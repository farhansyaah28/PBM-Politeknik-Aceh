<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ExamAttemptService
{
    public function __construct(private PDO $db) {}

    /** @return array<string,mixed> */
    public function startAttempt(string $participantId, string $sessionId, string $token): array
    {
        return $this->createAttempt($participantId, $sessionId, $token, true);
    }

    /** @return array<string,mixed> */
    public function prepareAttempt(string $participantId, string $sessionId, string $token): array
    {
        return $this->createAttempt($participantId, $sessionId, $token, false);
    }

    /** @return array<string,mixed> */
    public function activateAttempt(string $attemptId, string $participantId): array
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT ea.id, ea.session_id, ea.status, es.status AS session_status, es.ends_at, es.duration_minutes FROM exam_attempts ea INNER JOIN exam_sessions es ON es.id=ea.session_id WHERE ea.id=:attempt_id AND ea.participant_id=:participant_id FOR UPDATE');
            $statement->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]);
            $attempt = $statement->fetch();
            if (!$attempt || $attempt['status'] === 'SUBMITTED') throw new ValidationException(['exam' => 'Attempt ujian tidak tersedia untuk dimulai.']);
            if ($attempt['status'] === 'IN_PROGRESS') { $this->db->commit(); return $this->getAttempt($attemptId, $participantId); }
            if ($attempt['status'] !== 'READY' || $attempt['session_status'] !== 'SCHEDULED') throw new ValidationException(['exam' => 'Attempt belum siap dimulai.']);
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $endsAt = new DateTimeImmutable($attempt['ends_at'], new DateTimeZone('UTC'));
            if ($now >= $endsAt) throw new ValidationException(['exam' => 'Sesi ujian sudah berakhir.']);
            $durationEnd = $now->modify('+' . (int) $attempt['duration_minutes'] . ' minutes');
            $expiresAt = $durationEnd < $endsAt ? $durationEnd : $endsAt;
            $this->db->prepare('UPDATE exam_attempts SET status="IN_PROGRESS", started_at=UTC_TIMESTAMP(), expires_at=:expires_at, updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $attemptId, 'expires_at' => $expiresAt->format('Y-m-d H:i:s')]);
            $this->db->prepare('UPDATE exam_assignments SET assignment_status="STARTED", updated_at=UTC_TIMESTAMP() WHERE session_id=:session_id AND participant_id=:participant_id')->execute(['session_id' => $attempt['session_id'], 'participant_id' => $participantId]);
            $this->audit($participantId, 'exam.attempt.started', 'exam_attempt', $attemptId);
            $this->db->commit();
            return $this->getAttempt($attemptId, $participantId);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function createAttempt(string $participantId, string $sessionId, string $token, bool $startTimer): array
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT s.id, s.status, s.starts_at, s.ends_at, s.token_required, s.token_hash, s.question_count, s.duration_minutes FROM exam_sessions s INNER JOIN exam_assignments a ON a.session_id = s.id AND a.participant_id = :participant_id AND a.assignment_status IN ("ASSIGNED", "STARTED") INNER JOIN participants p ON p.id = a.participant_id INNER JOIN users u ON u.id=p.user_id AND u.status="ACTIVE" WHERE s.id = :session_id AND p.account_status="ACTIVE" AND p.verification_status = "APPROVED" FOR UPDATE');
            $statement->execute(['participant_id' => $participantId, 'session_id' => $sessionId]);
            $session = $statement->fetch();
            if (!$session || ((bool) $session['token_required'] && (!is_string($session['token_hash']) || !password_verify($token, $session['token_hash'])))) throw new ValidationException(['exam' => 'Sesi atau token ujian tidak valid.']);
            $consent = $this->db->prepare('SELECT id FROM exam_session_consents WHERE participant_id=:participant_id AND session_id=:session_id AND consent_version="EXAM-SECURITY-v1" LIMIT 1');
            $consent->execute(['participant_id' => $participantId, 'session_id' => $sessionId]);
            if (!$consent->fetch()) throw new ValidationException(['exam' => 'Persetujuan ujian dan keamanan wajib diberikan sebelum memulai attempt.']);
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $startsAt = new DateTimeImmutable($session['starts_at'], new DateTimeZone('UTC'));
            $endsAt = new DateTimeImmutable($session['ends_at'], new DateTimeZone('UTC'));
            if ($session['status'] !== 'SCHEDULED' || $now < $startsAt || $now >= $endsAt) throw new ValidationException(['exam' => 'Sesi ujian belum dibuka atau sudah berakhir.']);
            $existing = $this->db->prepare('SELECT id, session_id, status FROM exam_attempts WHERE participant_id = :participant_id ORDER BY created_at ASC LIMIT 1 FOR UPDATE');
            $existing->execute(['participant_id' => $participantId]);
            $attempt = $existing->fetch();
            if ($attempt) {
                if ($attempt['session_id'] !== $sessionId) throw new ValidationException(['exam' => 'Peserta sudah memiliki attempt pada sesi lain dan hanya dapat mengikuti ujian satu kali.']);
                if ($attempt['status'] === 'SUBMITTED') throw new ValidationException(['exam' => 'Peserta sudah menyelesaikan ujian dan hanya dapat mengikuti ujian satu kali.']);
                if ($startTimer && $attempt['status'] === 'READY') { $this->db->commit(); return $this->activateAttempt($attempt['id'], $participantId); }
                $this->db->commit();
                return $this->getAttempt($attempt['id'], $participantId);
            }
            $questions = $this->selectAttemptQuestions((int) $session['question_count']);
            $attemptId = $this->uuid();
            $durationEnd = $now->modify('+' . (int) $session['duration_minutes'] . ' minutes');
            $expiresAt = $startTimer && $durationEnd < $endsAt ? $durationEnd : $endsAt;
            $status = $startTimer ? 'IN_PROGRESS' : 'READY';
            $this->db->prepare('INSERT INTO exam_attempts (id, session_id, participant_id, attempt_no, status, started_at, expires_at, created_at, updated_at) VALUES (:id, :session_id, :participant_id, 1, :status, UTC_TIMESTAMP(), :expires_at, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $attemptId, 'session_id' => $sessionId, 'participant_id' => $participantId, 'status' => $status, 'expires_at' => $expiresAt->format('Y-m-d H:i:s')]);
            $insertQuestion = $this->db->prepare('INSERT INTO attempt_questions (id, attempt_id, question_id, position, question_text, options_json, option_order, correct_option, created_at) VALUES (:id, :attempt_id, :question_id, :position, :question_text, :options_json, :option_order, :correct_option, UTC_TIMESTAMP())');
            foreach ($questions as $position => $question) {
                $sourceOptions = ['A' => $question['option_a'], 'B' => $question['option_b'], 'C' => $question['option_c'], 'D' => $question['option_d']];
                $sourceOrder = array_keys($sourceOptions); shuffle($sourceOrder);
                $displayOrder = ['A', 'B', 'C', 'D']; $displayOptions = []; $correctOption = null;
                foreach ($displayOrder as $index => $displayOption) {
                    $sourceOption = $sourceOrder[$index];
                    $displayOptions[$displayOption] = $sourceOptions[$sourceOption];
                    if ($sourceOption === $question['correct_option']) $correctOption = $displayOption;
                }
                $insertQuestion->execute(['id' => $this->uuid(), 'attempt_id' => $attemptId, 'question_id' => $question['id'], 'position' => $position + 1, 'question_text' => $question['question_text'], 'options_json' => json_encode($displayOptions, JSON_THROW_ON_ERROR), 'option_order' => json_encode($displayOrder, JSON_THROW_ON_ERROR), 'correct_option' => $correctOption]);
            }
            if ($startTimer) $this->db->prepare('UPDATE exam_assignments SET assignment_status = "STARTED", updated_at = UTC_TIMESTAMP() WHERE session_id = :session_id AND participant_id = :participant_id')->execute(['session_id' => $sessionId, 'participant_id' => $participantId]);
            $this->audit($participantId, $startTimer ? 'exam.attempt.started' : 'exam.attempt.prepared', 'exam_attempt', $attemptId);
            $this->db->commit();
            return $this->getAttempt($attemptId, $participantId);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function getAttempt(string $attemptId, string $participantId): array
    {
        $attempt = $this->db->prepare('SELECT id, status, started_at, expires_at FROM exam_attempts WHERE id = :id AND participant_id = :participant_id LIMIT 1');
        $attempt->execute(['id' => $attemptId, 'participant_id' => $participantId]);
        $result = $attempt->fetch();
        if (!$result) throw new ValidationException(['exam' => 'Attempt ujian tidak ditemukan.']);
        $questions = $this->db->prepare('SELECT aq.id, aq.position, aq.question_text, aq.options_json, aq.option_order, ans.selected_option FROM attempt_questions aq LEFT JOIN exam_answers ans ON ans.attempt_question_id = aq.id AND ans.attempt_id = aq.attempt_id WHERE aq.attempt_id = :attempt_id ORDER BY aq.position');
        $questions->execute(['attempt_id' => $attemptId]);
        $result['questions'] = array_map(static function (array $question): array { $question['options'] = json_decode($question['options_json'], true, 512, JSON_THROW_ON_ERROR); $question['order'] = json_decode($question['option_order'], true, 512, JSON_THROW_ON_ERROR); unset($question['options_json'], $question['option_order']); return $question; }, $questions->fetchAll());
        return $result;
    }

    public function saveAnswer(string $attemptId, string $participantId, string $attemptQuestionId, string $selectedOption): void
    {
        if (!in_array($selectedOption, ['A', 'B', 'C', 'D'], true)) throw new ValidationException(['answer' => 'Pilihan jawaban tidak valid.']);
        $this->db->beginTransaction();
        try {
            $attempt = $this->db->prepare('SELECT id FROM exam_attempts WHERE id = :attempt_id AND participant_id = :participant_id AND status = "IN_PROGRESS" AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
            $attempt->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]);
            if (!$attempt->fetch()) throw new ValidationException(['answer' => 'Attempt tidak aktif atau waktu ujian telah berakhir.']);
            $question = $this->db->prepare('SELECT id FROM attempt_questions WHERE id = :question_id AND attempt_id = :attempt_id LIMIT 1');
            $question->execute(['question_id' => $attemptQuestionId, 'attempt_id' => $attemptId]);
            if (!$question->fetch()) throw new ValidationException(['answer' => 'Soal tidak termasuk dalam attempt ini.']);
            $this->db->prepare('INSERT INTO exam_answers (id, attempt_id, attempt_question_id, selected_option, saved_at, created_at, updated_at) VALUES (:id, :attempt_id, :question_id, :selected_option, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), saved_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()')->execute(['id' => $this->uuid(), 'attempt_id' => $attemptId, 'question_id' => $attemptQuestionId, 'selected_option' => $selectedOption]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array{id:string,status:string,score:float,correct_count:int,incorrect_count:int,unanswered_count:int} */
    public function submitAttempt(string $attemptId, string $participantId): array
    {
        return $this->finalizeAttempt($attemptId, $participantId, false);
    }

    /** @return array{id:string,status:string,score:float,correct_count:int,incorrect_count:int,unanswered_count:int} */
    private function finalizeAttempt(string $attemptId, string $participantId, bool $allowPausedReview): array
    {
        $this->db->beginTransaction();
        try {
            $attempt = $this->db->prepare('SELECT ea.id, ea.session_id, ea.status, es.passing_grade FROM exam_attempts ea INNER JOIN exam_sessions es ON es.id=ea.session_id WHERE ea.id = :attempt_id AND ea.participant_id = :participant_id FOR UPDATE');
            $attempt->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]); $attemptRow = $attempt->fetch();
            if (!$attemptRow) throw new ValidationException(['exam' => 'Attempt ujian tidak ditemukan.']);
            $existing = $this->result($attemptId, $participantId);
            if ($existing) { $this->db->commit(); return $existing; }
            $otherResult = $this->db->prepare('SELECT id FROM exam_results WHERE participant_id=:participant_id AND attempt_id<>:attempt_id LIMIT 1 FOR UPDATE');
            $otherResult->execute(['participant_id' => $participantId, 'attempt_id' => $attemptId]);
            if ($otherResult->fetchColumn()) throw new ValidationException(['exam' => 'Peserta sudah memiliki satu hasil ujian final.']);
            if ($attemptRow['status'] !== 'IN_PROGRESS' && !($allowPausedReview && $attemptRow['status'] === 'PAUSED_REVIEW')) throw new ValidationException(['exam' => 'Attempt tidak dapat dikumpulkan pada status saat ini.']);
            $counts = $this->db->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN ans.selected_option = aq.correct_option THEN 1 ELSE 0 END) AS correct_count, SUM(CASE WHEN ans.selected_option IS NOT NULL AND ans.selected_option <> aq.correct_option THEN 1 ELSE 0 END) AS incorrect_count, SUM(CASE WHEN ans.selected_option IS NULL THEN 1 ELSE 0 END) AS unanswered_count FROM attempt_questions aq LEFT JOIN exam_answers ans ON ans.attempt_question_id = aq.id AND ans.attempt_id = aq.attempt_id WHERE aq.attempt_id = :attempt_id');
            $counts->execute(['attempt_id' => $attemptId]); $scoreData = $counts->fetch();
            $total = (int) $scoreData['total'];
            if ($total < 1) throw new ValidationException(['exam' => 'Attempt tidak memiliki soal untuk dinilai.']);
            $correct = (int) ($scoreData['correct_count'] ?? 0); $incorrect = (int) ($scoreData['incorrect_count'] ?? 0); $unanswered = (int) ($scoreData['unanswered_count'] ?? 0);
            $score = round(($correct / $total) * 100, 2);
            $passingGrade = (float) $attemptRow['passing_grade'];
            $automaticDecision = $score >= $passingGrade ? 'PASSED' : 'NOT_PASSED';
            $resultId = $this->uuid();
            $this->db->prepare('UPDATE exam_attempts SET status="SUBMITTED", submitted_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $attemptId]);
            $this->db->prepare('UPDATE exam_assignments SET assignment_status="SUBMITTED", updated_at=UTC_TIMESTAMP() WHERE session_id=:session_id AND participant_id=:participant_id')->execute(['session_id' => $attemptRow['session_id'], 'participant_id' => $participantId]);
            $this->db->prepare('INSERT INTO exam_results (id, attempt_id, participant_id, session_id, score, passing_grade, automatic_decision, correct_count, incorrect_count, unanswered_count, status, is_visible, scored_at, created_at) VALUES (:id, :attempt_id, :participant_id, :session_id, :score, :passing_grade, :automatic_decision, :correct_count, :incorrect_count, :unanswered_count, "SCORED", 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $resultId, 'attempt_id' => $attemptId, 'participant_id' => $participantId, 'session_id' => $attemptRow['session_id'], 'score' => $score, 'passing_grade' => $passingGrade, 'automatic_decision' => $automaticDecision, 'correct_count' => $correct, 'incorrect_count' => $incorrect, 'unanswered_count' => $unanswered]);
            $this->audit($participantId, 'exam.attempt.submitted', 'exam_attempt', $attemptId);
            $this->db->commit();
            return ['id' => $resultId, 'status' => 'SCORED', 'score' => $score, 'passing_grade' => $passingGrade, 'automatic_decision' => $automaticDecision, 'correct_count' => $correct, 'incorrect_count' => $incorrect, 'unanswered_count' => $unanswered];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function submitExpiredAttempts(): int
    {
        $expired = $this->db->query('SELECT id, participant_id FROM exam_attempts WHERE status IN ("IN_PROGRESS", "PAUSED_REVIEW") AND expires_at <= UTC_TIMESTAMP() ORDER BY expires_at ASC LIMIT 200')->fetchAll();
        $processed = 0;
        foreach ($expired as $attempt) {
            try {
                $this->finalizeAttempt($attempt['id'], $attempt['participant_id'], true);
                $this->auditSystem('exam.attempt.auto_submitted', 'exam_attempt', $attempt['id']);
                $processed++;
            } catch (ValidationException) {
                // Another request may have finalized the same attempt after this worker selected it.
            }
        }
        return $processed;
    }

    /** @return array{id:string,status:string,score:float,correct_count:int,incorrect_count:int,unanswered_count:int}|null */
    private function result(string $attemptId, string $participantId): ?array
    {
        $statement = $this->db->prepare('SELECT id, status, score, passing_grade, automatic_decision, correct_count, incorrect_count, unanswered_count FROM exam_results WHERE attempt_id = :attempt_id AND participant_id = :participant_id LIMIT 1');
        $statement->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]); $result = $statement->fetch();
        if (!$result) return null;
        $result['score'] = (float) $result['score']; $result['passing_grade'] = (float) $result['passing_grade']; $result['correct_count'] = (int) $result['correct_count']; $result['incorrect_count'] = (int) $result['incorrect_count']; $result['unanswered_count'] = (int) $result['unanswered_count'];
        return $result;
    }

    private function audit(string $participantId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "PARTICIPANT", NULL, :participant_id, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'participant_id' => $participantId, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    /** @return array<int,array<string,mixed>> */
    private function selectAttemptQuestions(int $questionCount): array
    {
        $categories = ['VERBAL', 'NUMERIK', 'LOGIKA', 'UMUM'];
        if ($questionCount < count($categories)) {
            $questions = $this->db->query('SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE is_active=1 ORDER BY RAND() LIMIT ' . $questionCount)->fetchAll();
            if (count($questions) < $questionCount) throw new ValidationException(['exam' => 'Bank soal aktif belum mencukupi untuk sesi ini.']);
            return $questions;
        }

        $base = intdiv($questionCount, count($categories));
        $remainder = $questionCount % count($categories);
        $questions = [];
        foreach ($categories as $index => $code) {
            $quota = $base + ($index < $remainder ? 1 : 0);
            $statement = $this->db->prepare('SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option FROM questions q INNER JOIN question_categories c ON c.id=q.category_id WHERE q.is_active=1 AND c.is_active=1 AND c.code=:code ORDER BY RAND() LIMIT ' . $quota);
            $statement->execute(['code' => $code]);
            $categoryQuestions = $statement->fetchAll();
            if (count($categoryQuestions) < $quota) throw new ValidationException(['exam' => 'Bank soal kategori ' . $code . ' belum mencukupi untuk sesi ini.']);
            array_push($questions, ...$categoryQuestions);
        }
        shuffle($questions);
        return $questions;
    }

    private function auditSystem(string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "SYSTEM", NULL, NULL, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x0f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
