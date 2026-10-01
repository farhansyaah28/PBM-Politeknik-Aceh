<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class ParticipantDashboardService
{
    public function __construct(private PDO $db) {}

    /** @return array<string,mixed>|null */
    public function get(string $participantId): ?array
    {
        $statement = $this->db->prepare('SELECT p.id,p.registration_number,p.full_name,p.email,p.phone_number,p.school_name,p.graduation_year,p.admission_path,p.program_choice,p.wave,p.account_status,p.verification_status,
            u.status AS user_status,
            (SELECT decision FROM verification_decisions WHERE participant_id=p.id ORDER BY created_at DESC LIMIT 1) AS identity_decision,
            (SELECT note FROM verification_decisions WHERE participant_id=p.id ORDER BY created_at DESC LIMIT 1) AS identity_note,
            (SELECT created_at FROM verification_decisions WHERE participant_id=p.id ORDER BY created_at DESC LIMIT 1) AS identity_reviewed_at
            FROM participants p
            INNER JOIN users u ON u.id=p.user_id
            WHERE p.id=:participant_id LIMIT 1');
        $statement->execute(['participant_id' => $participantId]);
        $participant = $statement->fetch();
        if (!$participant || $participant['user_status'] === 'DISABLED') return null;

        $participant['identity_complete'] = $participant['verification_status'] === 'APPROVED';
        $participant['eligible'] = $participant['identity_complete'];
        $participant['identity_reviewed_at_wib'] = $this->formatWib($participant['identity_reviewed_at'] ?? null);

        $participant['exam_sessions'] = $this->examSessions($participantId);
        $participant['all_results'] = array_values(array_filter($participant['exam_sessions'], static fn (array $session): bool => !empty($session['result_id'])));
        $participant['results'] = array_values(array_filter($participant['exam_sessions'], static fn (array $session): bool => !empty($session['result_id']) && (bool) $session['is_visible']));
        $participant['has_hidden_result'] = count($participant['all_results']) > 0 && count($participant['results']) === 0;
        $participant['exam_summary'] = $this->examSummary($participant['exam_sessions']);
        return $participant;
    }

    /** @return list<array<string,mixed>> */
    private function examSessions(string $participantId): array
    {
        $statement = $this->db->prepare('SELECT s.id,s.name,s.starts_at,s.ends_at,s.duration_minutes,s.token_required,s.passing_grade,a.assignment_status,
            ea.id AS attempt_id,ea.status AS attempt_status,er.id AS result_id,er.score,er.passing_grade AS result_passing_grade,er.automatic_decision,er.correct_count,er.incorrect_count,er.unanswered_count,er.is_visible,er.scored_at,
            od.decision AS official_decision,od.communication_status AS decision_communication_status,od.published_at AS decision_published_at
            FROM exam_assignments a
            INNER JOIN exam_sessions s ON s.id=a.session_id
            LEFT JOIN exam_attempts ea ON ea.session_id=s.id AND ea.participant_id=a.participant_id
            LEFT JOIN exam_results er ON er.attempt_id=ea.id
            LEFT JOIN official_decisions od ON od.result_id=er.id AND od.is_current=1
            WHERE a.participant_id=:participant_id AND a.assignment_status<>"CANCELLED"
            ORDER BY COALESCE(er.scored_at,ea.updated_at,a.assigned_at) DESC');
        $statement->execute(['participant_id' => $participantId]);
        $sessions = $statement->fetchAll();
        foreach ($sessions as &$session) {
            $session['starts_at_wib'] = $this->formatWib($session['starts_at']);
            $session['ends_at_wib'] = $this->formatWib($session['ends_at']);
            $session['scored_at_wib'] = $this->formatWib($session['scored_at'] ?? null);
            $session['decision_published_at_wib'] = $this->formatWib($session['decision_published_at'] ?? null);
            $session['is_visible'] = (bool) ($session['is_visible'] ?? false);
        }
        return $sessions;
    }

    /** @param list<array<string,mixed>> $sessions @return array{state:string,title:string,description:string} */
    private function examSummary(array $sessions): array
    {
        foreach ($sessions as $session) {
            if (!empty($session['result_id'])) return ['state' => 'COMPLETED', 'title' => 'Tidak ada tindakan', 'description' => 'Ujian telah dikumpulkan. Buka riwayat sesi bila Anda perlu melihat detail pelaksanaan.'];
        }
        foreach ($sessions as $session) {
            if (($session['attempt_status'] ?? null) === 'PAUSED_REVIEW') return ['state' => 'PAUSED_REVIEW', 'title' => 'Menunggu review Admin', 'description' => 'Attempt dijeda karena catatan pengawasan dan perlu dilanjutkan oleh Admin.'];
            if (($session['attempt_status'] ?? null) === 'IN_PROGRESS') return ['state' => 'IN_PROGRESS', 'title' => 'Ujian sedang berlangsung', 'description' => 'Lanjutkan attempt sebelum waktu server berakhir.'];
            if (($session['attempt_status'] ?? null) === 'READY') return ['state' => 'READY', 'title' => 'Pemeriksaan perangkat belum selesai', 'description' => 'Lanjutkan kamera dan fullscreen untuk membuka ruang soal.'];
        }
        if ($sessions !== []) return ['state' => 'ASSIGNED', 'title' => 'Sesi telah ditugaskan', 'description' => 'Buka sesi ujian dan masukkan token yang disampaikan melalui kanal resmi.'];
        return ['state' => 'UNASSIGNED', 'title' => 'Menunggu penugasan sesi', 'description' => 'Admin belum menempatkan Anda pada sesi ujian.'];
    }

    private function formatWib(?string $value): ?string
    {
        if (!$value) return null;
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('d M Y, H:i') . ' WIB';
    }
}
