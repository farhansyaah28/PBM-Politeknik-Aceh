<?php
declare(strict_types=1);

use App\Infrastructure\Database;
use App\Services\OfficialDecisionService;

require dirname(__DIR__) . '/app/bootstrap.php';

$count = (new OfficialDecisionService(Database::connect($config['db'])))->publishDue();
fwrite(STDOUT, 'Published scheduled decisions: ' . $count . PHP_EOL);
