<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use PDO;
use RuntimeException;

final class ExamSetupService
{
    public function __construct(private PDO $db, private string $tokenEncryptionKey = '') {}

    /** @param array<string,string> $input */
    public function createCategory(array $input, string $actorId): string
    {
        $code = strtoupper(trim($input['code'] ?? '')); $name = trim($input['name'] ?? '');
        if ($code === '' || $name === '') throw new ValidationException(['category' => 'Kode dan nama kategori soal wajib diisi.']);
        if (!preg_match('/^[A-Z0-9-]{2,24}$/', $code)) throw new ValidationException(['category' => 'Kode kategori hanya boleh berisi huruf kapital, angka, atau tanda hubung.']);
        try {
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO question_categories (id, code, name, is_active, created_at, updated_at) VALUES (:id, :code, :name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $id, 'code' => $code, 'name' => $name]);
            $this->audit($actorId, 'exam.category.created', 'question_category', $id);
            return $id;
        } catch (\PDOException $exception) { throw new ValidationException(['category' => 'Kode atau nama kategori soal sudah digunakan.']); }
    }

    /** @param array<string,string> $input */
    public function createQuestion(array $input, string $actorId): string
    {
        $input = $this->validatedQuestionInput($input);
        $category = $this->db->prepare('SELECT id FROM question_categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $category->execute(['id' => $input['category_id']]);
        if (!$category->fetch()) throw new ValidationException(['category_id' => 'Kategori soal tidak tersedia.']);
        $id = $this->uuid();
        $this->db->prepare('INSERT INTO questions (id, category_id, question_text, option_a, option_b, option_c, option_d, correct_option, is_active, created_by, created_at, updated_at) VALUES (:id, :category_id, :question_text, :option_a, :option_b, :option_c, :option_d, :correct_option, 1, :actor_id, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $id, 'category_id' => $input['category_id'], 'question_text' => trim($input['question_text']), 'option_a' => trim($input['option_a']), 'option_b' => trim($input['option_b']), 'option_c' => trim($input['option_c']), 'option_d' => trim($input['option_d']), 'correct_option' => $input['correct_option'], 'actor_id' => $actorId]);
        $this->audit($actorId, 'exam.question.created', 'question', $id);
        return $id;
    }

    /** @param array{search?:string,category_id?:string,status?:string,page?:mixed} $filters @return array{categories:array<int,array<string,mixed>>,questions:array<int,array<string,mixed>>,paginator:Paginator} */
    public function questionBankData(array $filters): array
    {
        $where = []; $params = [];
        $search = trim($filters['search'] ?? '');
        $categoryId = trim($filters['category_id'] ?? '');
        $status = strtoupper(trim($filters['status'] ?? 'ACTIVE'));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) $status = 'ACTIVE';
        if ($search !== '') { $where[] = 'CONCAT_WS(" ", q.question_text, q.option_a, q.option_b, q.option_c, q.option_d) LIKE :search'; $params['search'] = '%' . $search . '%'; }
        if ($categoryId !== '') { $where[] = 'q.category_id = :category_id'; $params['category_id'] = $categoryId; }
        if ($status !== 'ALL') $where[] = 'q.is_active = ' . ($status === 'ACTIVE' ? '1' : '0');
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count=$this->db->prepare('SELECT COUNT(*) FROM questions q'.$whereSql);$count->execute($params);$paginator=Paginator::fromRequest((int)$count->fetchColumn(),$filters['page']??1);
        $sql = 'SELECT q.id, q.category_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.is_active, q.created_at, q.updated_at, c.code AS category_code, c.name AS category_name FROM questions q INNER JOIN question_categories c ON c.id=q.category_id'.$whereSql.' ORDER BY q.updated_at DESC, q.id DESC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset();
        $statement = $this->db->prepare($sql); $statement->execute($params);
        return [
            'categories' => $this->db->query('SELECT id, code, name FROM question_categories WHERE is_active=1 ORDER BY name')->fetchAll(),
            'questions' => $statement->fetchAll(),
            'paginator' => $paginator,
        ];
    }

    /** @param array<string,string> $input */
    public function updateQuestion(string $questionId, array $input, string $actorId): void
    {
        $input = $this->validatedQuestionInput($input);
        $category = $this->db->prepare('SELECT id FROM question_categories WHERE id=:id AND is_active=1 LIMIT 1');
        $category->execute(['id' => $input['category_id']]);
        if (!$category->fetch()) throw new ValidationException(['category_id' => 'Kategori soal tidak tersedia.']);
        $statement = $this->db->prepare('UPDATE questions SET category_id=:category_id, question_text=:question_text, option_a=:option_a, option_b=:option_b, option_c=:option_c, option_d=:option_d, correct_option=:correct_option, updated_at=UTC_TIMESTAMP() WHERE id=:id AND is_active=1');
        $statement->execute(['id' => $questionId] + $input);
        if ($statement->rowCount() !== 1) throw new ValidationException(['question' => 'Soal aktif tidak ditemukan atau tidak dapat diperbarui.']);
        $this->audit($actorId, 'exam.question.updated', 'question', $questionId);
    }

    public function deactivateQuestion(string $questionId, string $actorId): void
    {
        $statement = $this->db->prepare('UPDATE questions SET is_active=0, updated_at=UTC_TIMESTAMP() WHERE id=:id AND is_active=1');
        $statement->execute(['id' => $questionId]);
        if ($statement->rowCount() !== 1) throw new ValidationException(['question' => 'Soal aktif tidak ditemukan atau sudah dinonaktifkan.']);
        $this->audit($actorId, 'exam.question.deactivated', 'question', $questionId);
    }

    public function activateQuestion(string $questionId, string $actorId): void
    {
        $statement = $this->db->prepare('UPDATE questions SET is_active=1, updated_at=UTC_TIMESTAMP() WHERE id=:id AND is_active=0');
        $statement->execute(['id' => $questionId]);
        if ($statement->rowCount() !== 1) throw new ValidationException(['question' => 'Soal tidak aktif tidak ditemukan atau sudah aktif.']);
        $this->audit($actorId, 'exam.question.activated', 'question', $questionId);
    }

    /** @return array{categories:array<int,array<string,mixed>>,questions:array<int,array<string,mixed>>,questionTotal:int,waves:array<int,array<string,mixed>>,admissionPaths:array<int,array<string,mixed>>,sessions:array<int,array<string,mixed>>,eligibleParticipants:array<int,array<string,mixed>>} */
    public function adminExamData(): array
    {
        $sessions = $this->db->query('SELECT s.id,s.name,s.status,s.starts_at,s.ends_at,s.token_hint,s.token_ciphertext,s.token_required,s.question_count,s.duration_minutes,s.passing_grade,s.security_mode,w.name AS wave_name,(SELECT COUNT(*) FROM exam_assignments a WHERE a.session_id=s.id) AS assignment_count FROM exam_sessions s INNER JOIN admission_waves w ON w.id=s.wave_id ORDER BY s.created_at DESC LIMIT 20')->fetchAll();
        foreach ($sessions as &$session) {
            $session['token_plaintext'] = $this->decryptToken($session['token_ciphertext'] ?? null);
            $session['starts_at_wib_input'] = $this->formatWibInput($session['starts_at'] ?? null);
            $session['ends_at_wib_input'] = $this->formatWibInput($session['ends_at'] ?? null);
            unset($session['token_ciphertext']);
        }

        $eligibleParticipants = $this->db->query('SELECT p.id, p.registration_number, p.full_name, p.wave_id, p.admission_path FROM participants p INNER JOIN users u ON u.id=p.user_id AND u.status="ACTIVE" WHERE p.verification_status="APPROVED" AND p.account_status="ACTIVE" AND NOT EXISTS (SELECT 1 FROM exam_assignments existing_assignment WHERE existing_assignment.participant_id=p.id AND existing_assignment.assignment_status<>"CANCELLED") AND NOT EXISTS (SELECT 1 FROM exam_attempts existing_attempt WHERE existing_attempt.participant_id=p.id) AND NOT EXISTS (SELECT 1 FROM exam_results existing_result WHERE existing_result.participant_id=p.id) ORDER BY p.admission_path, p.full_name')->fetchAll();

        $pathCounts = [];
        foreach ($eligibleParticipants as $p) {
            $pathName = (string) ($p['admission_path'] ?: 'Reguler');
            $pathCounts[$pathName] = ($pathCounts[$pathName] ?? 0) + 1;
        }

        $rawPaths = $this->db->query('SELECT id, name FROM admission_paths WHERE is_active = 1 ORDER BY name')->fetchAll();
        $admissionPaths = [];
        foreach ($rawPaths as $rp) {
            $name = (string) $rp['name'];
            $admissionPaths[] = [
                'id' => $rp['id'],
                'name' => $name,
                'eligible_count' => $pathCounts[$name] ?? 0,
            ];
            unset($pathCounts[$name]);
        }
        foreach ($pathCounts as $name => $count) {
            $admissionPaths[] = [
                'id' => $name,
                'name' => $name,
                'eligible_count' => $count,
            ];
        }

        return [
            'categories' => $this->db->query('SELECT c.id, c.code, c.name, (SELECT COUNT(*) FROM questions q WHERE q.category_id=c.id AND q.is_active=1) AS question_count FROM question_categories c WHERE c.is_active = 1 ORDER BY c.name')->fetchAll(),
            'questions' => $this->db->query('SELECT q.id, q.question_text, q.correct_option, c.name AS category_name FROM questions q INNER JOIN question_categories c ON c.id = q.category_id WHERE q.is_active = 1 ORDER BY q.created_at DESC LIMIT 20')->fetchAll(),
            'questionTotal' => (int) $this->db->query('SELECT COUNT(*) FROM questions WHERE is_active=1')->fetchColumn(),
            'waves' => $this->db->query('SELECT id, name FROM admission_waves WHERE is_active = 1 ORDER BY name')->fetchAll(),
            'admissionPaths' => $admissionPaths,
            'sessions' => $sessions,
            'eligibleParticipants' => $eligibleParticipants,
        ];
    }

    /** @return array{id:string,token:string} */
    public function createSession(string $waveId, string $name, string $token, string $actorId, bool $tokenRequired = true, ?string $passingGrade = null): array
    {
        if (trim($name) === '') throw new ValidationException(['session' => 'Nama sesi wajib diisi.']);
        if ($tokenRequired && strlen($token) < 8) throw new ValidationException(['token' => 'Token sesi minimal 8 karakter saat proteksi token diaktifkan.']);
        if ($passingGrade !== null && (!is_numeric($passingGrade) || (float) $passingGrade < 0 || (float) $passingGrade > 100)) throw new ValidationException(['passing_grade' => 'Batas nilai kelulusan harus antara 0 sampai 100.']);
        $wave = $this->db->prepare('SELECT id, exam_question_count, exam_duration_minutes, passing_grade, security_mode FROM admission_waves WHERE id = :id AND is_active = 1 LIMIT 1');
        $wave->execute(['id' => $waveId]); $config = $wave->fetch();
        if (!$config) throw new ValidationException(['session' => 'Gelombang aktif tidak ditemukan.']);
        $id = $this->uuid();
        $this->db->prepare('INSERT INTO exam_sessions (id,wave_id,name,status,starts_at,ends_at,token_hash,token_ciphertext,token_hint,token_required,question_count,duration_minutes,passing_grade,security_mode,created_by,created_at,updated_at) VALUES (:id,:wave_id,:name,"DRAFT",DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) + INTERVAL :duration MINUTE,:token_hash,:token_ciphertext,:token_hint,:token_required,:question_count,:duration_minutes,:passing_grade,:security_mode,:actor_id,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([
            'id' => $id, 'wave_id' => $waveId, 'name' => trim($name), 'duration' => (int) $config['exam_duration_minutes'],
            'token_hash' => $tokenRequired ? password_hash($token, PASSWORD_DEFAULT) : null, 'token_ciphertext' => $tokenRequired ? $this->encryptToken($token) : null, 'token_hint' => $tokenRequired ? substr($token, -4) : null, 'token_required' => $tokenRequired ? 1 : 0,
            'question_count' => (int) $config['exam_question_count'], 'duration_minutes' => (int) $config['exam_duration_minutes'],
            'passing_grade' => $passingGrade ?? $config['passing_grade'], 'security_mode' => $config['security_mode'], 'actor_id' => $actorId,
        ]);
        $this->audit($actorId, 'exam.session.created', 'exam_session', $id);
        return ['id' => $id, 'token' => $token];
    }

    public function sessionToken(string $sessionId, string $adminId): ?string
    {
        $this->requireAdmin($adminId);
        $statement = $this->db->prepare('SELECT token_ciphertext FROM exam_sessions WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $sessionId]);
        $ciphertext = $statement->fetchColumn();
        if ($ciphertext === false) throw new ValidationException(['token' => 'Sesi ujian tidak ditemukan.']);
        $token = $this->decryptToken(is_string($ciphertext) ? $ciphertext : null);
        if ($token !== null) $this->audit($adminId, 'exam.session.token_revealed', 'exam_session', $sessionId);
        return $token;
    }

    public function rotateSessionToken(string $sessionId, string $token, string $adminId): void
    {
        if (strlen($token) < 8) throw new ValidationException(['token' => 'Token sesi minimal 8 karakter.']);
        $this->requireAdmin($adminId);
        $ciphertext = $this->encryptToken($token);
        if ($ciphertext === null) throw new ValidationException(['token' => 'Kunci enkripsi token belum dikonfigurasi.']);
        $statement = $this->db->prepare('UPDATE exam_sessions SET token_hash=:hash,token_ciphertext=:ciphertext,token_hint=:hint,updated_at=UTC_TIMESTAMP() WHERE id=:id AND status IN ("DRAFT","SCHEDULED")');
        $statement->execute(['hash' => password_hash($token, PASSWORD_DEFAULT), 'ciphertext' => $ciphertext, 'hint' => substr($token, -4), 'id' => $sessionId]);
        if ($statement->rowCount() !== 1) throw new ValidationException(['token' => 'Token sesi tidak dapat diperbarui pada status saat ini.']);
        $this->audit($adminId, 'exam.session.token_rotated', 'exam_session', $sessionId);
    }

    public function configureSessionToken(string $sessionId, bool $required, string $token, string $adminId): void
    {
        $this->requireAdmin($adminId);
        $session = $this->db->prepare('SELECT token_hash FROM exam_sessions WHERE id=:id AND status IN ("DRAFT","SCHEDULED") LIMIT 1');
        $session->execute(['id' => $sessionId]);
        $row = $session->fetch();
        if (!$row) throw new ValidationException(['token' => 'Sesi tidak tersedia untuk mengubah pengaturan token.']);
        if (!$required) {
            $this->db->prepare('UPDATE exam_sessions SET token_required=0,token_hash=NULL,token_ciphertext=NULL,token_hint=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $sessionId]);
            $this->audit($adminId, 'exam.session.token_disabled', 'exam_session', $sessionId);
            return;
        }
        if ($token === '' && !empty($row['token_hash'])) {
            $this->db->prepare('UPDATE exam_sessions SET token_required=1,updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $sessionId]);
            $this->audit($adminId, 'exam.session.token_enabled', 'exam_session', $sessionId);
            return;
        }
        if (strlen($token) < 8) throw new ValidationException(['token' => 'Isi token minimal 8 karakter saat proteksi token diaktifkan.']);
        $ciphertext = $this->encryptToken($token);
        if ($ciphertext === null) throw new ValidationException(['token' => 'Kunci enkripsi token belum dikonfigurasi.']);
        $this->db->prepare('UPDATE exam_sessions SET token_required=1,token_hash=:hash,token_ciphertext=:ciphertext,token_hint=:hint,updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute([
            'hash' => password_hash($token, PASSWORD_DEFAULT), 'ciphertext' => $ciphertext, 'hint' => substr($token, -4), 'id' => $sessionId,
        ]);
        $this->audit($adminId, 'exam.session.token_configured', 'exam_session', $sessionId);
    }

    public function assignParticipant(string $sessionId, string $participantId, string $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $session = $this->db->prepare('SELECT wave_id FROM exam_sessions WHERE id = :id AND status IN ("DRAFT", "SCHEDULED") FOR UPDATE');
            $session->execute(['id' => $sessionId]); $sessionRow = $session->fetch();
            if (!$sessionRow) throw new ValidationException(['assignment' => 'Sesi ujian tidak tersedia untuk penugasan.']);
            $participant = $this->db->prepare('SELECT p.id FROM participants p INNER JOIN users u ON u.id=p.user_id AND u.status="ACTIVE" WHERE p.id = :id AND p.wave_id = :wave_id AND p.account_status="ACTIVE" AND p.verification_status = "APPROVED" FOR UPDATE');
            $participant->execute(['id' => $participantId, 'wave_id' => $sessionRow['wave_id']]);
            if (!$participant->fetch()) throw new ValidationException(['assignment' => 'Peserta belum eligible untuk ditugaskan ke sesi ujian.']);
            $result = $this->db->prepare('SELECT id FROM exam_results WHERE participant_id=:participant_id LIMIT 1');
            $result->execute(['participant_id' => $participantId]);
            if ($result->fetchColumn()) throw new ValidationException(['assignment' => 'Peserta sudah menyelesaikan ujian dan memiliki satu hasil final.']);
            $attempt = $this->db->prepare('SELECT id FROM exam_attempts WHERE participant_id=:participant_id LIMIT 1');
            $attempt->execute(['participant_id' => $participantId]);
            if ($attempt->fetchColumn()) throw new ValidationException(['assignment' => 'Peserta sudah memiliki attempt ujian dan tidak dapat ditugaskan ke sesi lain.']);
            $assigned = $this->db->prepare('SELECT id FROM exam_assignments WHERE participant_id=:participant_id AND assignment_status<>"CANCELLED" LIMIT 1');
            $assigned->execute(['participant_id' => $participantId]);
            if ($assigned->fetchColumn()) throw new ValidationException(['assignment' => 'Peserta sudah ditugaskan pada satu sesi ujian aktif.']);
            $assignmentId = $this->uuid();
            $this->db->prepare('INSERT INTO exam_assignments (id, session_id, participant_id, assignment_status, assigned_by, assigned_at, created_at, updated_at) VALUES (:id, :session_id, :participant_id, "ASSIGNED", :actor_id, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $assignmentId, 'session_id' => $sessionId, 'participant_id' => $participantId, 'actor_id' => $actorId]);
            $this->audit($actorId, 'exam.assignment.created', 'exam_assignment', $assignmentId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($exception instanceof ValidationException) throw $exception;
            if ($exception instanceof \PDOException && $exception->getCode() === '23000') throw new ValidationException(['assignment' => 'Peserta sudah ditugaskan pada sesi ini.']);
            throw $exception;
        }
    }

    public function assignAllEligible(string $sessionId, string $actorId, ?string $admissionPath = null): int
    {
        $this->db->beginTransaction();
        try {
            $session = $this->db->prepare('SELECT wave_id FROM exam_sessions WHERE id=:id AND status IN ("DRAFT", "SCHEDULED") FOR UPDATE');
            $session->execute(['id' => $sessionId]); $sessionRow = $session->fetch();
            if (!$sessionRow) throw new ValidationException(['assignment' => 'Sesi ujian tidak tersedia untuk penugasan.']);
            
            $params = ['wave_id' => $sessionRow['wave_id']];
            $pathFilter = '';
            if ($admissionPath !== null && trim($admissionPath) !== '' && trim($admissionPath) !== 'ALL') {
                $pathFilter = ' AND p.admission_path = :admission_path';
                $params['admission_path'] = trim($admissionPath);
            }

            $eligible = $this->db->prepare('SELECT p.id FROM participants p INNER JOIN users u ON u.id=p.user_id AND u.status="ACTIVE" WHERE p.wave_id=:wave_id AND p.account_status="ACTIVE" AND p.verification_status="APPROVED"' . $pathFilter . ' AND NOT EXISTS (SELECT 1 FROM exam_assignments existing_assignment WHERE existing_assignment.participant_id=p.id AND existing_assignment.assignment_status<>"CANCELLED") AND NOT EXISTS (SELECT 1 FROM exam_attempts existing_attempt WHERE existing_attempt.participant_id=p.id) AND NOT EXISTS (SELECT 1 FROM exam_results existing_result WHERE existing_result.participant_id=p.id) FOR UPDATE');
            $eligible->execute($params);
            $assignment = $this->db->prepare('INSERT INTO exam_assignments (id, session_id, participant_id, assignment_status, assigned_by, assigned_at, created_at, updated_at) VALUES (:id, :session_id, :participant_id, "ASSIGNED", :actor_id, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())');
            $assigned = 0;
            foreach ($eligible->fetchAll(PDO::FETCH_COLUMN) as $participantId) {
                $assignmentId = $this->uuid();
                $assignment->execute(['id' => $assignmentId, 'session_id' => $sessionId, 'participant_id' => $participantId, 'actor_id' => $actorId]);
                $this->audit($actorId, 'exam.assignment.created', 'exam_assignment', $assignmentId);
                $assigned++;
            }
            $this->audit($actorId, 'exam.assignment.bulk_created', 'exam_session', $sessionId);
            $this->db->commit();
            return $assigned;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function scheduleSession(string $sessionId, string $startsAtWib, string $endsAtWib, string $actorId, ?string $passingGrade = null): void
    {
        $timezone = new \DateTimeZone('Asia/Jakarta');
        $start = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startsAtWib, $timezone);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endsAtWib, $timezone);
        if (!$start || !$end || $start->format('Y-m-d\TH:i') !== $startsAtWib || $end->format('Y-m-d\TH:i') !== $endsAtWib || $end <= $start) throw new ValidationException(['session' => 'Jadwal mulai dan selesai sesi tidak valid.']);
        if ($passingGrade !== null && (!is_numeric($passingGrade) || (float) $passingGrade < 0 || (float) $passingGrade > 100)) throw new ValidationException(['passing_grade' => 'Batas nilai kelulusan harus antara 0 sampai 100.']);
        $statement = $this->db->prepare('UPDATE exam_sessions SET status="SCHEDULED", starts_at=:starts_at, ends_at=:ends_at, passing_grade=COALESCE(:passing_grade,passing_grade), updated_at=UTC_TIMESTAMP() WHERE id=:id AND status IN ("DRAFT", "SCHEDULED")');
        $statement->execute(['id' => $sessionId, 'starts_at' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'ends_at' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'passing_grade' => $passingGrade]);
        if ($statement->rowCount() !== 1) throw new ValidationException(['session' => 'Sesi tidak tersedia untuk dijadwalkan.']);
        $this->audit($actorId, 'exam.session.scheduled', 'exam_session', $sessionId);
    }

    /** @return array{id:string,name:string,status:string,starts_at:string,ends_at:string,duration_minutes:int,security_mode:string} */
    public function validateAssignedToken(string $participantId, string $sessionId, string $token): array
    {
        $statement = $this->db->prepare('SELECT s.id, s.name, s.status, s.starts_at, s.ends_at, s.duration_minutes, s.security_mode, s.token_required, s.token_hash FROM exam_sessions s INNER JOIN exam_assignments a ON a.session_id = s.id WHERE s.id = :session_id AND a.participant_id = :participant_id AND a.assignment_status = "ASSIGNED" AND s.status IN ("DRAFT", "SCHEDULED") LIMIT 1');
        $statement->execute(['session_id' => $sessionId, 'participant_id' => $participantId]);
        $session = $statement->fetch();
        if (!$session || ((bool) $session['token_required'] && (!is_string($session['token_hash']) || !password_verify($token, $session['token_hash'])))) throw new ValidationException(['token' => 'Token sesi tidak valid atau peserta tidak ditugaskan pada sesi ini.']);
        unset($session['token_hash']);
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "PARTICIPANT", NULL, :participant_id, "exam.token.validated", "exam_session", :session_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'participant_id' => $participantId, 'session_id' => $sessionId, 'request_id' => bin2hex(random_bytes(12))]);
        return $session;
    }

    private function audit(string $actorId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "ADMIN", :actor_id, NULL, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'actor_id' => $actorId, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function requireAdmin(string $adminId): void
    {
        $statement = $this->db->prepare('SELECT id FROM users WHERE id=:id AND role="ADMIN" AND status="ACTIVE" LIMIT 1');
        $statement->execute(['id' => $adminId]);
        if (!$statement->fetch()) throw new ValidationException(['token' => 'Token sesi hanya dapat dibuka oleh Admin aktif.']);
    }

    private function formatWibInput(?string $value): string
    {
        if (!$value) return '';
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Asia/Jakarta'))
            ->format('Y-m-d\TH:i');
    }

    private function encryptToken(string $token): ?string
    {
        if ($this->tokenEncryptionKey === '' || !function_exists('openssl_encrypt')) return null;
        $iv = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt($token, 'aes-256-gcm', hash('sha256', $this->tokenEncryptionKey, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) throw new RuntimeException('Token sesi belum dapat dienkripsi.');
        return base64_encode($iv . $tag . $ciphertext);
    }

    private function decryptToken(mixed $payload): ?string
    {
        if (!is_string($payload) || $payload === '' || $this->tokenEncryptionKey === '' || !function_exists('openssl_decrypt')) return null;
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < 29) return null;
        $token = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', hash('sha256', $this->tokenEncryptionKey, true), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));
        return $token === false ? null : $token;
    }

    /** @param array<string,string> $input @return array{category_id:string,question_text:string,option_a:string,option_b:string,option_c:string,option_d:string,correct_option:string} */
    private function validatedQuestionInput(array $input): array
    {
        $normalized = [];
        foreach (['category_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'] as $field) $normalized[$field] = trim($input[$field] ?? '');
        $errors = [];
        foreach ($normalized as $field => $value) if ($value === '') $errors[$field] = 'Wajib diisi.';
        $normalized['correct_option'] = strtoupper($normalized['correct_option']);
        if (!in_array($normalized['correct_option'], ['A', 'B', 'C', 'D'], true)) $errors['correct_option'] = 'Kunci jawaban tidak valid.';
        if ($errors !== []) throw new ValidationException($errors);
        return $normalized;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x0f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
