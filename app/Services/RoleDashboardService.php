<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class RoleDashboardService
{
    public function __construct(private PDO $db) {}

    /** @return array<string,mixed> */
    public function admin(): array
    {
        return [
            'summary' => [
                'participants' => $this->count('SELECT COUNT(*) FROM participants'),
                'eligible' => $this->count('SELECT COUNT(*) FROM participants p INNER JOIN users u ON u.id=p.user_id WHERE p.verification_status="APPROVED" AND p.account_status="ACTIVE" AND u.status="ACTIVE"'),
                'active_questions' => $this->count('SELECT COUNT(*) FROM questions WHERE is_active=1'),
                'scheduled_sessions' => $this->count('SELECT COUNT(*) FROM exam_sessions WHERE status="SCHEDULED"'),
                'pending_verification' => $this->count('SELECT COUNT(*) FROM participants WHERE verification_status IN ("PENDING","NEEDS_CORRECTION")'),
                'active_attempts' => $this->count('SELECT COUNT(*) FROM exam_attempts WHERE status IN ("READY","IN_PROGRESS","PAUSED_REVIEW")'),
                'scored_results' => $this->count('SELECT COUNT(*) FROM exam_results WHERE status="SCORED"'),
                'published_decisions' => $this->count('SELECT COUNT(*) FROM official_decisions WHERE is_current=1 AND communication_status="PUBLISHED"'),
            ],
            'sessions' => $this->adminSessions(),
            'recent_participants' => $this->recentParticipants(),
        ];
    }

    /** @return array<string,mixed> */
    public function committee(): array
    {
        return [
            'summary' => [
                'pending_verification' => $this->count('SELECT COUNT(*) FROM participants WHERE verification_status="PENDING"'),
                'needs_correction' => $this->count('SELECT COUNT(*) FROM participants WHERE verification_status="NEEDS_CORRECTION"'),
                'awaiting_decision' => $this->count('SELECT COUNT(*) FROM exam_results er LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1 WHERE er.status="SCORED" AND od.id IS NULL'),
                'pending_publication' => $this->count('SELECT COUNT(*) FROM official_decisions WHERE is_current=1 AND communication_status="PENDING"'),
                'published_decisions' => $this->count('SELECT COUNT(*) FROM official_decisions WHERE is_current=1 AND communication_status="PUBLISHED"'),
            ],
            'verification_queue' => $this->verificationQueue(),
            'recent_results' => $this->recentResults(),
        ];
    }

    private function count(string $query): int
    {
        return (int) $this->db->query($query)->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    private function adminSessions(): array
    {
        $statement = $this->db->query('SELECT s.id,s.name,s.status,s.starts_at,s.ends_at,w.name AS wave_name,
            COUNT(DISTINCT a.id) AS assigned_count,COUNT(DISTINCT ea.id) AS attempt_count
            FROM exam_sessions s
            LEFT JOIN admission_waves w ON w.id=s.wave_id
            LEFT JOIN exam_assignments a ON a.session_id=s.id AND a.assignment_status<>"CANCELLED"
            LEFT JOIN exam_attempts ea ON ea.session_id=s.id
            GROUP BY s.id,s.name,s.status,s.starts_at,s.ends_at,w.name,s.created_at
            ORDER BY COALESCE(s.starts_at,s.created_at) DESC LIMIT 5');
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['starts_at_wib'] = $this->formatWib($row['starts_at'] ?? null);
            $row['ends_at_wib'] = $this->formatWib($row['ends_at'] ?? null);
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function recentParticipants(): array
    {
        return $this->db->query('SELECT registration_number,full_name,program_choice,wave,verification_status,created_at
            FROM participants ORDER BY created_at DESC LIMIT 5')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function verificationQueue(): array
    {
        return $this->db->query('SELECT id,registration_number,full_name,program_choice,wave,verification_status,updated_at
            FROM participants
            WHERE verification_status IN ("PENDING","NEEDS_CORRECTION")
            ORDER BY FIELD(verification_status,"NEEDS_CORRECTION","PENDING"),updated_at ASC LIMIT 6')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function recentResults(): array
    {
        return $this->db->query('SELECT er.id AS result_id,er.score,er.scored_at,p.registration_number,p.full_name,
            od.decision,od.communication_status
            FROM exam_results er
            INNER JOIN participants p ON p.id=er.participant_id
            LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1
            WHERE er.status="SCORED"
            ORDER BY er.scored_at DESC LIMIT 6')->fetchAll();
    }

    private function formatWib(?string $value): string
    {
        if (!$value) return 'Belum dijadwalkan';
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('d M Y, H:i') . ' WIB';
    }
}
