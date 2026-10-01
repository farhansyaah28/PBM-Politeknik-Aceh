<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use PDO;

final class ReportingService
{
    public function __construct(private PDO $db) {}

    /** @return array{summary:array<string,int>,visualizations:array<string,list<array<string,mixed>>>,rows:list<array<string,mixed>>,sessions:list<array<string,mixed>>,waves:list<array<string,mixed>>,admission_paths:list<string>,stats:array{total_count:int,avg_score:float,max_score:float,min_score:float,passed_count:int,pass_rate:float},filters:array{session_id:string,search:string,admission_path:string,wave_id:string,decision_status:string},paginator:?Paginator} */
    public function report(
        string $actorId,
        mixed $page = 1,
        bool $allRows = false,
        ?string $sessionId = null,
        ?string $search = null,
        ?string $admissionPath = null,
        ?string $waveId = null,
        ?string $decisionStatus = null
    ): array {
        $this->internalActor($actorId);
        $summary = [
            'participants' => (int) $this->db->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
            'eligible_participants' => (int) $this->db->query('SELECT COUNT(*) FROM participants p INNER JOIN users u ON u.id=p.user_id AND u.status="ACTIVE" WHERE p.account_status="ACTIVE" AND p.verification_status="APPROVED"')->fetchColumn(),
            'scored_results' => (int) $this->db->query('SELECT COUNT(*) FROM exam_results WHERE status="SCORED"')->fetchColumn(),
            'published_decisions' => (int) $this->db->query('SELECT COUNT(*) FROM official_decisions WHERE is_current=1 AND communication_status="PUBLISHED"')->fetchColumn(),
        ];
        $decisionCounts = ['PASSED' => 0, 'NOT_PASSED' => 0, 'PENDING' => 0];
        $decisionRows = $this->db->query('SELECT CASE WHEN od.communication_status="PUBLISHED" AND od.decision="PASSED" THEN "PASSED" WHEN od.communication_status="PUBLISHED" AND od.decision="NOT_PASSED" THEN "NOT_PASSED" ELSE "PENDING" END AS decision_key, COUNT(*) AS total FROM exam_results er LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE er.status="SCORED" GROUP BY decision_key')->fetchAll();
        foreach ($decisionRows as $row) $decisionCounts[$row['decision_key']] = (int) $row['total'];
        $decisionDistribution = [
            ['key' => 'PASSED', 'label' => 'Lulus diumumkan', 'count' => $decisionCounts['PASSED']],
            ['key' => 'NOT_PASSED', 'label' => 'Belum lulus diumumkan', 'count' => $decisionCounts['NOT_PASSED']],
            ['key' => 'PENDING', 'label' => 'Menunggu keputusan atau publikasi', 'count' => $decisionCounts['PENDING']],
        ];

        $programPerformance = $this->db->query('SELECT COALESCE(sp.name,p.program_choice) AS label,COUNT(*) AS participant_count,ROUND(AVG(er.score),2) AS average_score FROM exam_results er INNER JOIN participants p ON p.id=er.participant_id LEFT JOIN study_programs sp ON sp.id=p.program_id WHERE er.status="SCORED" GROUP BY COALESCE(sp.name,p.program_choice) ORDER BY participant_count DESC,label ASC LIMIT 10')->fetchAll();
        foreach ($programPerformance as &$program) {
            $program['participant_count'] = (int) $program['participant_count'];
            $program['average_score'] = (float) $program['average_score'];
        }
        unset($program);

        $where = ['er.status = "SCORED"'];
        $params = [];

        if ($sessionId !== null && trim($sessionId) !== '' && trim($sessionId) !== 'ALL') {
            $where[] = 'er.session_id = :session_id';
            $params['session_id'] = trim($sessionId);
        }

        if ($waveId !== null && trim($waveId) !== '' && trim($waveId) !== 'ALL') {
            $where[] = 'p.wave_id = :wave_id';
            $params['wave_id'] = trim($waveId);
        }

        if ($search !== null && trim($search) !== '') {
            $where[] = '(p.full_name LIKE :search OR p.registration_number LIKE :search OR p.email LIKE :search)';
            $params['search'] = '%' . trim($search) . '%';
        }

        if ($admissionPath !== null && trim($admissionPath) !== '' && trim($admissionPath) !== 'ALL') {
            $where[] = 'p.admission_path = :admission_path';
            $params['admission_path'] = trim($admissionPath);
        }

        if ($decisionStatus !== null && trim($decisionStatus) !== '' && trim($decisionStatus) !== 'ALL') {
            $statusVal = trim($decisionStatus);
            if ($statusVal === 'PASSED') {
                $where[] = '(od.decision = "PASSED" OR (od.id IS NULL AND er.automatic_decision = "PASSED"))';
            } elseif ($statusVal === 'NOT_PASSED') {
                $where[] = '(od.decision = "NOT_PASSED" OR (od.id IS NULL AND er.automatic_decision = "NOT_PASSED"))';
            } elseif ($statusVal === 'PUBLISHED') {
                $where[] = 'od.communication_status = "PUBLISHED"';
            } elseif ($statusVal === 'PENDING') {
                $where[] = '(od.id IS NULL OR od.communication_status != "PUBLISHED")';
            }
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $statsStmt = $this->db->prepare('
            SELECT 
                COUNT(*) AS total_count,
                COALESCE(ROUND(AVG(er.score), 2), 0) AS avg_score,
                COALESCE(ROUND(MAX(er.score), 2), 0) AS max_score,
                COALESCE(ROUND(MIN(er.score), 2), 0) AS min_score,
                SUM(CASE WHEN er.score >= er.passing_grade THEN 1 ELSE 0 END) AS passed_count
            FROM exam_results er 
            INNER JOIN participants p ON p.id=er.participant_id
            LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1' . $whereSql
        );
        $statsStmt->execute($params);
        $statsRow = $statsStmt->fetch();

        $statsTotal = (int) ($statsRow['total_count'] ?? 0);
        $statsPassed = (int) ($statsRow['passed_count'] ?? 0);

        $paginator = $allRows ? null : Paginator::fromRequest($statsTotal, $page);
        $limit = $allRows ? ' LIMIT 5000' : ' LIMIT ' . $paginator->perPage . ' OFFSET ' . $paginator->offset();

        $sql = 'SELECT p.registration_number, p.full_name, p.admission_path, COALESCE(sp.name, p.program_choice) AS program_name, COALESCE(aw.name, p.wave) AS wave_name, COALESCE(es.name, "-") AS session_name, er.score, er.correct_count, er.incorrect_count, er.unanswered_count, er.scored_at, od.decision AS official_decision, od.communication_status, od.published_at 
                FROM exam_results er 
                INNER JOIN participants p ON p.id=er.participant_id 
                LEFT JOIN study_programs sp ON sp.id=p.program_id 
                LEFT JOIN admission_waves aw ON aw.id=p.wave_id 
                LEFT JOIN exam_sessions es ON es.id=er.session_id
                LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 ' . $whereSql . ' 
                ORDER BY er.scored_at DESC' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $sessions = $this->db->query('SELECT id, name FROM exam_sessions ORDER BY name ASC')->fetchAll();
        $waves = $this->db->query('SELECT id, name FROM admission_waves ORDER BY name ASC')->fetchAll();
        $admissionPaths = $this->db->query('SELECT DISTINCT name FROM admission_paths WHERE is_active = 1 ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);

        return [
            'summary' => $summary,
            'visualizations' => [
                'decision_distribution' => $decisionDistribution,
                'program_performance' => $programPerformance
            ],
            'rows' => $rows,
            'sessions' => $sessions,
            'waves' => $waves,
            'admission_paths' => $admissionPaths,
            'stats' => [
                'total_count' => $statsTotal,
                'avg_score' => (float) ($statsRow['avg_score'] ?? 0),
                'max_score' => (float) ($statsRow['max_score'] ?? 0),
                'min_score' => (float) ($statsRow['min_score'] ?? 0),
                'passed_count' => $statsPassed,
                'pass_rate' => $statsTotal > 0 ? round(($statsPassed / $statsTotal) * 100, 1) : 0.0,
            ],
            'filters' => [
                'session_id' => $sessionId ?? '',
                'search' => $search ?? '',
                'admission_path' => $admissionPath ?? '',
                'wave_id' => $waveId ?? '',
                'decision_status' => $decisionStatus ?? '',
            ],
            'paginator' => $paginator
        ];
    }

    public function recordOutput(string $actorId, string $outputType): void
    {
        $actions = ['EXPORT_CSV' => 'report.exported', 'PRINT' => 'report.printed'];
        if (!isset($actions[$outputType])) throw new ValidationException(['report' => 'Jenis keluaran laporan tidak valid.']);
        $actor = $this->internalActor($actorId);
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, :actor_type, :actor_id, NULL, :action, "report", :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'actor_type' => $actor['role'], 'actor_id' => $actorId, 'action' => $actions[$outputType], 'target_id' => 'standard-result-report', 'request_id' => bin2hex(random_bytes(12))]);
    }

    public function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    /** @return array<string,mixed> */
    private function internalActor(string $actorId): array
    {
        $statement = $this->db->prepare('SELECT id, role FROM users WHERE id=:actor_id AND role IN ("COMMITTEE", "ADMIN") AND status="ACTIVE" LIMIT 1');
        $statement->execute(['actor_id' => $actorId]); $actor = $statement->fetch();
        if (!$actor) throw new ValidationException(['report' => 'Laporan hanya dapat diakses Panitia atau Admin aktif.']);
        return $actor;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
