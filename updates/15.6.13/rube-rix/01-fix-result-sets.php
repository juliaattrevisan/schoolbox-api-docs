<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Fix Rubric Result Sets', array(
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
));


$kernel->run(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    $db = Database::getInstance();

    $affectedStmt = $db->query("
        SELECT DISTINCT(ru.submission_box_id) box_id, `name`, created 
        FROM rubric_usage ru 
        JOIN submission_box sb ON sb.id = ru.submission_box_id 
        LEFT JOIN rubric_descriptor rd ON ru.submission_box_id = rd.submission_box_id 
        WHERE rd.id IS NULL
    ");

    if ($affectedStmt->rowCount() == 0) {
        $log->info("No submission boxes affected; terminating");
        return;
    }

    $resultSetLookupStmt = $db->prepare("
        SELECT r.label, r.value 
        FROM rubric_result r
        LEFT JOIN submission_box b ON r.resultset_id = b.rubric_result_set
        WHERE b.id = ?
        ORDER BY r.value ASC;
    ");

    $criteraLookupStmt = $db->prepare("
        SELECT c.id
        FROM rubric_usage u
        LEFT JOIN rubric_criterion c ON u.criterion_id = c.id
        WHERE u.submission_box_id = ?
    ");

    $insertLevelsStmt = $db->prepare("
        INSERT INTO rubric_level (box_id, name, sequence) VALUES (
            :boxId, :levelName, :levelSequence
        )
    ");

    $insertDescStmt = $db->prepare("
        INSERT INTO rubric_descriptor (criterion_id, submission_box_id, level_id, value, description) VALUES (
            :critId, :boxId, :levelId, :value, :label
        )
    ");

    while (list($boxId, $boxName, $boxCreated) = $affectedStmt->fetch(PDO::FETCH_NUM)) {

        if ($boxId == 0) {
            $log->error("Can't work with null box; skipping");
            continue;
        }

        // iterate affected boxes to repair
        $log->debug("Working with box $boxId: '$boxName' (created $boxCreated)");

        $resultSetLookupStmt->execute([ $boxId ]);

        if ($resultSetLookupStmt->rowCount() == 0) {
            $log->error("Box references unknown result set; skipping");
            continue;
        }

        $criteraLookupStmt->execute([ $boxId ]);

        if ($criteraLookupStmt->rowCount() == 0) {
            $log->error("No criteria in use; skipping");
            continue;
        }

        // cache this
        $critCache = $criteraLookupStmt->fetchAll(PDO::FETCH_COLUMN);

        $levelCache = [];

        // convert resultset to levels
        while (list($label, $value) = $resultSetLookupStmt->fetch(PDO::FETCH_NUM)) {
            $data = [
                'boxId'         => $boxId,
                'levelName'     => $label,
                'levelSequence' => $value
            ];

            $log->info(sprintf("INSERT level [ %d, %s, %d ]", $boxId, $label, $value));
            $insertLevelsStmt->execute($data);

            $levelCache[] = [ $label, $value, $db->lastInsertId() ];
        }

        foreach ($levelCache as list($label, $value, $levelId)) {
            // iterate the levels created, and create descriptors for each criterion

            if ($levelId == 0) {
                $log->error("Can't work with null level - something is wrong with box $boxId; skipping");
                break;
            }

            foreach ($critCache as $critId) {
                $data = [
                    'critId' => $critId,
                    'boxId'  => $boxId,
                    'levelId'=> $levelId,
                    'value'  => $value,
                    'label'  => $label
                ];

                $log->info(sprintf("INSERT descriptor [ %d, %d, %d, %d, %s ]", $critId, $boxId, $levelId, $value, $label));
                $insertDescStmt->execute($data);
            }
        }


    }

});
