<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use PDO;

final class RetentionService
{
    public function __construct(private PDO $db) {}

    /** @return array{id:string,is_active:int} */
    public function applyHold(string $participantId, string $adminId, string $reason): array
    {
        if (trim($reason) === '') throw new ValidationException(['retention' => 'Alasan legal hold wajib diisi.']);
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($adminId);
            $participant = $this->db->prepare('SELECT id FROM participants WHERE id=:participant_id FOR UPDATE');
            $participant->execute(['participant_id' => $participantId]);
            if (!$participant->fetch()) throw new ValidationException(['retention' => 'Peserta tidak ditemukan.']);
            $existing = $this->db->prepare('SELECT id FROM retention_holds WHERE participant_id=:participant_id AND is_active=1 FOR UPDATE');
            $existing->execute(['participant_id' => $participantId]);
            $id = $existing->fetchColumn();
            if (!$id) {
                $id = $this->uuid();
                $this->db->prepare('INSERT INTO retention_holds (id, participant_id, reason, is_active, applied_by, applied_at) VALUES (:id, :participant_id, :reason, 1, :admin_id, UTC_TIMESTAMP())')->execute(['id' => $id, 'participant_id' => $participantId, 'reason' => trim($reason), 'admin_id' => $adminId]);
                $this->audit($adminId, $participantId, 'retention.hold_applied', 'retention_hold', $id);
            }
            $this->db->commit();
            return ['id' => $id, 'is_active' => 1];
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function hasActiveHold(string $participantId): bool
    {
        $statement = $this->db->prepare('SELECT id FROM retention_holds WHERE participant_id=:participant_id AND is_active=1 LIMIT 1');
        $statement->execute(['participant_id' => $participantId]); return (bool) $statement->fetch();
    }

    public function releaseHold(string $holdId, string $adminId): void
    {
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($adminId);
            $hold = $this->db->prepare('SELECT id, participant_id FROM retention_holds WHERE id=:id AND is_active=1 FOR UPDATE');
            $hold->execute(['id' => $holdId]); $record = $hold->fetch();
            if (!$record) throw new ValidationException(['retention' => 'Legal hold aktif tidak ditemukan atau sudah dicabut.']);
            $this->db->prepare('UPDATE retention_holds SET is_active=0, released_by=:admin_id, released_at=UTC_TIMESTAMP() WHERE id=:id AND is_active=1')->execute(['id' => $holdId, 'admin_id' => $adminId]);
            $this->audit($adminId, $record['participant_id'], 'retention.hold_released', 'retention_hold', $holdId);
            $this->db->commit();
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function applyHoldToAll(string $adminId, string $reason): int
    {
        if (trim($reason) === '') throw new ValidationException(['retention' => 'Alasan legal hold wajib diisi.']);
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($adminId);
            $participants = $this->db->query('SELECT id FROM participants FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            $existing = $this->db->prepare('SELECT id FROM retention_holds WHERE participant_id=:participant_id AND is_active=1 FOR UPDATE');
            $insert = $this->db->prepare('INSERT INTO retention_holds (id, participant_id, reason, is_active, applied_by, applied_at) VALUES (:id, :participant_id, :reason, 1, :admin_id, UTC_TIMESTAMP())');
            $created = 0;
            foreach ($participants as $participantId) {
                $existing->execute(['participant_id' => $participantId]);
                if ($existing->fetchColumn()) continue;
                $holdId = $this->uuid();
                $insert->execute(['id' => $holdId, 'participant_id' => $participantId, 'reason' => trim($reason), 'admin_id' => $adminId]);
                $this->audit($adminId, $participantId, 'retention.hold_applied', 'retention_hold', $holdId);
                $created++;
            }
            $this->db->commit();
            return $created;
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    /** @return array<string,int> */
    public function inventory(string $adminId): array
    {
        $this->activeAdmin($adminId);
        return [
            'proctoring_photos' => (int) $this->db->query('SELECT COUNT(*) FROM proctoring_photos')->fetchColumn(),
            'security_events' => (int) $this->db->query('SELECT COUNT(*) FROM exam_security_events')->fetchColumn(),
            'private_records' => (int) $this->db->query('SELECT (SELECT COUNT(*) FROM consent_records) + (SELECT COUNT(*) FROM audit_logs)')->fetchColumn(),
            'active_holds' => (int) $this->db->query('SELECT COUNT(*) FROM retention_holds WHERE is_active=1')->fetchColumn(),
        ];
    }

    /** @return array{rows:list<array<string,mixed>>,paginator:Paginator} */
    public function activeHolds(string $adminId, mixed $page = 1): array
    {
        $this->activeAdmin($adminId);
        $total=(int)$this->db->query('SELECT COUNT(*) FROM retention_holds WHERE is_active=1')->fetchColumn();$paginator=Paginator::fromRequest($total,$page);
        $rows=$this->db->query('SELECT rh.id, rh.reason, rh.applied_at, p.registration_number, p.full_name FROM retention_holds rh INNER JOIN participants p ON p.id=rh.participant_id WHERE rh.is_active=1 ORDER BY rh.applied_at DESC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset())->fetchAll();
        return ['rows'=>$rows,'paginator'=>$paginator];
    }

    /** @return array{defaults:array<string,int>,overrides:array<string,array<string,mixed>>} */
    public function retentionConfiguration(string $adminId): array
    {
        $this->activeAdmin($adminId);
        $defaults = [];
        foreach ($this->db->query('SELECT policy_code, retention_years FROM retention_default_policies ORDER BY policy_code')->fetchAll() as $policy) $defaults[$policy['policy_code']] = (int) $policy['retention_years'];
        $overrides = [];
        foreach ($this->db->query('SELECT o.id, o.participant_id, o.retention_years, o.reason, o.updated_at, p.registration_number, p.full_name FROM participant_retention_overrides o INNER JOIN participants p ON p.id=o.participant_id ORDER BY o.updated_at DESC')->fetchAll() as $override) $overrides[$override['participant_id']] = $override;
        return ['defaults' => $defaults, 'overrides' => $overrides];
    }

    /** @param array<string,int|string> $years */
    public function updateDefaultRetentionYears(array $years, string $adminId): void
    {
        $this->validateYears($years);
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($adminId);
            $update = $this->db->prepare('UPDATE retention_default_policies SET retention_years=:years, updated_by=:admin_id, updated_at=UTC_TIMESTAMP() WHERE policy_code=:code');
            foreach ($this->policyCodes() as $code) $update->execute(['years'=>(int) $years[$code], 'admin_id'=>$adminId, 'code'=>$code]);
            $this->audit($adminId, null, 'retention.default_policy_updated', 'retention_default_policy', 'all');
            $this->db->commit();
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function setParticipantRetentionOverride(string $participantId, int|string $years, string $reason, string $adminId): void
    {
        $this->validateRetentionYears($years);
        if (trim($reason) === '') throw new ValidationException(['retention' => 'Alasan pengecualian retensi wajib diisi.']);
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($adminId);
            $participant = $this->db->prepare('SELECT id FROM participants WHERE id=:id FOR UPDATE'); $participant->execute(['id'=>$participantId]);
            if (!$participant->fetch()) throw new ValidationException(['retention' => 'Peserta tidak ditemukan.']);
            $existing = $this->db->prepare('SELECT id FROM participant_retention_overrides WHERE participant_id=:participant_id FOR UPDATE'); $existing->execute(['participant_id'=>$participantId]);
            $overrideId = $existing->fetchColumn();
            if ($overrideId) {
                $this->db->prepare('UPDATE participant_retention_overrides SET retention_years=:years, reason=:reason, updated_by=:admin_id, updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['years'=>(int)$years,'reason'=>trim($reason),'admin_id'=>$adminId,'id'=>$overrideId]);
            } else {
                $overrideId = $this->uuid();
                $this->db->prepare('INSERT INTO participant_retention_overrides (id,participant_id,retention_years,reason,created_by,created_at,updated_by,updated_at) VALUES (:id,:participant_id,:years,:reason,:created_by,UTC_TIMESTAMP(),:updated_by,UTC_TIMESTAMP())')->execute(['id'=>$overrideId,'participant_id'=>$participantId,'years'=>(int)$years,'reason'=>trim($reason),'created_by'=>$adminId,'updated_by'=>$adminId]);
            }
            $this->audit($adminId, $participantId, 'retention.participant_override_saved', 'participant_retention_override', $overrideId);
            $this->db->commit();
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function clearParticipantRetentionOverride(string $participantId, string $adminId): void
    {
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($adminId);
            $statement = $this->db->prepare('SELECT id FROM participant_retention_overrides WHERE participant_id=:participant_id FOR UPDATE'); $statement->execute(['participant_id'=>$participantId]);
            $overrideId = $statement->fetchColumn();
            if (!$overrideId) throw new ValidationException(['retention' => 'Pengecualian retensi peserta tidak ditemukan.']);
            $this->db->prepare('DELETE FROM participant_retention_overrides WHERE id=:id')->execute(['id'=>$overrideId]);
            $this->audit($adminId, $participantId, 'retention.participant_override_cleared', 'participant_retention_override', $overrideId);
            $this->db->commit();
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    private function activeAdmin(string $adminId): void
    {
        $statement = $this->db->prepare('SELECT id FROM users WHERE id=:admin_id AND role="ADMIN" AND status="ACTIVE" LIMIT 1');
        $statement->execute(['admin_id' => $adminId]); if (!$statement->fetch()) throw new ValidationException(['retention' => 'Legal hold hanya dapat dikelola Admin aktif.']);
    }

    /** @param array<string,int|string> $years */
    private function validateYears(array $years): void
    {
        foreach ($this->policyCodes() as $code) {
            if (!array_key_exists($code, $years)) throw new ValidationException(['retention' => 'Seluruh masa retensi default wajib diisi.']);
            $this->validateRetentionYears($years[$code]);
        }
    }

    private function validateRetentionYears(int|string $years): void
    {
        if (!ctype_digit((string) $years) || (int) $years < 1 || (int) $years > 100) throw new ValidationException(['retention' => 'Masa retensi harus antara 1 sampai 100 tahun.']);
    }

    /** @return list<string> */
    private function policyCodes(): array { return ['PROCTORING_PHOTOS', 'SECURITY_EVENTS', 'CONSENT_RECORDS', 'AUDIT_LOGS']; }

    private function audit(string $adminId, ?string $participantId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "ADMIN", :admin_id, :participant_id, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'admin_id' => $adminId, 'participant_id' => $participantId, 'action' => $action, 'target_type'=>$targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function uuid(): string { $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4)); }
}
