<?php
/**
 * Set default term data for all terms for which this school has actually used
 * Schoolbox, back a maximum of 8 years.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel("Set default terms");

$db     = Database::getInstance();
$logger = new Alaress\Lumberjack\Logger();

$insertTermQuery = $db->prepare("
    INSERT INTO `term` (`title`, `code`, `start`, `display_finish`, `archived`)
    VALUES (:title, :code, :start, :finish, FALSE)
");

$firstDateQuery = $db->query("
    SELECT GREATEST(
        NOW() - INTERVAL 8 YEAR,
        LEAST(
            (SELECT COALESCE(MIN(open_date), NOW()) FROM submission_box WHERE deleted IS FALSE),
            (SELECT COALESCE(MIN(start), NOW()) FROM assess WHERE deleted_at IS NULL)
        )
    )
");

$lastYear  = intval(date('Y'));
$firstYear = intval(date('Y', strtotime($firstDateQuery->fetchColumn())));

foreach (range($firstYear, $lastYear) as $year) {
    $insertTermQuery->execute([
        'title'  => "Semester 1, $year",
        'code'   => "$year-1",
        'start'  => "$year-01-01",
        'finish' => "$year-06-30",
    ]);
    $insertTermQuery->execute([
        'title'  => "Semester 2, $year",
        'code'   => "$year-2",
        'start'  => "$year-07-01",
        'finish' => "$year-12-31",
    ]);
}
