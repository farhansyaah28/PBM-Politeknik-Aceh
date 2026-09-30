<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class OfficialDecisionService
{
    public function __construct(private PDO $db) {}

    /** @return array<string,mixed> */
    public function decide(string $resultId, string $actorId, string $decision, string $note): array
    {
        if (!in_array($decision, ['PASSED','NOT_PASSED'], true)) throw new ValidationException(['decision' => 'Keputusan kelulusan tidak valid.']);
        if (trim($note) === '') throw new ValidationException(['note' => 'Catatan keputusan Panitia wajib diisi.']);
        $this->db->beginTransaction();
        try {
            $actor = $this->internalActor($actorId);
            $result = $this->db->prepare('SELECT id,participant_id FROM exam_results WHERE id=:result_id AND status="SCORED" FOR UPDATE');
            $result->execute(['result_id' => $resultId]); $resultRow = $result->fetch();
            if (!$resultRow) throw new ValidationException(['decision' => 'Hasil ujian belum tersedia untuk keputusan resmi.']);
            $previous = $this->db->prepare('SELECT id FROM official_decisions WHERE result_id=:result_id AND is_current=1 FOR UPDATE');
            $previous->execute(['result_id' => $resultId]); $previousId = $previous->fetchColumn() ?: null;
            if ($previousId !== null) $this->db->prepare('UPDATE official_decisions SET is_current=0 WHERE id=:id')->execute(['id' => $previousId]);
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO official_decisions (id,result_id,participant_id,decision,note,communication_status,is_current,supersedes_decision_id,decided_by,created_at) VALUES (:id,:result_id,:participant_id,:decision,:note,"PENDING",1,:previous_id,:actor_id,UTC_TIMESTAMP())')->execute([
                'id' => $id, 'result_id' => $resultId, 'participant_id' => $resultRow['participant_id'], 'decision' => $decision,
                'note' => trim($note), 'previous_id' => $previousId, 'actor_id' => $actorId,
            ]);
            $this->audit($actor['role'], $actorId, $resultRow['participant_id'], 'official_decision.recorded', 'official_decision', $id);
            $this->db->commit();
            return ['id' => $id, 'decision' => $decision, 'is_current' => 1, 'communication_status' => 'PENDING'];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    public function participantDecision(string $participantId): ?array
    {
        $this->publishDue();
        $statement = $this->db->prepare('SELECT decision,note,published_at FROM official_decisions WHERE participant_id=:participant_id AND is_current=1 AND communication_status="PUBLISHED" LIMIT 1');
        $statement->execute(['participant_id' => $participantId]);
        return $statement->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function participantDecisionForResult(string $resultId, string $participantId): ?array
    {
        $this->publishDue();
        $statement = $this->db->prepare('SELECT decision,note,published_at FROM official_decisions WHERE result_id=:result_id AND participant_id=:participant_id AND is_current=1 AND communication_status="PUBLISHED" LIMIT 1');
        $statement->execute(['result_id' => $resultId, 'participant_id' => $participantId]);
        return $statement->fetch() ?: null;
    }

    public function publish(string $decisionId, string $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $actor = $this->internalActor($actorId);
            $decision = $this->db->prepare('SELECT id,participant_id FROM official_decisions WHERE id=:decision_id AND is_current=1 AND communication_status="PENDING" FOR UPDATE');
            $decision->execute(['decision_id' => $decisionId]); $decisionRow = $decision->fetch();
            if (!$decisionRow) throw new ValidationException(['decision' => 'Keputusan tidak tersedia untuk dipublikasikan.']);
            $this->db->prepare('UPDATE official_decisions SET communication_status="PUBLISHED",scheduled_publish_at=NULL,scheduled_by=NULL,published_at=UTC_TIMESTAMP(),published_by=:actor_id WHERE id=:decision_id')->execute(['actor_id' => $actorId, 'decision_id' => $decisionId]);
            $this->audit($actor['role'], $actorId, $decisionRow['participant_id'], 'official_decision.published', 'official_decision', $decisionId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function scheduleAllPending(string $publishAtWib, string $actorId): int
    {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $publishAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $publishAtWib, $timezone);
        if (!$publishAt || $publishAt->format('Y-m-d\TH:i') !== $publishAtWib || $publishAt <= new DateTimeImmutable('now', $timezone)) {
            throw new ValidationException(['schedule' => 'Waktu publikasi harus berupa waktu WIB yang akan datang.']);
        }
        $actor = $this->internalActor($actorId);
        $this->db->beginTransaction();
        try {
            $pending = $this->db->query('SELECT id,participant_id FROM official_decisions WHERE is_current=1 AND communication_status="PENDING" FOR UPDATE')->fetchAll();
            if ($pending === []) throw new ValidationException(['schedule' => 'Belum ada keputusan pending untuk dijadwalkan.']);
            $utc = $publishAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $update = $this->db->prepare('UPDATE official_decisions SET scheduled_publish_at=:publish_at,scheduled_by=:actor_id WHERE is_current=1 AND communication_status="PENDING"');
            $update->execute(['publish_at' => $utc, 'actor_id' => $actorId]);
            foreach ($pending as $row) $this->audit($actor['role'], $actorId, $row['participant_id'], 'official_decision.scheduled', 'official_decision', $row['id']);
            $this->db->commit();
            return count($pending);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function cancelAllSchedules(string $actorId): int
    {
        $actor = $this->internalActor($actorId);
        $this->db->beginTransaction();
        try {
            $scheduled = $this->db->query('SELECT id,participant_id FROM official_decisions WHERE is_current=1 AND communication_status="PENDING" AND scheduled_publish_at IS NOT NULL FOR UPDATE')->fetchAll();
            $this->db->exec('UPDATE official_decisions SET scheduled_publish_at=NULL,scheduled_by=NULL WHERE is_current=1 AND communication_status="PENDING" AND scheduled_publish_at IS NOT NULL');
            foreach ($scheduled as $row) $this->audit($actor['role'], $actorId, $row['participant_id'], 'official_decision.schedule_cancelled', 'official_decision', $row['id']);
            $this->db->commit();
            return count($scheduled);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function publishDue(): int
    {
        $this->db->beginTransaction();
        try {
            $due = $this->db->query('SELECT id,participant_id FROM official_decisions WHERE is_current=1 AND communication_status="PENDING" AND scheduled_publish_at IS NOT NULL AND scheduled_publish_at<=UTC_TIMESTAMP() FOR UPDATE')->fetchAll();
            $update = $this->db->prepare('UPDATE official_decisions SET communication_status="PUBLISHED",published_at=scheduled_publish_at,published_by=scheduled_by WHERE id=:id');
            foreach ($due as $row) {
                $update->execute(['id' => $row['id']]);
                $this->audit('SYSTEM', null, $row['participant_id'], 'official_decision.auto_published', 'official_decision', $row['id']);
            }
            $this->db->commit();
            return count($due);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @param array{search?:string,status?:string,page?:mixed} $filters @return array{rows:list<array<string,mixed>>,paginator:Paginator} */
    public function queue(string $actorId, array $filters = []): array
    {
        $this->internalActor($actorId);
        $this->publishDue();
        $where = ['er.status="SCORED"']; $params = [];
        $search = trim($filters['search'] ?? '');
        $status = strtoupper(trim($filters['status'] ?? 'ALL'));
        if ($search !== '') {
            $where[] = '(p.registration_number LIKE :search_registration OR p.full_name LIKE :search_name)';
            $params['search_registration'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
        }
        $where[] = 'er.id=(SELECT latest_result.id FROM exam_results latest_result WHERE latest_result.participant_id=er.participant_id AND latest_result.status="SCORED" ORDER BY latest_result.scored_at DESC,latest_result.created_at DESC,latest_result.id DESC LIMIT 1)';
        if ($status === 'UNDECIDED') $where[] = 'od.id IS NULL';
        elseif ($status === 'PENDING') $where[] = 'od.communication_status="PENDING" AND od.scheduled_publish_at IS NULL';
        elseif ($status === 'SCHEDULED') $where[] = 'od.communication_status="PENDING" AND od.scheduled_publish_at IS NOT NULL';
        elseif ($status === 'PUBLISHED') $where[] = 'od.communication_status="PUBLISHED"';
        $from = ' FROM exam_results er INNER JOIN participants p ON p.id=er.participant_id INNER JOIN exam_sessions es ON es.id=er.session_id LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE ' . implode(' AND ', $where);
        $count=$this->db->prepare('SELECT COUNT(*)'.$from);$count->execute($params);$paginator=Paginator::fromRequest((int)$count->fetchColumn(),$filters['page']??1);
        $statement = $this->db->prepare('SELECT er.id AS result_id,er.score,er.passing_grade AS result_passing_grade,er.automatic_decision,er.scored_at,p.registration_number,p.full_name,es.name AS session_name,
            od.id AS decision_id,od.decision,od.note,od.communication_status,od.scheduled_publish_at,od.published_at,od.created_at AS decided_at
            '.$from.' ORDER BY od.id IS NULL DESC,od.communication_status="PENDING" DESC,od.scheduled_publish_at ASC,er.scored_at DESC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset());
        $statement->execute($params); $rows = $statement->fetchAll();
        foreach ($rows as &$row) $row['scheduled_publish_at_wib'] = $this->formatWib($row['scheduled_publish_at'] ?? null);
        return ['rows'=>$rows,'paginator'=>$paginator];
    }

    /** @return array<string,mixed> */
    private function internalActor(string $actorId): array
    {
        $statement = $this->db->prepare('SELECT id,role FROM users WHERE id=:actor_id AND role IN ("COMMITTEE","ADMIN") AND status="ACTIVE" LIMIT 1');
        $statement->execute(['actor_id' => $actorId]); $actor = $statement->fetch();
        if (!$actor) throw new ValidationException(['decision' => 'Keputusan resmi hanya dapat ditetapkan oleh Panitia atau Admin aktif.']);
        return $actor;
    }

    private function audit(string $role, ?string $actorId, string $participantId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id,actor_type,actor_id,participant_id,action,target_type,target_id,request_id,created_at) VALUES (:id,:actor_type,:actor_id,:participant_id,:action,:target_type,:target_id,:request_id,UTC_TIMESTAMP())')->execute([
            'id' => $this->uuid(), 'actor_type' => $role, 'actor_id' => $actorId, 'participant_id' => $participantId,
            'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12)),
        ]);
    }

    private function formatWib(?string $value): ?string
    {
        if (!$value) return null;
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y, H:i') . ' WIB';
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
