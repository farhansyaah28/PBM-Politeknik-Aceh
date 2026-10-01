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

    public function publishSelectedBatch(array $decisionIds, string $actorId): int
    {
        $cleanIds = array_values(array_filter(array_map('strval', $decisionIds)));
        if ($cleanIds === []) return 0;
        $actor = $this->internalActor($actorId);
        $this->db->beginTransaction();
        try {
            $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
            $stmt = $this->db->prepare('SELECT id, participant_id FROM official_decisions WHERE id IN (' . $inClause . ') AND is_current=1 AND communication_status="PENDING" FOR UPDATE');
            $stmt->execute($cleanIds);
            $pending = $stmt->fetchAll();

            $update = $this->db->prepare('UPDATE official_decisions SET communication_status="PUBLISHED", scheduled_publish_at=NULL, scheduled_by=NULL, published_at=UTC_TIMESTAMP(), published_by=? WHERE id=?');
            $count = 0;
            foreach ($pending as $row) {
                $update->execute([$actorId, $row['id']]);
                $this->audit($actor['role'], $actorId, $row['participant_id'], 'official_decision.published', 'official_decision', $row['id']);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function confirmSelectedAutoDecisionsBatch(array $resultIds, string $actorId): int
    {
        $cleanIds = array_values(array_filter(array_map('strval', $resultIds)));
        if ($cleanIds === []) return 0;
        $actor = $this->internalActor($actorId);
        $this->db->beginTransaction();
        try {
            $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
            $stmt = $this->db->prepare('SELECT er.id AS result_id, er.participant_id, er.automatic_decision FROM exam_results er LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE er.id IN (' . $inClause . ') AND er.status="SCORED" AND od.id IS NULL FOR UPDATE');
            $stmt->execute($cleanIds);
            $undecided = $stmt->fetchAll();

            $insert = $this->db->prepare('INSERT INTO official_decisions (id, result_id, participant_id, decision, note, communication_status, is_current, decided_by, created_at) VALUES (?, ?, ?, ?, ?, "PENDING", 1, ?, UTC_TIMESTAMP())');
            $count = 0;
            foreach ($undecided as $row) {
                $decId = $this->uuid();
                $decisionValue = $row['automatic_decision'] === 'PASSED' ? 'PASSED' : 'NOT_PASSED';
                $noteValue = $decisionValue === 'PASSED' ? 'Selamat! Anda dinyatakan Lulus berdasarkan standar nilai kelulusan sesi ujian.' : 'Belum memenuhi batas nilai kelulusan minimum.';
                $insert->execute([$decId, $row['result_id'], $row['participant_id'], $decisionValue, $noteValue, $actorId]);
                $this->audit($actor['role'], $actorId, $row['participant_id'], 'official_decision.recorded', 'official_decision', $decId);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function confirmAllAutoDecisionsBatch(string $actorId, ?string $sessionId = null, ?string $admissionPath = null): int
    {
        $actor = $this->internalActor($actorId);
        $this->db->beginTransaction();
        try {
            $where = ['er.status = "SCORED"', 'od.id IS NULL'];
            $params = [];
            $where[] = 'er.id = (SELECT latest_result.id FROM exam_results latest_result WHERE latest_result.participant_id=er.participant_id AND latest_result.status="SCORED" ORDER BY latest_result.scored_at DESC, latest_result.created_at DESC, latest_result.id DESC LIMIT 1)';
            if ($sessionId !== null && $sessionId !== '' && $sessionId !== 'ALL') {
                $where[] = 'er.session_id = :session_id';
                $params['session_id'] = $sessionId;
            }
            if ($admissionPath !== null && $admissionPath !== '' && $admissionPath !== 'ALL') {
                $where[] = 'p.admission_path = :admission_path';
                $params['admission_path'] = $admissionPath;
            }
            $sql = 'SELECT er.id AS result_id, er.participant_id, er.automatic_decision FROM exam_results er INNER JOIN participants p ON p.id=er.participant_id LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE ' . implode(' AND ', $where) . ' FOR UPDATE';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $undecided = $stmt->fetchAll();

            $insert = $this->db->prepare('INSERT INTO official_decisions (id, result_id, participant_id, decision, note, communication_status, is_current, decided_by, created_at) VALUES (:id, :result_id, :participant_id, :decision, :note, "PENDING", 1, :actor_id, UTC_TIMESTAMP())');
            $count = 0;
            foreach ($undecided as $row) {
                $decId = $this->uuid();
                $decisionValue = $row['automatic_decision'] === 'PASSED' ? 'PASSED' : 'NOT_PASSED';
                $noteValue = $decisionValue === 'PASSED' ? 'Selamat! Anda dinyatakan Lulus berdasarkan standar nilai kelulusan sesi ujian.' : 'Belum memenuhi batas nilai kelulusan minimum.';
                $insert->execute([
                    'id' => $decId,
                    'result_id' => $row['result_id'],
                    'participant_id' => $row['participant_id'],
                    'decision' => $decisionValue,
                    'note' => $noteValue,
                    'actor_id' => $actorId,
                ]);
                $this->audit($actor['role'], $actorId, $row['participant_id'], 'official_decision.recorded', 'official_decision', $decId);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function publishAllPendingBatch(string $actorId, ?string $sessionId = null, ?string $admissionPath = null): int
    {
        $actor = $this->internalActor($actorId);
        $this->db->beginTransaction();
        try {
            $where = ['od.is_current = 1', 'od.communication_status = "PENDING"'];
            $params = [];
            if ($sessionId !== null && $sessionId !== '' && $sessionId !== 'ALL') {
                $where[] = 'er.session_id = :session_id';
                $params['session_id'] = $sessionId;
            }
            if ($admissionPath !== null && $admissionPath !== '' && $admissionPath !== 'ALL') {
                $where[] = 'p.admission_path = :admission_path';
                $params['admission_path'] = $admissionPath;
            }
            $sql = 'SELECT od.id, od.participant_id FROM official_decisions od INNER JOIN exam_results er ON er.id=od.result_id INNER JOIN participants p ON p.id=od.participant_id WHERE ' . implode(' AND ', $where) . ' FOR UPDATE';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $pending = $stmt->fetchAll();

            $update = $this->db->prepare('UPDATE official_decisions SET communication_status="PUBLISHED", scheduled_publish_at=NULL, scheduled_by=NULL, published_at=UTC_TIMESTAMP(), published_by=:actor_id WHERE id=:id');
            $count = 0;
            foreach ($pending as $row) {
                $update->execute(['actor_id' => $actorId, 'id' => $row['id']]);
                $this->audit($actor['role'], $actorId, $row['participant_id'], 'official_decision.published', 'official_decision', $row['id']);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @param array{search?:string,status?:string,session_id?:string,admission_path?:string,page?:mixed} $filters @return array{rows:list<array<string,mixed>>,paginator:Paginator,sessions:list<array<string,mixed>>,admission_paths:list<string>,stats:array<string,int>} */
    public function queue(string $actorId, array $filters = []): array
    {
        $this->internalActor($actorId);
        $this->publishDue();
        $where = ['er.status="SCORED"']; $params = [];
        $search = trim($filters['search'] ?? '');
        $status = strtoupper(trim($filters['status'] ?? 'ALL'));
        $sessionId = trim($filters['session_id'] ?? '');
        $admissionPath = trim($filters['admission_path'] ?? '');

        if ($search !== '') {
            $where[] = '(p.registration_number LIKE :search_registration OR p.full_name LIKE :search_name)';
            $params['search_registration'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
        }
        if ($sessionId !== '' && $sessionId !== 'ALL') {
            $where[] = 'er.session_id = :session_id';
            $params['session_id'] = $sessionId;
        }
        if ($admissionPath !== '' && $admissionPath !== 'ALL') {
            $where[] = 'p.admission_path = :admission_path';
            $params['admission_path'] = $admissionPath;
        }
        $where[] = 'er.id=(SELECT latest_result.id FROM exam_results latest_result WHERE latest_result.participant_id=er.participant_id AND latest_result.status="SCORED" ORDER BY latest_result.scored_at DESC,latest_result.created_at DESC,latest_result.id DESC LIMIT 1)';
        
        $baseWhere = implode(' AND ', $where);
        $baseFrom = ' FROM exam_results er INNER JOIN participants p ON p.id=er.participant_id INNER JOIN exam_sessions es ON es.id=er.session_id LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE ' . $baseWhere;

        // Global stats for stats bar matching current filters (excluding status filter for accurate breakdown)
        $statsStmt = $this->db->prepare('SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN od.id IS NULL THEN 1 ELSE 0 END) AS undecided_count,
            SUM(CASE WHEN od.communication_status="PENDING" AND od.scheduled_publish_at IS NULL THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN od.communication_status="PENDING" AND od.scheduled_publish_at IS NOT NULL THEN 1 ELSE 0 END) AS scheduled_count,
            SUM(CASE WHEN od.communication_status="PUBLISHED" THEN 1 ELSE 0 END) AS published_count
            ' . $baseFrom);
        $statsStmt->execute($params);
        $statsRow = $statsStmt->fetch();
        $stats = [
            'total_count' => (int) ($statsRow['total_count'] ?? 0),
            'undecided_count' => (int) ($statsRow['undecided_count'] ?? 0),
            'pending_count' => (int) ($statsRow['pending_count'] ?? 0),
            'scheduled_count' => (int) ($statsRow['scheduled_count'] ?? 0),
            'published_count' => (int) ($statsRow['published_count'] ?? 0),
        ];

        if ($status === 'UNDECIDED') $where[] = 'od.id IS NULL';
        elseif ($status === 'PENDING') $where[] = 'od.communication_status="PENDING" AND od.scheduled_publish_at IS NULL';
        elseif ($status === 'SCHEDULED') $where[] = 'od.communication_status="PENDING" AND od.scheduled_publish_at IS NOT NULL';
        elseif ($status === 'PUBLISHED') $where[] = 'od.communication_status="PUBLISHED"';

        $from = ' FROM exam_results er INNER JOIN participants p ON p.id=er.participant_id INNER JOIN exam_sessions es ON es.id=er.session_id LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE ' . implode(' AND ', $where);
        $count=$this->db->prepare('SELECT COUNT(*)'.$from);$count->execute($params);$paginator=Paginator::fromRequest((int)$count->fetchColumn(),$filters['page']??1);
        $statement = $this->db->prepare('SELECT er.id AS result_id,er.score,er.is_visible,er.passing_grade AS result_passing_grade,er.automatic_decision,er.scored_at,p.registration_number,p.full_name,p.admission_path,p.program_choice,es.name AS session_name,
            od.id AS decision_id,od.decision,od.note,od.communication_status,od.scheduled_publish_at,od.published_at,od.created_at AS decided_at
            '.$from.' ORDER BY od.id IS NULL DESC,od.communication_status="PENDING" DESC,od.scheduled_publish_at ASC,er.scored_at DESC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset());
        $statement->execute($params); $rows = $statement->fetchAll();
        foreach ($rows as &$row) $row['scheduled_publish_at_wib'] = $this->formatWib($row['scheduled_publish_at'] ?? null);

        $sessions = $this->db->query('SELECT id, name FROM exam_sessions ORDER BY name ASC')->fetchAll();
        $admissionPaths = $this->db->query('SELECT DISTINCT name FROM admission_paths WHERE is_active = 1 ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);

        return ['rows'=>$rows,'paginator'=>$paginator,'sessions'=>$sessions,'admission_paths'=>$admissionPaths,'stats'=>$stats];
    }

    public function toggleScoreVisibility(string $resultId, int $isVisible, string $actorId): void
    {
        $actor = $this->internalActor($actorId);
        $stmt = $this->db->prepare('UPDATE exam_results SET is_visible=:is_visible WHERE id=:result_id');
        $stmt->execute(['is_visible' => $isVisible, 'result_id' => $resultId]);
    }

    public function toggleScoreVisibilityBatch(array $resultIds, int $isVisible, string $actorId): int
    {
        $actor = $this->internalActor($actorId);
        if ($resultIds === []) return 0;
        $inClause = implode(',', array_fill(0, count($resultIds), '?'));
        $params = array_merge([$isVisible], $resultIds);
        $stmt = $this->db->prepare('UPDATE exam_results SET is_visible = ? WHERE id IN (' . $inClause . ')');
        $stmt->execute($params);
        return $stmt->rowCount();
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
