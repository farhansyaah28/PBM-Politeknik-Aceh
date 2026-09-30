<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use PDO;

final class ReportingService
{
    public function __construct(private PDO $db) {}

    /** @return array{summary:array<string,int>,visualizations:array<string,list<array<string,mixed>>>,rows:list<array<string,mixed>>,paginator:?Paginator} */
    public function report(string $actorId, mixed $page = 1, bool $allRows = false): array
    {
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

        $total=(int)$this->db->query('SELECT COUNT(*) FROM exam_results WHERE status="SCORED"')->fetchColumn();$paginator=$allRows?null:Paginator::fromRequest($total,$page);
        $limit=$allRows?' LIMIT 5000':' LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset();
        $rows = $this->db->query('SELECT p.registration_number, p.full_name, COALESCE(sp.name, p.program_choice) AS program_name, COALESCE(aw.name, p.wave) AS wave_name, er.score, er.correct_count, er.incorrect_count, er.unanswered_count, er.scored_at, od.decision AS official_decision, od.communication_status, od.published_at FROM exam_results er INNER JOIN participants p ON p.id=er.participant_id LEFT JOIN study_programs sp ON sp.id=p.program_id LEFT JOIN admission_waves aw ON aw.id=p.wave_id LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE er.status="SCORED" ORDER BY er.scored_at DESC'.$limit)->fetchAll();
        return ['summary' => $summary, 'visualizations' => ['decision_distribution' => $decisionDistribution, 'program_performance' => $programPerformance], 'rows' => $rows, 'paginator'=>$paginator];
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
