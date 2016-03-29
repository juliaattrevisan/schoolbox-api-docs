<?php
/**
 * Set actual finish date for all terms.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel("Set default terms");

$db                      = Database::getInstance();
$updateTermQuery         = $db->prepare("UPDATE `term` SET `actual_finish` = :actual_finish WHERE `id` = :id");
$selectActualFinishQuery = $db->query("
    SELECT `current`.`id`, (MIN(`next`.`start`) - INTERVAL 1 DAY) AS `actual_finish`
    FROM `term` `current`
    LEFT JOIN `term` `next` ON `current`.`display_finish` < `next`.`start`
    GROUP BY `current`.`id`;
");
foreach ($selectActualFinishQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $updateTermQuery->execute([
        'id'            => $row['id'],
        'actual_finish' => $row['actual_finish'],
    ]);
}
