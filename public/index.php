<?php
declare(strict_types=1);

use App\Infrastructure\Database;
use App\Services\AdminConfigurationService;
use App\Services\AuthService;
use App\Services\CommitteeVerificationService;
use App\Services\ExamSetupService;
use App\Services\ExamAttemptService;
use App\Services\ExamSecurityService;
use App\Services\OfficialDecisionService;
use App\Services\ParticipantDashboardService;
use App\Services\ParticipantExamService;
use App\Services\ParticipantManagementService;
use App\Services\FileImportService;
use App\Services\QuestionImportService;
use App\Services\RetentionService;
use App\Services\ReportingService;
use App\Services\RoleDashboardService;
use App\Services\ValidationException;
use App\Support\Auth;

if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicRoot = realpath(__DIR__);
    $staticFile = $publicRoot === false ? false : realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $requestedPath), DIRECTORY_SEPARATOR));
    if ($staticFile !== false && $publicRoot !== false && str_starts_with($staticFile, rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) && is_file($staticFile)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; media-src 'self'; connect-src 'self'; style-src 'self'; script-src 'self' 'unsafe-inline'");
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$redirectForRole = static fn (string $role): string => $role === 'PARTICIPANT' ? '/dashboard' : ($role === 'ADMIN' ? '/admin/dashboard' : '/panitia/dashboard');

if ($path === '/') {
    $title = 'Penerimaan Mahasiswa Baru'; require __DIR__ . '/views/landing.php'; exit;
}

if (in_array($path, ['/panitia/login', '/admin/login'], true)) { header('Location: /login', true, 301); exit; }

if ($path === '/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $user = (new AuthService(Database::connect($config['db'])))->authenticate($identifier, $password);
        if ($user === null) {
            $_SESSION['login_error'] = 'Email, username, nomor peserta, atau password tidak sesuai.';
            $_SESSION['login_old'] = ['identifier' => $identifier];
            header('Location: /login', true, 303); exit;
        }
        Auth::login($user);
        header('Location: ' . $redirectForRole($user['role']), true, 303); exit;
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $_SESSION['login_error'] = 'Login belum dapat diproses. Silakan coba kembali atau hubungi Panitia PMB.';
        $_SESSION['login_old'] = ['identifier' => $identifier];
        header('Location: /login', true, 303); exit;
    }
}

if ($path === '/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (Auth::user() !== null) { $user = Auth::user(); header('Location: ' . $redirectForRole($user['role']), true, 303); exit; }
    $loginError = $_SESSION['login_error'] ?? null;
    $loginOld = $_SESSION['login_old'] ?? [];
    unset($_SESSION['login_error'], $_SESSION['login_old']);
    $title = 'Masuk ke Sistem Ujian'; require __DIR__ . '/views/login.php'; exit;
}

if ($path === '/logout' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::user();
    if ($user === null) { header('Location: /login', true, 303); exit; }
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }
    try { (new AuthService(Database::connect($config['db'])))->recordLogout($user); } catch (Throwable $exception) { error_log($exception->getMessage()); }
    Auth::logout();
    header('Location: /login?logout=1', true, 303); exit;
}

if ($path === '/dashboard' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireParticipant();
    $participant = (new ParticipantDashboardService(Database::connect($config['db'])))->get($user['participant_id']);
    if ($participant === null) { Auth::logout(); header('Location: /login', true, 303); exit; }
    $title = 'Dashboard Peserta'; require __DIR__ . '/views/dashboard.php'; exit;
}

if ($path === '/profil-peserta' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireParticipant();
    $participant = (new ParticipantDashboardService(Database::connect($config['db'])))->get($user['participant_id']);
    if ($participant === null) { Auth::logout(); header('Location: /login', true, 303); exit; }
    $title = 'Profil Peserta'; require __DIR__ . '/views/participant-profile.php'; exit;
}

if ($path === '/ujian/token' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireParticipant();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        if (!isset($_POST['exam_consent'])) throw new ValidationException(['consent' => 'Persetujuan ujian dan pengawasan wajib diberikan.']);
        (new ExamSecurityService(Database::connect($config['db']), $config))->acceptConsent($user['participant_id'], (string) ($_POST['session_id'] ?? ''));
        $attempt = (new ExamAttemptService(Database::connect($config['db'])))->prepareAttempt($user['participant_id'], (string) ($_POST['session_id'] ?? ''), trim((string) ($_POST['token'] ?? '')));
        header('Location: /ujian/security?attempt=' . urlencode($attempt['id']), true, 303); exit;
    } catch (ValidationException $exception) {
        $_SESSION['exam_error'] = implode(' ', $exception->errors); header('Location: /ujian', true, 303); exit;
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['exam_error'] = 'Sesi ujian belum dapat dibuka.'; header('Location: /ujian', true, 303); exit;
    }
}

if ($path === '/ujian/jawaban' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireParticipant();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => 'Permintaan tidak valid.']); exit; }
    try {
        (new ExamAttemptService(Database::connect($config['db'])))->saveAnswer((string) ($_POST['attempt_id'] ?? ''), $user['participant_id'], (string) ($_POST['question_id'] ?? ''), (string) ($_POST['selected_option'] ?? ''));
        header('Content-Type: application/json'); echo json_encode(['ok' => true, 'saved_at' => gmdate('c')]); exit;
    } catch (ValidationException $exception) {
        http_response_code(422); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => implode(' ', $exception->errors)]); exit;
    }
}

if ($path === '/ujian/kumpulkan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireParticipant();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        (new ExamAttemptService(Database::connect($config['db'])))->submitAttempt((string) ($_POST['attempt_id'] ?? ''), $user['participant_id']);
        header('Location: /ujian/hasil?attempt=' . urlencode((string) $_POST['attempt_id']), true, 303); exit;
    } catch (ValidationException $exception) {
        $_SESSION['exam_error'] = implode(' ', $exception->errors); header('Location: /ujian', true, 303); exit;
    }
}

if ($path === '/ujian/security/photo' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireParticipant();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => 'Permintaan tidak valid.']); exit; }
    try {
        (new ExamSecurityService(Database::connect($config['db']), $config))->storeInitialPhoto($user['participant_id'], (string) ($_POST['attempt_id'] ?? ''), (string) ($_POST['photo'] ?? ''));
        header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit;
    } catch (ValidationException $exception) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => implode(' ', $exception->errors)]); exit; }
}

if ($path === '/ujian/security/event' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireParticipant();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); header('Content-Type: application/json'); echo json_encode(['ok' => false]); exit; }
    try {
        $outcome = (new ExamSecurityService(Database::connect($config['db']), $config))->recordEvent($user['participant_id'], (string) ($_POST['attempt_id'] ?? ''), (string) ($_POST['event_type'] ?? ''));
        header('Content-Type: application/json'); echo json_encode(['ok' => true] + $outcome); exit;
    } catch (ValidationException $exception) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => implode(' ', $exception->errors)]); exit; }
}

if ($path === '/ujian/security' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireParticipant(); $attemptId = (string) ($_GET['attempt'] ?? '');
    $attempt = (new ExamAttemptService(Database::connect($config['db'])))->getAttempt($attemptId, $user['participant_id']);
    if (!in_array($attempt['status'], ['READY', 'IN_PROGRESS'], true)) { $_SESSION['exam_error'] = 'Attempt sedang ditinjau atau sudah selesai.'; header('Location: /ujian', true, 303); exit; }
    $title = 'Pemeriksaan Keamanan'; require __DIR__ . '/views/exam-security.php'; exit;
}

if ($path === '/ujian/ruang' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireParticipant(); $attemptId = (string) ($_GET['attempt'] ?? '');
    $attempt = (new ExamAttemptService(Database::connect($config['db'])))->getAttempt($attemptId, $user['participant_id']);
    if ($attempt['status'] === 'SUBMITTED') { header('Location: /ujian/hasil?attempt=' . urlencode($attemptId), true, 303); exit; }
    if (!(new ExamSecurityService(Database::connect($config['db']), $config))->hasInitialPhoto($user['participant_id'], $attemptId)) { header('Location: /ujian/security?attempt=' . urlencode($attemptId), true, 303); exit; }
    if ($attempt['status'] === 'READY') $attempt = (new ExamAttemptService(Database::connect($config['db'])))->activateAttempt($attemptId, $user['participant_id']);
    if ($attempt['status'] !== 'IN_PROGRESS') { $_SESSION['exam_error'] = 'Attempt sedang ditinjau Panitia.'; header('Location: /ujian', true, 303); exit; }
    $title = 'Ruang Ujian'; require __DIR__ . '/views/exam-room.php'; exit;
}

if ($path === '/ujian/hasil' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireParticipant(); $attemptId = (string) ($_GET['attempt'] ?? '');
    $result = (new ParticipantExamService(Database::connect($config['db'])))->result($attemptId, $user['participant_id']);
    if ($result === null) { http_response_code(404); $title = 'Hasil belum tersedia'; require __DIR__ . '/views/not-found.php'; exit; }
    $officialDecision = (new OfficialDecisionService(Database::connect($config['db'])))->participantDecisionForResult($result['result_id'], $user['participant_id']);
    $title = 'Hasil Ujian'; require __DIR__ . '/views/exam-result.php'; exit;
}

if ($path === '/ujian' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireParticipant();
    $sessions = (new ParticipantExamService(Database::connect($config['db'])))->sessions($user['participant_id']);
    $examError = $_SESSION['exam_error'] ?? null; unset($_SESSION['exam_error']);
    $title = 'Sesi Ujian Saya'; require __DIR__ . '/views/exam-sessions.php'; exit;
}

if ($path === '/panitia/verifikasi/identitas' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireInternal(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{(new CommitteeVerificationService(Database::connect($config['db'])))->decideIdentity((string)$_POST['participant_id'],$user['user_id'],(string)$_POST['decision'],$_POST['checks']??[],trim((string)($_POST['note']??'')));$_SESSION['committee_success']='Keputusan identitas disimpan.';}catch(ValidationException $e){$_SESSION['committee_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['committee_error']='Keputusan belum dapat disimpan.';}
    $redirectQuery = http_build_query(['peserta' => (string) $_POST['participant_id'], 'q' => trim((string) ($_POST['q'] ?? ''))]);
    header('Location: /panitia/verifikasi?' . $redirectQuery,true,303);exit;
}
if ($path === '/panitia/verifikasi/semua' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireInternal(); if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        $count = (new CommitteeVerificationService(Database::connect($config['db'])))->approveAll($user['user_id']);
        $_SESSION['committee_success'] = $count > 0 ? $count . ' peserta berhasil disetujui dan diverifikasi secara massal.' : 'Seluruh peserta yang ada sudah disetujui sebelumnya.';
    } catch (ValidationException $e) { $_SESSION['committee_error'] = implode(' ', $e->errors); }
    catch (Throwable $e) { error_log($e->getMessage()); $_SESSION['committee_error'] = 'Verifikasi massal belum dapat diproses.'; }
    header('Location: /panitia/verifikasi', true, 303); exit;
}
if ($path === '/panitia/verifikasi' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user=Auth::requireInternal();$service=new CommitteeVerificationService(Database::connect($config['db']));$queueData=$service->queue(trim((string)($_GET['q']??'')),$_GET['page']??1);$queue=$queueData['rows'];$queuePaginator=$queueData['paginator'];$participantId=(string)($_GET['peserta']??($queue[0]['id']??''));$workspace=$participantId?$service->workspace($participantId):null;$committeeSuccess=$_SESSION['committee_success']??null;$committeeError=$_SESSION['committee_error']??null;unset($_SESSION['committee_success'],$_SESSION['committee_error']);$title='Verifikasi Peserta';require __DIR__.'/views/committee-verification.php';exit;
}

if ($path === '/panitia/keputusan/tetapkan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireInternal();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        (new OfficialDecisionService(Database::connect($config['db'])))->decide((string) ($_POST['result_id'] ?? ''), $user['user_id'], (string) ($_POST['decision'] ?? ''), trim((string) ($_POST['note'] ?? '')));
        $_SESSION['decision_success'] = 'Keputusan resmi tersimpan sebagai menunggu publikasi.';
    } catch (ValidationException $exception) {
        $_SESSION['decision_error'] = implode(' ', $exception->errors);
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['decision_error'] = 'Keputusan resmi belum dapat disimpan.';
    }
    header('Location: /panitia/keputusan', true, 303); exit;
}

if ($path === '/panitia/keputusan/publikasikan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireInternal();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        (new OfficialDecisionService(Database::connect($config['db'])))->publish((string) ($_POST['decision_id'] ?? ''), $user['user_id']);
        $_SESSION['decision_success'] = 'Keputusan resmi telah dipublikasikan kepada peserta.';
    } catch (ValidationException $exception) {
        $_SESSION['decision_error'] = implode(' ', $exception->errors);
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['decision_error'] = 'Keputusan belum dapat dipublikasikan.';
    }
    header('Location: /panitia/keputusan', true, 303); exit;
}

if ($path === '/admin/dashboard' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();
    $adminDashboard = (new RoleDashboardService(Database::connect($config['db'])))->admin();
    $title = 'Dashboard Admin'; require __DIR__ . '/views/admin-dashboard.php'; exit;
}

if ($path === '/panitia/dashboard' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireInternal();
    if ($user['role'] === 'ADMIN') { header('Location: /admin/dashboard', true, 303); exit; }
    $committeeDashboard = (new RoleDashboardService(Database::connect($config['db'])))->committee();
    $title = 'Dashboard Panitia'; require __DIR__ . '/views/committee-dashboard.php'; exit;
}

if ($path === '/panitia/keputusan/jadwalkan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireInternal();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        $count = (new OfficialDecisionService(Database::connect($config['db'])))->scheduleAllPending((string) ($_POST['publish_at'] ?? ''), $user['user_id']);
        $_SESSION['decision_success'] = $count . ' keputusan pending dijadwalkan untuk dipublikasikan serentak.';
    } catch (ValidationException $exception) {
        $_SESSION['decision_error'] = implode(' ', $exception->errors);
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['decision_error'] = 'Jadwal publikasi belum dapat disimpan.';
    }
    header('Location: /panitia/keputusan?status=SCHEDULED', true, 303); exit;
}

if ($path === '/panitia/keputusan/batalkan-jadwal' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireInternal();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        $count = (new OfficialDecisionService(Database::connect($config['db'])))->cancelAllSchedules($user['user_id']);
        $_SESSION['decision_success'] = $count . ' jadwal publikasi dibatalkan; keputusan kembali ke antrean pending.';
    } catch (ValidationException $exception) {
        $_SESSION['decision_error'] = implode(' ', $exception->errors);
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['decision_error'] = 'Jadwal publikasi belum dapat dibatalkan.';
    }
    header('Location: /panitia/keputusan?status=PENDING', true, 303); exit;
}

if ($path === '/panitia/keputusan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireInternal();
    $decisionFilters = ['search' => trim((string) ($_GET['search'] ?? '')), 'status' => strtoupper(trim((string) ($_GET['status'] ?? 'ALL'))), 'page'=>$_GET['page']??1];
    $decisionQueueData = (new OfficialDecisionService(Database::connect($config['db'])))->queue($user['user_id'], $decisionFilters);$decisionQueue=$decisionQueueData['rows'];$decisionPaginator=$decisionQueueData['paginator'];
    $decisionSuccess = $_SESSION['decision_success'] ?? null; $decisionError = $_SESSION['decision_error'] ?? null;
    unset($_SESSION['decision_success'], $_SESSION['decision_error']);
    $title = 'Keputusan Kelulusan'; require __DIR__ . '/views/official-decisions.php'; exit;
}

if (in_array($path, ['/panitia/laporan/csv', '/admin/laporan/csv'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = $path === '/admin/laporan/csv' ? Auth::requireAdmin() : Auth::requireInternal();
    if ($path === '/panitia/laporan/csv' && $user['role'] === 'ADMIN') { header('Location: /admin/laporan/csv', true, 303); exit; }
    $service = new ReportingService(Database::connect($config['db']));
    $report = $service->report($user['user_id'], 1, true); $service->recordOutput($user['user_id'], 'EXPORT_CSV');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan-hasil-pmb.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Nomor Peserta', 'Nama Peserta', 'Program Studi', 'Gelombang', 'Nilai', 'Benar', 'Salah', 'Kosong', 'Keputusan Resmi', 'Status Komunikasi', 'Waktu Nilai (UTC)']);
    foreach ($report['rows'] as $row) {
        fputcsv($output, array_map(fn ($value) => $service->csvCell((string) ($value ?? '')), [$row['registration_number'], $row['full_name'], $row['program_name'], $row['wave_name'], $row['score'], $row['correct_count'], $row['incorrect_count'], $row['unanswered_count'], $row['official_decision'], $row['communication_status'], $row['scored_at']]));
    }
    fclose($output); exit;
}

if (in_array($path, ['/panitia/laporan/cetak', '/admin/laporan/cetak'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = $path === '/admin/laporan/cetak' ? Auth::requireAdmin() : Auth::requireInternal();
    if ($path === '/panitia/laporan/cetak' && $user['role'] === 'ADMIN') { header('Location: /admin/laporan/cetak', true, 303); exit; }
    $service = new ReportingService(Database::connect($config['db']));
    $report = $service->report($user['user_id'], 1, true); $service->recordOutput($user['user_id'], 'PRINT');
    $reportPortal = str_starts_with($path, '/admin/') ? 'ADMIN' : 'COMMITTEE';
    $printMode = true; $title = 'Cetak Laporan Hasil PMB'; require __DIR__ . '/views/reporting.php'; exit;
}

if (in_array($path, ['/panitia/laporan', '/admin/laporan'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = $path === '/admin/laporan' ? Auth::requireAdmin() : Auth::requireInternal();
    if ($path === '/panitia/laporan' && $user['role'] === 'ADMIN') { header('Location: /admin/laporan', true, 303); exit; }
    $report = (new ReportingService(Database::connect($config['db'])))->report($user['user_id'], $_GET['page']??1);
    $reportPortal = $path === '/admin/laporan' ? 'ADMIN' : 'COMMITTEE';
    $printMode = false; $title = 'Laporan Hasil PMB'; require __DIR__ . '/views/reporting.php'; exit;
}

if ($path === '/admin/pengawasan/lanjutkan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireAdmin();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        (new ExamSecurityService(Database::connect($config['db']), $config))->resumeAttempt((string) ($_POST['attempt_id'] ?? ''), $user['user_id']);
        $_SESSION['monitoring_success'] = 'Attempt dilanjutkan setelah review Admin. Waktu ujian tidak diperpanjang.';
    } catch (ValidationException $exception) {
        $_SESSION['monitoring_error'] = implode(' ', $exception->errors);
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['monitoring_error'] = 'Attempt belum dapat dilanjutkan.';
    }
    header('Location: /admin/pengawasan', true, 303); exit;
}

if ($path === '/admin/foto-proctoring' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireAdmin();
    $photoId = (string) ($_GET['foto'] ?? '');
    $photo = $photoId === '' ? null : (new ExamSecurityService(Database::connect($config['db']), $config))->adminPhoto($photoId, $user['user_id']);
    if ($photo === null) { http_response_code(404); exit('Foto proctoring tidak ditemukan.'); }
    $base = realpath($config['private_storage_path']);
    $pathToPhoto = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $photo['storage_key']));
    $basePrefix = $base === false ? '' : rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($pathToPhoto === false || !str_starts_with($pathToPhoto, $basePrefix) || !is_file($pathToPhoto)) { http_response_code(404); exit('Berkas foto tidak tersedia.'); }
    $mime = in_array($photo['mime_type'], ['image/jpeg', 'image/png'], true) ? $photo['mime_type'] : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($pathToPhoto));
    header('Content-Disposition: inline; filename="foto-proctoring.' . ($mime === 'image/png' ? 'png' : 'jpg') . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($pathToPhoto); exit;
}

if ($path === '/admin/pengawasan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user = Auth::requireAdmin();
    $monitoringData = (new ExamSecurityService(Database::connect($config['db']), $config))->adminMonitoring($user['user_id'],$_GET['page']??1);$monitoringRows=$monitoringData['rows'];$monitoringPaginator=$monitoringData['paginator'];
    $monitoringSuccess = $_SESSION['monitoring_success'] ?? null; $monitoringError = $_SESSION['monitoring_error'] ?? null;
    unset($_SESSION['monitoring_success'], $_SESSION['monitoring_error']);
    $title = 'Pengawasan Ujian'; require __DIR__ . '/views/admin-monitoring.php'; exit;
}

if ($path === '/admin/retensi/tahan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{(new RetentionService(Database::connect($config['db'])))->applyHold((string)($_POST['participant_id']??''),$user['user_id'],trim((string)($_POST['reason']??'')));$_SESSION['retention_success']='Legal hold aktif dan tercatat pada audit log.';}catch(ValidationException $e){$_SESSION['retention_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['retention_error']='Legal hold belum dapat disimpan.';}
    header('Location: /admin/retensi',true,303);exit;
}
if ($path === '/admin/retensi/lepas' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{(new RetentionService(Database::connect($config['db'])))->releaseHold((string)($_POST['hold_id']??''),$user['user_id']);$_SESSION['retention_success']='Legal hold dicabut dan tercatat pada audit log.';}catch(ValidationException $e){$_SESSION['retention_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['retention_error']='Legal hold belum dapat dicabut.';}
    header('Location: /admin/retensi',true,303);exit;
}
if ($path === '/admin/retensi/default' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{(new RetentionService(Database::connect($config['db'])))->updateDefaultRetentionYears($_POST,$user['user_id']);$_SESSION['retention_success']='Masa simpan default berhasil diperbarui dan tercatat pada audit log.';}catch(ValidationException $e){$_SESSION['retention_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['retention_error']='Masa simpan default belum dapat disimpan.';}
    header('Location: /admin/retensi',true,303);exit;
}
if ($path === '/admin/retensi/pengecualian' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{(new RetentionService(Database::connect($config['db'])))->setParticipantRetentionOverride((string)($_POST['participant_id']??''),(string)($_POST['retention_years']??''),trim((string)($_POST['reason']??'')),$user['user_id']);$_SESSION['retention_success']='Pengecualian masa simpan peserta berhasil disimpan dan tercatat pada audit log.';}catch(ValidationException $e){$_SESSION['retention_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['retention_error']='Pengecualian masa simpan belum dapat disimpan.';}
    header('Location: /admin/retensi',true,303);exit;
}
if ($path === '/admin/retensi/pengecualian/hapus' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{(new RetentionService(Database::connect($config['db'])))->clearParticipantRetentionOverride((string)($_POST['participant_id']??''),$user['user_id']);$_SESSION['retention_success']='Pengecualian dihapus. Peserta kembali menggunakan masa simpan default.';}catch(ValidationException $e){$_SESSION['retention_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['retention_error']='Pengecualian masa simpan belum dapat dihapus.';}
    header('Location: /admin/retensi',true,303);exit;
}
if ($path === '/admin/retensi/semua' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try{
        if ((string) ($_POST['confirm_all'] ?? '') !== '1') throw new ValidationException(['Konfirmasi legal hold untuk seluruh peserta wajib dicentang.']);
        $count=(new RetentionService(Database::connect($config['db'])))->applyHoldToAll($user['user_id'],trim((string)($_POST['reason']??'')));
        $_SESSION['retention_success']=$count > 0 ? 'Legal hold aktif untuk '.$count.' peserta dan tercatat pada audit log.' : 'Seluruh peserta sudah memiliki legal hold aktif.';
    }catch(ValidationException $e){$_SESSION['retention_error']=implode(' ',$e->errors);}catch(Throwable $e){error_log($e->getMessage());$_SESSION['retention_error']='Legal hold massal belum dapat disimpan.';}
    header('Location: /admin/retensi',true,303);exit;
}
if ($path === '/admin/retensi' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $user=Auth::requireAdmin();$service=new RetentionService(Database::connect($config['db']));$retentionInventory=$service->inventory($user['user_id']);$retentionHoldData=$service->activeHolds($user['user_id'],$_GET['page']??1);$retentionHolds=$retentionHoldData['rows'];$retentionPaginator=$retentionHoldData['paginator'];$retentionConfiguration=$service->retentionConfiguration($user['user_id']);$retentionParticipants=Database::connect($config['db'])->query('SELECT id,registration_number,full_name FROM participants ORDER BY created_at DESC LIMIT 100')->fetchAll();$retentionSuccess=$_SESSION['retention_success']??null;$retentionError=$_SESSION['retention_error']??null;unset($_SESSION['retention_success'],$_SESSION['retention_error']);$title='Retensi Data';require __DIR__.'/views/admin-retention.php';exit;
}

if (in_array($path, ['/admin/program', '/admin/program/perbarui', '/admin/program/hapus', '/admin/jalur-masuk', '/admin/jalur-masuk/perbarui', '/admin/jalur-masuk/hapus', '/admin/gelombang', '/admin/gelombang/perbarui', '/admin/status', '/admin/kategori', '/admin/soal', '/admin/soal/perbarui', '/admin/soal/nonaktifkan', '/admin/soal/aktifkan', '/admin/sesi', '/admin/sesi/token', '/admin/sesi/jadwal', '/admin/tugaskan', '/admin/tugaskan/semua'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = Auth::requireAdmin();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Permintaan tidak valid.'); }
    try {
        $service = new AdminConfigurationService(Database::connect($config['db']));
        if ($path === '/admin/program') $service->createProgram(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/program/perbarui') $service->updateProgram((string) ($_POST['program_id'] ?? ''), array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/program/hapus') $service->deleteProgram((string) ($_POST['program_id'] ?? ''), $user['user_id']);
        if ($path === '/admin/jalur-masuk') $service->createAdmissionPath(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/jalur-masuk/perbarui') $service->updateAdmissionPath((string) ($_POST['admission_path_id'] ?? ''), array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/jalur-masuk/hapus') $service->deleteAdmissionPath((string) ($_POST['admission_path_id'] ?? ''), $user['user_id']);
        if ($path === '/admin/gelombang') $service->createWave(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/gelombang/perbarui') $service->updateWave((string) ($_POST['wave_id'] ?? ''), array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/status') $service->toggle((string) ($_POST['entity'] ?? ''), (string) ($_POST['id'] ?? ''), $user['user_id']);
        if ($path === '/admin/kategori') (new ExamSetupService(Database::connect($config['db'])))->createCategory(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/soal') (new ExamSetupService(Database::connect($config['db'])))->createQuestion(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/soal/perbarui') (new ExamSetupService(Database::connect($config['db'])))->updateQuestion((string) ($_POST['question_id'] ?? ''), array_map(static fn ($value) => is_string($value) ? trim($value) : '', $_POST), $user['user_id']);
        if ($path === '/admin/soal/nonaktifkan') (new ExamSetupService(Database::connect($config['db'])))->deactivateQuestion((string) ($_POST['question_id'] ?? ''), $user['user_id']);
        if ($path === '/admin/soal/aktifkan') (new ExamSetupService(Database::connect($config['db'])))->activateQuestion((string) ($_POST['question_id'] ?? ''), $user['user_id']);
        if ($path === '/admin/sesi') {
            $tokenRequired = isset($_POST['token_required']);
            $session = (new ExamSetupService(Database::connect($config['db']), $config['token_encryption_key']))->createSession((string) ($_POST['wave_id'] ?? ''), trim((string) ($_POST['name'] ?? '')), trim((string) ($_POST['token'] ?? '')), $user['user_id'], $tokenRequired, (string) ($_POST['passing_grade'] ?? ''));
            $_SESSION['admin_success'] = $tokenRequired ? 'Sesi dibuat dengan proteksi token aktif.' : 'Sesi dibuat tanpa token. Peserta dapat masuk langsung setelah menyetujui aturan ujian.';
        }
        if ($path === '/admin/sesi/token') (new ExamSetupService(Database::connect($config['db']), $config['token_encryption_key']))->configureSessionToken((string) ($_POST['session_id'] ?? ''), isset($_POST['token_required']), trim((string) ($_POST['token'] ?? '')), $user['user_id']);
        if ($path === '/admin/sesi/jadwal') (new ExamSetupService(Database::connect($config['db'])))->scheduleSession((string) ($_POST['session_id'] ?? ''), (string) ($_POST['starts_at'] ?? ''), (string) ($_POST['ends_at'] ?? ''), $user['user_id'], (string) ($_POST['passing_grade'] ?? ''));
        if ($path === '/admin/tugaskan') (new ExamSetupService(Database::connect($config['db'])))->assignParticipant((string) ($_POST['session_id'] ?? ''), (string) ($_POST['participant_id'] ?? ''), $user['user_id']);
        if ($path === '/admin/tugaskan/semua') {
            $admissionPath = trim((string) ($_POST['admission_path'] ?? ''));
            $count = (new ExamSetupService(Database::connect($config['db'])))->assignAllEligible((string) ($_POST['session_id'] ?? ''), $user['user_id'], $admissionPath !== '' ? $admissionPath : null);
            $pathLabel = ($admissionPath !== '' && $admissionPath !== 'ALL') ? ' (' . htmlspecialchars($admissionPath, ENT_QUOTES, 'UTF-8') . ')' : '';
            $_SESSION['admin_success'] = $count > 0 ? $count . ' peserta eligible' . $pathLabel . ' ditugaskan ke sesi.' : 'Tidak ada peserta eligible baru' . $pathLabel . ' yang perlu ditugaskan ke sesi ini.';
        }
        if (!isset($_SESSION['admin_success'])) $_SESSION['admin_success'] = 'Konfigurasi disimpan dan tercatat pada audit log.';
    } catch (ValidationException $exception) {
        $_SESSION['admin_error'] = implode(' ', $exception->errors);
    } catch (Throwable $exception) {
        error_log($exception->getMessage()); $_SESSION['admin_error'] = 'Konfigurasi belum dapat disimpan.';
    }
    header('Location: ' . (in_array($path, ['/admin/kategori', '/admin/soal', '/admin/soal/perbarui', '/admin/soal/nonaktifkan', '/admin/soal/aktifkan'], true) ? '/admin/bank-soal' : (in_array($path, ['/admin/sesi', '/admin/sesi/token', '/admin/sesi/jadwal', '/admin/tugaskan', '/admin/tugaskan/semua'], true) ? '/admin/ujian' : '/admin/konfigurasi')), true, 303); exit;
}

if ($path === '/admin/bank-soal/template' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();
    $templateDb = Database::connect($config['db']);
    $categories = $templateDb->query('SELECT code,name FROM question_categories WHERE is_active=1 ORDER BY name')->fetchAll();
    $rows = QuestionImportService::templateRows();
    if ($categories !== []) $rows[1][0] = (string) $categories[0]['code'];
    $template = (new FileImportService())->xlsxTemplate($rows, 'Bank Soal', [
        'title'=>'Template Impor Bank Soal',
        'summary'=>'Isi satu soal per baris pada sheet Bank Soal. Gunakan sheet Petunjuk sebagai panduan pengisian.',
        'references'=>['category_code'=>['title'=>'Kategori soal aktif','items'=>$categories]],
    ]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="template-bank-soal.xlsx"'); echo $template; exit;
}
if ($path === '/admin/bank-soal/impor' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try {$count=(new QuestionImportService(Database::connect($config['db']),new FileImportService()))->import($_FILES['question_file']??[],$user['user_id']);$_SESSION['admin_success']=$count.' soal diimpor secara atomik.';}catch(ValidationException $exception){$_SESSION['admin_error']=implode(' ',$exception->errors);}catch(Throwable $exception){error_log($exception->getMessage());$_SESSION['admin_error']='Berkas soal belum dapat diproses.';}
    header('Location: /admin/bank-soal',true,303);exit;
}

if ($path === '/admin/peserta/template' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();
    $templateDb = Database::connect($config['db']);
    $programs = $templateDb->query("SELECT code,name FROM study_programs WHERE is_active=1 ORDER BY CASE WHEN code LIKE 'TEST-%' THEN 1 ELSE 0 END, name")->fetchAll();
    $waves = $templateDb->query('SELECT code,name FROM admission_waves WHERE is_active=1 ORDER BY name')->fetchAll();
    $admissionPaths = $templateDb->query('SELECT name AS code, name FROM admission_paths WHERE is_active=1 ORDER BY name')->fetchAll();
    $rows = ParticipantManagementService::templateRows();
    $rows[1][5] = $admissionPaths !== [] ? (string) $admissionPaths[0]['code'] : '';
    if ($programs !== []) $rows[1][6] = (string) $programs[0]['code'];
    if ($waves !== []) $rows[1][7] = (string) $waves[0]['code'];
    $template = (new FileImportService())->xlsxTemplate($rows, 'Peserta', [
        'title'=>'Template Impor Peserta',
        'summary'=>'Isi satu peserta per baris pada sheet Peserta. Gunakan sheet Petunjuk sebagai panduan pengisian.',
        'references'=>[
            'admission_path'=>['title'=>'Jalur masuk','items'=>$admissionPaths],
            'program_code'=>['title'=>'Program studi aktif','items'=>$programs],
            'wave_code'=>['title'=>'Gelombang penerimaan aktif','items'=>$waves],
        ],
    ]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="template-peserta.xlsx"'); echo $template; exit;
}
if ($path === '/admin/peserta' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try {$created=(new ParticipantManagementService(Database::connect($config['db']),new FileImportService(),$config['token_encryption_key']))->create(array_map(static fn($value)=>is_string($value)?trim($value):'',$_POST),$user['user_id']);$_SESSION['participant_success']='Peserta dibuat. Nomor peserta: '.$created['number'].'. Password awal: '.$created['password'].'. Password dapat dilihat kembali oleh Admin dari daftar peserta.';}catch(ValidationException $exception){$_SESSION['participant_error']=implode(' ',$exception->errors);}catch(Throwable $exception){error_log($exception->getMessage());$_SESSION['participant_error']='Peserta belum dapat dibuat.';}
    header('Location: /admin/peserta',true,303);exit;
}
if ($path === '/admin/peserta/impor' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try {$result=(new ParticipantManagementService(Database::connect($config['db']),new FileImportService(),$config['token_encryption_key']))->import($_FILES['participant_file']??[],$user['user_id']);$_SESSION['participant_success']=$result['created'].' peserta dibuat.';$_SESSION['participant_import']=$result;}catch(ValidationException $exception){$_SESSION['participant_error']=implode(' ',$exception->errors);}catch(Throwable $exception){error_log($exception->getMessage());$_SESSION['participant_error']='Berkas peserta belum dapat diproses.';}
    header('Location: /admin/peserta',true,303);exit;
}
if ($path === '/admin/peserta/password' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user=Auth::requireAdmin(); if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Permintaan tidak valid.');}
    try {$_SESSION['participant_credential']=(new ParticipantManagementService(Database::connect($config['db']),new FileImportService(),$config['token_encryption_key']))->passwordForAdmin((string)($_POST['participant_id']??''),$user['user_id']);}
    catch(ValidationException $exception){$_SESSION['participant_error']=implode(' ',$exception->errors);}catch(Throwable $exception){error_log($exception->getMessage());$_SESSION['participant_error']='Password peserta belum dapat ditampilkan.';}
    header('Location: /admin/peserta',true,303);exit;
}
if ($path === '/admin/peserta' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();$participantFilters=['search'=>trim((string)($_GET['search']??'')),'status'=>strtoupper(trim((string)($_GET['status']??'ALL'))),'admission_path'=>trim((string)($_GET['admission_path']??'')),'page'=>$_GET['page']??1];$participantData=(new ParticipantManagementService(Database::connect($config['db']),new FileImportService(),$config['token_encryption_key']))->data($participantFilters);$participantSuccess=$_SESSION['participant_success']??null;$participantError=$_SESSION['participant_error']??null;$participantImport=$_SESSION['participant_import']??null;$participantCredential=$_SESSION['participant_credential']??null;if($participantCredential!==null)header('Cache-Control: no-store, private');unset($_SESSION['participant_success'],$_SESSION['participant_error'],$_SESSION['participant_import'],$_SESSION['participant_credential']);$title='Kelola Peserta';require __DIR__.'/views/admin-participants.php';exit;
}

if ($path === '/admin/konfigurasi' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();
    $catalog = (new AdminConfigurationService(Database::connect($config['db'])))->adminCatalog();
    $adminSuccess = $_SESSION['admin_success'] ?? null; $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_success'], $_SESSION['admin_error']);
    $title = 'Konfigurasi Admin'; require __DIR__ . '/views/admin-configuration.php'; exit;
}

if ($path === '/admin/ujian' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();
    $examData = (new ExamSetupService(Database::connect($config['db']), $config['token_encryption_key']))->adminExamData();
    $adminSuccess = $_SESSION['admin_success'] ?? null; $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_success'], $_SESSION['admin_error']);
    $title = 'Operasional Ujian'; require __DIR__ . '/views/admin-exam.php'; exit;
}

if ($path === '/admin/bank-soal' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireAdmin();
    $questionBankFilters = [
        'search' => trim((string) ($_GET['search'] ?? '')),
        'category_id' => trim((string) ($_GET['category_id'] ?? '')),
        'status' => strtoupper(trim((string) ($_GET['status'] ?? 'ACTIVE'))),
        'page' => $_GET['page'] ?? 1,
    ];
    $questionBank = (new ExamSetupService(Database::connect($config['db'])))->questionBankData($questionBankFilters);
    $adminSuccess = $_SESSION['admin_success'] ?? null; $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_success'], $_SESSION['admin_error']);
    $title = 'Daftar Bank Soal'; require __DIR__ . '/views/admin-question-bank.php'; exit;
}

http_response_code(404);
$title = 'Halaman tidak ditemukan'; require __DIR__ . '/views/not-found.php';
