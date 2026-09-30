<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ParticipantExamService
{
    public function __construct(private PDO $db) {}

    /** @return array<int,array<string,mixed>> */
    public function sessions(string $participantId): array
    {
        $statement = $this->db->prepare('SELECT s.id,s.name,s.status,s.starts_at,s.ends_at,s.duration_minutes,s.security_mode,s.token_required,s.passing_grade,a.assignment_status,
            ea.id AS attempt_id,ea.status AS attempt_status,er.id AS result_id,er.score,er.is_visible,
            EXISTS(SELECT 1 FROM proctoring_photos pp WHERE pp.attempt_id=ea.id) AS has_photo
            FROM exam_assignments a INNER JOIN exam_sessions s ON s.id=a.session_id
            LEFT JOIN exam_attempts ea ON ea.session_id=s.id AND ea.participant_id=a.participant_id
            LEFT JOIN exam_results er ON er.attempt_id=ea.id
            WHERE a.participant_id=:participant_id AND a.assignment_status<>"CANCELLED" ORDER BY s.starts_at DESC');
        $statement->execute(['participant_id' => $participantId]);
        $sessions = $statement->fetchAll();
        foreach ($sessions as &$session) {
            $session['starts_at_wib'] = $this->formatWib($session['starts_at']);
            $session['ends_at_wib'] = $this->formatWib($session['ends_at']);
        }
        return $sessions;
    }

    /** @return array<string,mixed>|null */
    public function result(string $attemptId, string $participantId): ?array
    {
        $statement = $this->db->prepare('SELECT er.id AS result_id, er.score, er.passing_grade, er.automatic_decision, er.correct_count, er.incorrect_count, er.unanswered_count, er.status, er.scored_at, s.name AS session_name FROM exam_results er INNER JOIN exam_sessions s ON s.id=er.session_id WHERE er.attempt_id=:attempt_id AND er.participant_id=:participant_id AND er.status="SCORED" AND er.is_visible=1 LIMIT 1');
        $statement->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]);
        return $statement->fetch() ?: null;
    }

    private function formatWib(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y, H:i') . ' WIB';
    }
}
