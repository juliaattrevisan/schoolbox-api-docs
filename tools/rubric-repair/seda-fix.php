<?php
/**
 * READ JIRA TICKET SB-12028 <http://jira.alaress.com.au:8080/browse/SB-12028> BEFORE ATTEMPTING TO DO ANYTHING WITH THIS SCRIPT
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;


require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Rubric Data Repair', array(
    // force full verbosity
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
));

$kernel->run(function($di, $loop, $log, $opts) use (&$rerouter) {
    /**
     * @var Di\Container $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    if ($di->get('config')->get('site_location') !== 'seda') {
        $log->info("Rubric data repair not relevant to this instance: terminating.");
        // exit zero because this isn't an error
        exit;
    }

    // report the database name
    $log->notice("Operating on database '" . SQL_db . "'");

    // general debugging config values
    $enforceWidth       = true;  // deployment value: TRUE
    $requiredTableWidth = 6;     // deployment value: 6

    $log->alert("Initialising rubric data repair");

    // retrieve a list of the most-recent-per-box-and-student non-deleted, non-null returns attached to non-deleted boxes that use rubric marking
    $stmt = Database::getInstance()->query("
        SELECT * FROM (
            SELECT r.box_id, r.id return_id, `owner` student_id, norm_mark
            FROM submission_return r
            WHERE r.box_id IN (
                SELECT id FROM submission_box 
                WHERE use_rubric IS TRUE
                AND deleted IS FALSE
                AND task_id IS NULL
            )
            AND is_confirmation_of_submission IS FALSE
            AND deleted IS FALSE
            AND norm_mark IS NOT NULL
            ORDER BY returned DESC
        ) t
        GROUP BY box_id, student_id
    ");

    // find out if any marks are given for this box/student combo
    $testRubricMarkGivenStmt = Database::getInstance()->prepare("
        SELECT COUNT(*)
        FROM rubric_mark
        WHERE student_id = :studentId
        AND submission_box_id = :boxId
    ");

    // determine the width of the target rubric grid
    $tableWidthStmt = Database::getInstance()->prepare("
        SELECT MAX(c) FROM (
          SELECT COUNT(*) c FROM rubric_descriptor d
          WHERE d.submission_box_id = ?
          GROUP BY criterion_id
        ) t GROUP BY c;
    ");

    // get the list of criteria that are actually in use on this rubric
    $criteriaInUseStmt = Database::getInstance()->prepare("
        SELECT DISTINCT criterion_id
        FROM rubric_descriptor d
        WHERE d.submission_box_id = ?
    ");

    // get the descriptor in the column described by the ordinal position :rowNum
    $obtainDescriptorIdStmt = Database::getInstance()->prepare("
        SELECT id
        FROM rubric_descriptor
        WHERE criterion_id = :critId
        AND submission_box_id = :boxId
        ORDER BY `value` ASC
        LIMIT :rowNum, 1
    ");

    // jam data into the db
    $markInsertionStmt = Database::getInstance()->prepare("
        INSERT INTO rubric_mark (student_id, submission_box_id, criterion_id, descriptor_id)
        VALUES (:studentId, :boxId, :critId, :descriptorId)
    ");

    $log->notice("Total rubric returns found: " . $stmt->rowCount());

    $workToBeDone = [];

    while (list($boxId, $returnId, $studentId, $trueMark) = $stmt->fetch(PDO::FETCH_NUM)) {
        // iterate the list of returns and filter out things we don't need to look at

        $testRubricMarkGivenStmt->execute([
            'studentId' => $studentId,
            'boxId'     => $boxId
        ]);

        if ($testRubricMarkGivenStmt->fetchColumn() > 0) {
            // get these out the way
            $log->debug("Check student $studentId, box $boxId... ok!");
        } else {
            // put these in cache to be dealt with
            $workToBeDone[] = [ $boxId, $returnId, $studentId, $trueMark ];
        }
    }

    if (!count($workToBeDone)) {
        // don't do work if you don't gotta
        $log->notice("All rubrics present and accounted for; exiting");
        exit;
    }

    $log->notice(sprintf("%d marks given have no rubric associated; preparing data injection", count($workToBeDone)));

    $boxBlacklist = [];

    foreach ($workToBeDone as list($boxId, $returnId, $studentId, $trueMark)) {
        // iterate that cache we prepared earlier

        if (isset($boxBlacklist[$boxId])) {
            // don't attempt to parse boxes we know we can't parse
            $log->debug("Box $boxId cannot be automarked; skipping");
            continue;
        }

        // obtain the width of the table (it should be 6)
        $tableWidthStmt->execute([ $boxId ]);
        $tableWidth = $tableWidthStmt->fetchColumn();

        if ($enforceWidth && $tableWidth != $requiredTableWidth) {
            // if we're being strict about this, blacklist the box in question
            $log->notice("Rubric grid width of exactly $requiredTableWidth required, cannot automark box $boxId; skipping");
            $boxBlacklist[$boxId] = true;
            continue;
        }

        // calculate which column we're going to put a mark in
        $columnIndex = floor($trueMark / 100 * ($tableWidth - 1));

        $log->notice(sprintf("Check student %4d, box %4d... %7.3f%% given, assigning column index %d (width is %d)", $studentId, $boxId, $trueMark, $columnIndex, $tableWidth), [ 'char' => 'e' ]);

        // get the list of criteria that are in use on this particular box
        $criteriaInUseStmt->execute([ $boxId ]);

        // reset this
        $descriptorList   = [];
        $descriptorErrors = false;

        while ($criterionId = $criteriaInUseStmt->fetchColumn()) {
            // iterate the list, searching for the appropriate descriptor in each case

            // get the descriptor that correlates ordinally to the column we're targeting
            // note: this one we bind the long way, because :rowNum is in the LIMIT clause and needs special treatment
            $obtainDescriptorIdStmt->bindValue(':rowNum', $columnIndex, PDO::PARAM_INT);
            $obtainDescriptorIdStmt->bindValue(':critId', $criterionId, PDO::PARAM_INT);
            $obtainDescriptorIdStmt->bindValue(':boxId',  $boxId,       PDO::PARAM_INT);
            $obtainDescriptorIdStmt->execute();

            $descriptorId = $obtainDescriptorIdStmt->fetchColumn();

            if (!$descriptorId) {
                $log->error("Unable to get a fix on descriptor for criterion $criterionId, searched for position $columnIndex; is there a descriptor in this cell?");
                $descriptorErrors = true;
                // we continue rather than break so that we can find out just how broken this particular criterion is - it could just be an absent cell
                continue;
            }

            // cache the value found, ready for insertion
            $descriptorList[$criterionId] = $descriptorId;

        }

        // give a neat summary of what's about to be injected
        $args = [
            'student' => $studentId,
            'box'     => $boxId,
            'crits'   => array_keys($descriptorList),
            'descs'   => array_values($descriptorList)
        ];

        if ($descriptorErrors) {
            $log->warning("Could not align columns: ".json_encode($args));
            continue;
        }

        $log->debug("Data for insertion: ".json_encode($args));

        foreach ($descriptorList as $critId => $descrId) {
            $params = [
                'studentId'    => $studentId,
                'boxId'        => $boxId,
                'critId'       => $critId,
                'descriptorId' => $descrId
            ];

            try {
                // aaaand in it goes
                $markInsertionStmt->execute($params);
                $log->debug("Data insertion success: [ $critId, $descrId ]");

            } catch (PDOException $e) {
                $log->warning("Data insertion failure: [ $critId, $descrId ]");
            }
        }

        $log->info("Successfully updated student $studentId, box $boxId", [ 'char' => '+' ]);
    }
});
