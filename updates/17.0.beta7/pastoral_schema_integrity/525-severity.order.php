<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * `lu_pastoral_severity.order` is a required field in the code, but currently nullable in the schema.
 *
 * This script ensures each severity has an order so we can then make this field non-null.
 */
use Psr\Log\LoggerInterface;
use Monolog\Logger;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Ensure `lu_pastoral_severity`.`order` is set', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);


/** @noinspection PhpUnusedParameterInspection */
$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {

    $db             = Database::getInstance();
    $countSuccess   = 0;
    $countFailure   = 0;

    $log->info('Searching for pastoral severities with a NULL `order`...');
    $selectSeverityWithoutOrder = $db->prepare('
        SELECT `id`, `name`
        FROM lu_pastoral_severity
        WHERE `order` IS NULL
        ORDER BY id;
    ');
    $selectSeverityWithoutOrder->execute();

    if (!$selectSeverityWithoutOrder->rowCount()) {
        $log->info('No pastoral severity records found without order. OK.');
    } else {
        $log->info(sprintf('%d pastoral severity records found without order.', $selectSeverityWithoutOrder->rowCount()));

        $severitiesWithoutOrder = $selectSeverityWithoutOrder->fetchAll(PDO::FETCH_ASSOC);
        foreach ($severitiesWithoutOrder as $row){
            $log->debug(sprintf("id: '%d', Name: '%s'", $row['id'], $row['name']));
        }

        $log->info('Fetching current max `order`...');
        $selectMaxOrder = $db->prepare('
            SELECT MAX(`order`)
            FROM lu_pastoral_severity
            WHERE `order` IS NOT NULL;
        ');
        $maxCurrentOrder = $selectMaxOrder->fetchColumn();
        $log->info(sprintf('Max current order: %d', $maxCurrentOrder));

        $updateSeverityOrder = $db->prepare('
            UPDATE lu_pastoral_severity
            SET `order` = :order
            WHERE id = :id;
        ');

        foreach($severitiesWithoutOrder as $row){
            try {
                $updateSeverityOrder->execute([
                    'order' => ++$maxCurrentOrder,
                    'id'    => $row['id'],
                ]);
                $log->info(sprintf('Severity (id: %d) `order` successfully set to %d', $row['id'], $maxCurrentOrder));
                $countSuccess++;
            } catch (PDOException $e) {
                $log->error(sprintf('Failed to set Severity (id: %d) `order` %d', $row['id'], $maxCurrentOrder), [$e]);
                $log->error($e->getMessage());
                $countFailure++;
            }
        }
    }

    $level   = $countFailure ? Logger::ERROR : Logger::INFO;
    $message = sprintf('%d severity.order successfully updated, %d failures', $countSuccess, $countFailure);
    $log->log($level, $message);

    exit($countFailure ? 1 : 0);
});

