<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Support\Paginator;

final class CommitteeVerificationService
{
    private const IDENTITY_CHECKS = ['V-01','V-02','V-03','V-04','V-05','V-06','V-07','V-08','V-09','V-10'];

    public function __construct(private PDO $db) {}

    /** @return array{rows:list<array<string,mixed>>,paginator:Paginator} */
    public function queue(string $search = '', mixed $page = 1): array
    {
        $params=['registration' => '%' . $search . '%', 'name' => '%' . $search . '%'];
        $count=$this->db->prepare('SELECT COUNT(*) FROM participants p WHERE p.registration_number LIKE :registration OR p.full_name LIKE :name');$count->execute($params);
        $paginator=Paginator::fromRequest((int)$count->fetchColumn(),$page);
        $statement = $this->db->prepare('SELECT p.id,p.registration_number,p.full_name,p.verification_status FROM participants p WHERE p.registration_number LIKE :registration OR p.full_name LIKE :name ORDER BY (p.verification_status IN ("PENDING","NEEDS_CORRECTION")) DESC,p.created_at ASC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset());
        $statement->execute($params);
        return ['rows'=>$statement->fetchAll(),'paginator'=>$paginator];
    }

    /** @return array<string,mixed>|null */
    public function workspace(string $participantId): ?array
    {
        $statement = $this->db->prepare('SELECT p.*,
            (SELECT decision FROM verification_decisions WHERE participant_id=p.id ORDER BY created_at DESC LIMIT 1) last_identity_decision,
            (SELECT checklist_json FROM verification_decisions WHERE participant_id=p.id ORDER BY created_at DESC LIMIT 1) identity_checklist_json,
            (SELECT note FROM verification_decisions WHERE participant_id=p.id ORDER BY created_at DESC LIMIT 1) identity_note
            FROM participants p
            WHERE p.id=:id LIMIT 1');
        $statement->execute(['id' => $participantId]);
        $result = $statement->fetch();
        if (!$result) return null;
        $result['identity_checklist'] = $this->decodeChecklist($result['identity_checklist_json'] ?? null, self::IDENTITY_CHECKS);
        unset($result['identity_checklist_json']);
        $result['eligible'] = $result['verification_status'] === 'APPROVED';
        return $result;
    }

    /** @param array<int,string> $checks */
    public function decideIdentity(string $participantId, string $actorId, string $decision, array $checks, string $note): void
    {
        if (!in_array($decision, ['APPROVED','NEEDS_CORRECTION','REJECTED'], true)) throw new ValidationException(['identity' => 'Keputusan identitas tidak valid.']);
        $checks = $this->validatedChecklist($checks, self::IDENTITY_CHECKS, 'V');
        if ($decision === 'APPROVED' && $checks !== self::IDENTITY_CHECKS) throw new ValidationException(['identity' => 'Seluruh checklist V-01 sampai V-10 wajib dipenuhi untuk menyetujui identitas.']);
        if ($decision !== 'APPROVED' && trim($note) === '') throw new ValidationException(['identity' => 'Catatan wajib diisi untuk perbaikan atau penolakan.']);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE participants SET verification_status=:decision,updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['decision' => $decision, 'id' => $participantId]);
            $this->db->prepare('INSERT INTO verification_decisions (id,participant_id,decision,checklist_json,note,reviewed_by,created_at) VALUES (:id,:participant,:decision,:checks,:note,:actor,UTC_TIMESTAMP())')->execute([
                'id' => $this->uuid(), 'participant' => $participantId, 'decision' => $decision,
                'checks' => json_encode($checks, JSON_THROW_ON_ERROR), 'note' => trim($note) ?: null, 'actor' => $actorId,
            ]);
            $this->audit($actorId, $participantId, 'verification.reviewed', 'verification');
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function approveAll(string $actorId): int
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->query('SELECT id FROM participants WHERE verification_status <> "APPROVED" FOR UPDATE');
            $participantIds = $statement->fetchAll(PDO::FETCH_COLUMN);
            if ($participantIds === []) {
                $this->db->commit();
                return 0;
            }

            $updateStatus = $this->db->prepare('UPDATE participants SET verification_status="APPROVED", updated_at=UTC_TIMESTAMP() WHERE id=:id');
            $insertDecision = $this->db->prepare('INSERT INTO verification_decisions (id,participant_id,decision,checklist_json,note,reviewed_by,created_at) VALUES (:id,:participant,"APPROVED",:checks,:note,:actor,UTC_TIMESTAMP())');
            $checksJson = json_encode(self::IDENTITY_CHECKS, JSON_THROW_ON_ERROR);
            $note = 'Disetujui secara massal oleh panitia.';

            $count = 0;
            foreach ($participantIds as $participantId) {
                $updateStatus->execute(['id' => $participantId]);
                $insertDecision->execute([
                    'id' => $this->uuid(),
                    'participant' => $participantId,
                    'checks' => $checksJson,
                    'note' => $note,
                    'actor' => $actorId,
                ]);
                $this->audit($actorId, $participantId, 'verification.bulk_approved', 'verification');
                $count++;
            }

            $this->db->commit();
            return $count;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @param array<int,mixed> $checks @param array<int,string> $allowed @return array<int,string> */
    private function validatedChecklist(array $checks, array $allowed, string $prefix): array
    {
        $normalized = array_values(array_unique(array_filter($checks, 'is_string')));
        if (array_diff($normalized, $allowed) !== []) throw new ValidationException(['checklist' => 'Checklist ' . $prefix . ' berisi kode yang tidak valid.']);
        return array_values(array_intersect($allowed, $normalized));
    }

    /** @param array<int,string> $allowed @return array<int,string> */
    private function decodeChecklist(mixed $json, array $allowed): array
    {
        if (!is_string($json) || $json === '') return [];
        try { $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return []; }
        return is_array($decoded) ? array_values(array_intersect($allowed, $decoded)) : [];
    }

    private function audit(string $actor, string $participant, string $action, string $type): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id,actor_type,actor_id,participant_id,action,target_type,target_id,request_id,created_at) VALUES (:id,"COMMITTEE",:actor,:participant,:action,:type,:target,:request,UTC_TIMESTAMP())')->execute([
            'id' => $this->uuid(), 'actor' => $actor, 'participant' => $participant, 'action' => $action,
            'type' => $type, 'target' => $participant, 'request' => bin2hex(random_bytes(12)),
        ]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 15) | 64); $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
