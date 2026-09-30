<?php
declare(strict_types=1);

use App\Infrastructure\Database;
use App\Services\ExamAttemptService;

require dirname(__DIR__) . '/app/bootstrap.php';

$processed = (new ExamAttemptService(Database::connect($config['db'])))->submitExpiredAttempts();
fwrite(STDOUT, "AUTO_SUBMIT_PROCESSED={$processed}\n");
