<?php
/**
 * READ JIRA TICKET SA-477 <https://schoolbox.atlassian.net/browse/SA-477> BEFORE ATTEMPTING TO DO ANYTHING WITH THIS SCRIPT
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Collection;

$fields = array('porcelain', 'mem', 'truemem', 'pid', 'bind');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Rubric Course Realignment', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
    'errors'  => E_ALL,
));

$kernel->run
(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    if ($di->get('config')->get('site_location') !== 'seda') {
        $log->info("Rubric course alignment not relevant to this instance: terminating.");
        // exit zero because this isn't an error
        exit;
    }

    // report the database name
    $log->notice("Operating on database '" . SQL_db . "'");

    $log->alert("Initialising rubric course realignment");

    /** @var Schoolbox\User[] $userCache */
    $userCache = [];

    // prepare statements
    $db = Database::getInstance();

    $selectNonInnoDbRubricTablesStmt = $db->prepare("
        SELECT `TABLE_NAME`
        FROM `information_schema`.`TABLES`
        WHERE `TABLE_SCHEMA` = :db
        AND `TABLE_NAME` LIKE 'rubric_%'
        AND `ENGINE` != 'InnoDB'
    ");

    $updateTableToInnoDbTemplate = "
        ALTER TABLE %s.%s
        ENGINE=INNODB
    ";

    $indexDescriptorIdStmt = $db->prepare("
        ALTER TABLE `rubric_mark` ADD INDEX (`descriptor_id`);
    ");

    $selectedIndicatorStmt = $db->prepare("
        SELECT DISTINCT CONCAT_WS(' :: ', c.grouping, c.name) fullname, c.* 
        FROM rubric_descriptor d
        LEFT JOIN rubric_criterion c ON d.criterion_id = c.id
        WHERE d.valid IS TRUE
        AND d.submission_box_id = ?
        AND c.deleted_at IS NULL
        ORDER BY fullname ASC
    ");

    $invalidateMarkStmt = $db->prepare("
        UPDATE rubric_mark
        SET valid = FALSE
        WHERE criterion_id = :criterion_id
        AND submission_box_id = :submission_box_id
    ");

    $hardDeleteUsageStmt = $db->prepare("
        DELETE FROM rubric_usage
        WHERE criterion_id = :criterion_id
        AND submission_box_id = :submission_box_id
    ");

    $invalidateDescriptorStmt = $db->prepare("
        UPDATE rubric_descriptor
        SET valid = FALSE
        WHERE criterion_id = :criterion_id
        AND submission_box_id = :submission_box_id
    ");

    $massInvalidateMarkStmt = $db->prepare("
        UPDATE rubric_mark m
        LEFT JOIN rubric_descriptor d ON m.descriptor_id = d.id
        SET m.valid = FALSE
        WHERE d.valid != m.valid
        AND d.submission_box_id = :submission_box_id
    ");

    $duplicateCriterionStmt = $db->prepare("
        INSERT INTO rubric_criterion (grouping, `name`, parent_id, parent_type)
        SELECT grouping, `name`, :parent_id, 'category'
        FROM rubric_criterion
        WHERE id = :criterion_id
    ");

    $findSourceDescriptors = $db->prepare("
        SELECT d.id, `value`, description, l.sequence
        FROM rubric_descriptor d
        INNER JOIN rubric_level l ON d.level_id = l.id
        RIGHT JOIN rubric_criterion c ON d.criterion_id = c.id
        WHERE c.id = :source_crit_id 
        AND d.submission_box_id = :source_box_id
        ORDER BY l.sequence ASC
    ");

    $findLevelIdStmt = $db->prepare("
        SELECT id
        FROM rubric_level
        WHERE box_id = :box_id
        ORDER BY sequence ASC
        LIMIT 1 OFFSET :nth
    ");

    $insertDescriptorStmt = $db->prepare("
        INSERT INTO rubric_descriptor (criterion_id, submission_box_id, level_id, `value`, description, valid)
        VALUES (:crit_id, :box_id, :level_id, :val, :des, 1)
    ");

    $findNextAvailableSequenceStmt = $db->prepare("
      SELECT COALESCE(MAX(sequence) + 1, 1) FROM rubric_usage WHERE submission_box_id = :box_id
    ");

    $insertRubricUsageStmt = $db->prepare("
        INSERT INTO rubric_usage VALUES (:crit_id, :box_id, :seq)
    ");

    $insertRubricLevelStmt = $db->prepare("
        INSERT INTO rubric_level (box_id, name, sequence) VALUES (:box_id, '', :seq)
    ");

    $findUnmarkedCriteriaStmt = $db->prepare("
        SELECT DISTINCT criterion_id
        FROM rubric_descriptor d
        WHERE d.submission_box_id = :box_id
        AND criterion_id NOT IN (
            SELECT criterion_id
            FROM rubric_mark m
            WHERE m.student_id = :student_id
            AND m.submission_box_id = :box_id
            AND m.valid IS TRUE
        )
    ");

    $getLatestStampStmt = $db->prepare("
        SELECT tstamp
        FROM rubric_mark
        WHERE submission_box_id = :box_id
        AND student_id = :student_id
        ORDER BY tstamp DESC
        LIMIT 1
    ");

    $insertMarkStmt = $db->prepare("
        INSERT INTO rubric_mark (`student_id`, `submission_box_id`, `criterion_id`, `descriptor_id`, `tstamp`)
        VALUES (:student_id, :box_id, (SELECT criterion_id FROM rubric_descriptor WHERE id = :desc_id), :desc_id, :tstamp)
    ");

    $getLatestReturnStmt = $db->prepare("
        SELECT id
        FROM submission_return
        WHERE `owner` = :student_id
        AND box_id = :box_id
        ORDER BY returned DESC
        LIMIT 1
    ");

    $updateReturnRecordStmt = $db->prepare("
        UPDATE submission_return 
        SET mark = :visibleMark
        WHERE id = :return_id
    ");


    // verify that the tables are all in InnoDB format
    $selectNonInnoDbRubricTablesStmt->execute([ 'db' => SQL_db ]);

    while ($tableName = $selectNonInnoDbRubricTablesStmt->fetch(\PDO::FETCH_COLUMN)) {
        try {
            $log->warning("WARNING: $tableName changed to InnoDB");
            $db->query(sprintf($updateTableToInnoDbTemplate, SQL_db, $tableName));

        } catch (PDOException $e) {
            $log->emergency($e->getMessage(), [ $e ]);
            exit(1);
        }
    }

    // set the missing index so queries actually execute in non-molasses time
    $indexDescriptorIdStmt->execute();

    $searchStmt = $db->query("
        SELECT COUNT(t.box_id) unit_boxes, COUNT(ti.box_id) class_boxes, ui.* 
        FROM course_unit_implementation ui
        LEFT JOIN folder c1 ON ui.category_id = c1.id
        LEFT JOIN folder c2 ON ui.unit_category_id = c2.id
        LEFT JOIN task_instance ti ON ti.course_unit_instance_id = ui.id
        LEFT JOIN task t ON t.category_id = c2.id 
        LEFT JOIN submission_box b1 ON ti.box_id = b1.id
        LEFT JOIN submission_box b2 ON t.box_id = b2.id
        WHERE ui.deleted_at IS NULL
        AND t.box_id IS NOT NULL
        AND ti.box_id IS NOT NULL
        AND t.deleted_at IS NULL
        AND ti.deleted_at IS NULL
        AND c1.deleted_at IS NULL
        AND c2.deleted_at IS NULL
        AND b1.use_rubric IS TRUE
        AND b2.use_rubric IS TRUE
        GROUP BY ui.id;
    ");

    // check every instance for cohesiveness
    foreach ($searchStmt as $row) {

        /** @var UnitInstance $instance */
        $instance = UnitInstanceMapper::getInstance()->hydrate($row);

        try {
            $class  = fullyHydrateFolder($instance->getCategory());
            $source = fullyHydrateFolder($instance->getUnitCategory());
        } catch (Schoolbox\Mapper\Exception\Deleted $e) {
            $log->debug("Folder {$e->getDeletedObject()->getId()} has been deleted, skipping unit instance", [ 'char' => 'e' ]);
            continue;
        }

        $log->debug(sprintf("Testing %4d imported into %4d: '%s' -> '%s'", $source->getId(), $class->getId(), $source->getName(), $class->getNameWithCodes()));

        // for every rubric-marked submission box that exists in BOTH class and source, compare indicators

        $sourceTasks = TaskMapper::getInstance()->findByCategory($source)->filter(
            function (Task $v)
            {
                return $v->getSubmissionBox() instanceof SubmissionBox && $v->getSubmissionBox()->getId() && $v->getSubmissionBox()->isRubric();
            }
        );

        if ($sourceTasks->count() == 0) {
            $log->debug(sprintf("Unit %d '%s' has no rubric-marked submission boxes: skipping.", $source->getId(), $source->getName()));
            continue;
        }

        /** @var Collection|Task\Instance[] $classTaskInstances */
        $classTaskInstances = Task\InstanceMapper::getInstance()->findByColumnList([
            'task_id'                 => $sourceTasks->getColumnValues('id'),
            'course_unit_instance_id' => $instance->getId()
        ]);

        if ($classTaskInstances->count() == 0) {
            $log->debug(sprintf("Class %d '%s' has no rubric-marked submission boxes: skipping.", $class->getId(), $class->getName()));
            continue;
        }

        $log->debug(sprintf(
            "Interrogating unit %d ('%s') on class %d ('%s')",
            $source->getId(), $source->getName(), $class->getId(), $class->getName()
        ));

        // arrange by ID
        $sourceTasks = $sourceTasks->groupBy('id');

        // obtain rubric data and cross-reference
        foreach ($classTaskInstances as $classTaskInstance) {
            /** @var Task $sourceTask */
            $sourceTask = $sourceTasks[$classTaskInstance->getTask()->getId()]->first();

            if (!($classTaskInstance->getSubmissionBox() instanceof SubmissionBox && $classTaskInstance->getSubmissionBox()->getId())) {
                $log->debug("Somehow task instance {$classTaskInstance->getId()} does not have a submission box attached, despite the source: skipping");
                continue;
            }

            $sourceBoxId   = $sourceTask->getSubmissionBox()->getId();
            $instanceBox   = fullyHydrateBox($classTaskInstance->getSubmissionBox());

            $instanceBoxId = $instanceBox->getId();

            /** @var Collection|SubmissionBox\ReturnedWork[] $instanceReturns */
            $instanceReturns = SubmissionBox\ReturnedWorkMapper::getInstance()->findByColumnList([
                'box_id' => $instanceBoxId,
                'is_confirmation_of_submission' => false
            ], [
                'deleted' => false,
                'order'   => [
                    'returned' => 'DESC'
                ]
            ]);

            $log->debug(sprintf(
                "[class.instance.box](%d.%d.%d): %d returns exist",
                $class->getId(), $classTaskInstance->getId(), $instanceBoxId, $instanceReturns->count()
            ));

            $log->debug(sprintf(
                "Checking rubric data on [unit.task.box](%d.%d.%d) against [class.instance.box](%d.%d.%d)",
                $source->getId(), $sourceTask->getId(), $sourceBoxId,
                $class->getId(), $classTaskInstance->getId(), $instanceBoxId
            ));

            $selectedIndicatorStmt->execute([ $sourceBoxId ]);
            $sourceBoxIndicators = $selectedIndicatorStmt->fetchAll(PDO::FETCH_ASSOC);

            $selectedIndicatorStmt->execute([ $instanceBoxId ]);
            $instanceBoxIndicators = $selectedIndicatorStmt->fetchAll(PDO::FETCH_ASSOC);

            // find out the patch-difference between these two arrays
            $dd = doubleDiff($sourceBoxIndicators, $instanceBoxIndicators, $log);

            try {
                $log->info("BEGIN TX$instanceBoxId", [ 'char' => '>' ]);
                $db->beginTransaction();

                if (empty($dd)) {
                    $log->debug("Indicators match between unit and class");

                } else {
                    list ($add, $del) = $dd;

                    $log->warning(sprintf(
                        "Instance %d: indicators do not match between unit box %d and class box %d! Cleaning up difference [%d +%d/-%d = %d]",
                        $instance->getId(), $sourceBoxId, $instanceBoxId, count($instanceBoxIndicators), count($add), count($del), count($sourceBoxIndicators)
                    ));

                    foreach ($del as $deletion) {
                        removeIndicators($deletion, $instanceBoxId, $invalidateMarkStmt, $hardDeleteUsageStmt, $invalidateDescriptorStmt, $log);
                    }

                    try {
                        foreach ($add as $addition) {
                            insertIndicators(
                                $addition, $instanceBoxId, $sourceBoxId, $class->getId(),
                                $duplicateCriterionStmt, $findSourceDescriptors, $findLevelIdStmt,
                                $insertDescriptorStmt, $findNextAvailableSequenceStmt, $insertRubricUsageStmt, $insertRubricLevelStmt, $db, $log
                            );
                        }
                    } catch (InvalidArgumentException $e) {
                        throw new PDOException("Could not insert new indicators: ".$e->getMessage(), 0, $e);
                    }
                }

                /** @var SubmissionBox\Rubric\Rubric $grid */
                $grid = null;

                // return count check
                if ($instanceReturns->count() == 0) {
                    $log->debug(sprintf(
                        "[class.instance.box](%d.%d.%d): no returns exist; no further work to do",
                        $class->getId(), $classTaskInstance->getId(), $instanceBoxId
                    ));

                } else {
                    $log->info("Invalidating all incongruous rubric marks [box_id]($instanceBoxId)");
                    $massInvalidateMarkStmt->execute([ 'submission_box_id' => $instanceBoxId ]);

                    $log->debug("Verifying integrity of returns");
                    $grid = (new SubmissionBox\Rubric\RubricMapper)->findBySubmissionBox($instanceBox);
                }

                $userSeen = [];

                // iterate the list of returns for this box
                foreach ($instanceReturns as $return) {

                    $studentId = $return->getOwner()->getId();

                    // skip students we've already checked the most recent of
                    if (isset($userSeen[$studentId])) {
                        continue;
                    }

                    // add to seen list so we don't parse them again
                    $userSeen[$studentId] = true;

                    $hrMark = $return->getMark();

                    // EXTREMELY SPECIFIC CASE: student returns with an submission state of 'RESUBMIT' need to be left alone
                    if ($hrMark == 'RESUBMIT') {
                        $log->debug("Student has been flagged for resubmission; skipping [box_id,student_id]($instanceBoxId,$studentId)");
                        continue;
                    }

                    $log->debug("Checking marks for user $studentId");

                    // load into cache if absent
                    if (empty($userCache[$studentId])) {
                        $userCache[$studentId] = Schoolbox\UserMapper::getInstance()->findById($studentId);
                    }

                    $student = $userCache[$studentId];

                    SubmissionBox\Rubric\RubricMapper::getInstance()->applyStudentMark($grid, $student);

                    $array = $grid->toContext();

                    $markedDenominator   = 0;
                    $unmarkedCount       = 0;
                    $markedCount         = 0;
                    $unmarkedDenominator = 0;

                    foreach ($array['groups'] as $gid => $group) {
                        foreach ($group['criteria'] as $critId => $criterion) {

//                            $log->debug("TEST Criterion $critId");

                            $critMax    = $criterion['total'];
                            $critMarked = !is_null($criterion['sum']);

                            if ($critMarked) {

//                                $log->debug("Crit $critId is marked, adding to running total");

                                $markedDenominator += $critMax;
                                ++$markedCount;
                            } else {
//                                $log->debug("Crit $critId NOT marked");

                                ++$unmarkedCount;
                                $unmarkedDenominator += $critMax;
                            }
                        }
                    }

//                    $log->debug("Existing mark given: " . $grid->getSum() . " / $markedDenominator");

                    if ($markedDenominator == 0) {
                        // div by zero, no marks given: skip!
                        $log->debug("No marks have been given, nothing to extrapolate");
                        continue;
                    }

                    if ($unmarkedCount == 0) {
                        $log->debug("Rubric grid is fully marked; no marks to apply");
                        continue;
                    }

                    $averageGiven = $grid->getSum() / $markedDenominator;

                    $marksToApply = round($averageGiven * $unmarkedDenominator);
//                    $log->debug("Marks to apply: $marksToApply");

                    $newDenom  = $grid->getTotal();
                    $newNum    = $grid->getSum() + $marksToApply;

//                    $newVisibleMark = "$newNum / $newDenom";
//                    $log->debug("New mark: $newVisibleMark");

                    if ($marksToApply == 0) {
                        $log->debug("No marks to apply");
                        continue;
                    }

                    // obtain the set of unmarked criteria for this student
                    $findUnmarkedCriteriaStmt->execute([ 'box_id' => $instanceBoxId, 'student_id' => $studentId ]);

                    $log->debug("Distribute $marksToApply marks across $unmarkedCount indicators");

                    $unmarkedCritIds = $findUnmarkedCriteriaStmt->fetchAll(PDO::FETCH_COLUMN);

                    /** @var Collection|SubmissionBox\Rubric\Descriptor[] $descriptors */
                    $descriptors = SubmissionBox\Rubric\DescriptorMapper::getInstance()->findByColumnList([
                        'submission_box_id' => $instanceBoxId,
                        'criterion_id'   => $unmarkedCritIds
                    ]);

                    $set = [];

                    foreach ($descriptors as $d) {
                        $set[$d->getCriterion()->getId()][$d->getValue()] = $d->getId();
                    }

                    $avg = findClosestMatch($set, $marksToApply);

                    if (empty($avg)) {
                        $log->error("Could not apply $marksToApply marks to rubric [box_id,student_id]($instanceBoxId,$studentId)");
                        continue;
                    }

                    $getLatestStampStmt->execute([
                        'box_id' => $instanceBoxId,
                        'student_id' => $studentId
                    ]);

                    $stamp = $getLatestStampStmt->fetchColumn();

                    foreach ($avg as $descriptorId => $descriptorValue) {
                        $insertMarkStmt->execute([
                            'student_id' => $studentId,
                            'box_id'     => $instanceBoxId,
                            'desc_id'    => $descriptorId,
                            'tstamp'     => $stamp
                        ]);
                    }

                    $adjustment = json_encode($avg);

                    $log->debug("Mark adjustment: $adjustment [box_id,student_id]($instanceBoxId,$studentId)");

                    $getLatestReturnStmt->execute([
                        'student_id' => $studentId,
                        'box_id' => $instanceBoxId
                    ]);

                    $returnId = $getLatestReturnStmt->fetchColumn();

                    $log->info("Updating student-visible mark to $newNum / $newDenom [return_id,box_id,student_id]($returnId,$instanceBoxId,$studentId)");

                    $updateReturnRecordStmt->execute([
                        'visibleMark' => "$newNum / $newDenom",
                        'return_id'   => $returnId
                    ]);

                }
                $log->info("COMMIT TX$instanceBoxId", [ 'char' => '+' ]);
                $db->commit();

            } catch (PDOException $e) {
                $log->alert('PDOException: ' . $e->getMessage(), [ $e ]);
                $log->info("ABORT TX$instanceBoxId", [ 'char' => '~' ]);
                $db->rollBack();
            }
        }

    }

});

//$cmad = findClosestMatch([
//    [ 0 => 1,  4 => 2,  5 => 3,  6 => 4,  7 => 5,  8 => 6  ],
//    [ 0 => 7,  4 => 8,  5 => 9,  6 => 10, 7 => 11, 8 => 12 ],
//    [ 0 => 13, 4 => 14, 5 => 15, 6 => 17, 7 => 18, 8 => 19 ],
//], 25);
//
//$soln = [ 6 => 8, 12 => 8, 19 => 8 ];
//
//var_dump($cmad);
//var_dump($soln);
//var_dump($cmad == $soln);

/**
 * @param array $set
 * @param int   $targetValue
 * @return array
 */
function findClosestMatch(array $set, $targetValue)
{
    if ($targetValue <= 0) {
        // failfast: can't do anything with zero or negatives
        return [];
    }

    $result = computeMostAveragestDelta($set, $targetValue);

    if (empty($result)) {
        foreach ($set as $row) {

            $cacheVal = $cacheId = 0;

            foreach ($row as $val => $id) {

                if ($val > $cacheVal) {
                    $cacheVal = $val;
                    $cacheId = $id;
                }
            }

            $result[$cacheId] = $cacheVal;
        }
    }

    return $result;
}


/**
 * This is the definition of insanity.
 * From the given 2-dimensional set, return a 1-dimensional set representing which values would add to the given target.
 *
 * @param array $set
 * @param int   $targetValue
 * @param array $append
 * @return array
 */
function computeMostAveragestDelta(array $set, $targetValue, $append = [])
{
    if (array_sum($append) == $targetValue) {
        return $append;
    }

    // no work can be achieved
    if (count($set) == 0 || $targetValue <= 0) {
//        echo "NO CAN DO WORK\n";
        return $append;
    }

    $average = $targetValue / count($set);
//    echo "AVERAGE computed is $average\n";

    foreach ($set as $critId => $values) {

        // obtain set diff
        $reducedSet = $set;
        unset($reducedSet[$critId]);

        // sort by value proximity to the average for best fit
        uksort($values, function ($a, $b) use ($average) {
            $dA = abs($a - $average);
            $dB = abs($b - $average);

            if ($dA == $dB) {
                return 0;
            }

            return $dA > $dB ? 1 : -1;
        });

        foreach ($values as $dVal => $dId) {
            if ($dVal == $targetValue) {
                // if this is a perfect match, just return it
//                echo "RETURNING EXACT, value is $dVal\n";
                $append[$dId] = $dVal;
                return $append;
            }
            if ($dVal > $targetValue) {
//                echo "$dVal IS BIGGER THAN $targetValue, TOO BIG TO USE\n";
                continue;
            }

            // recurse with the reduced set, accommodating our currently-selected value
            $reducedTarget = $targetValue - $dVal;
//            echo "RECURSING with $targetValue - $dVal = $reducedTarget...\n";

            $append = computeMostAveragestDelta($reducedSet, $reducedTarget, $append);

            $targetValue -= array_sum($append);

            if ($dVal == $targetValue) {
                // if this is a perfect match, just return it
//                echo "RETURNING EXACT, value is $dVal\n";
                $append[$dId] = $dVal;
                return $append;
            }

        }

    }

//    echo "FINAL RETURN with sum of " . array_sum($append). "\n";
    return $append;
}

/**
 * @param array           $deletion
 * @param int             $instanceBoxId
 * @param PDOStatement    $invalidateMarkStmt
 * @param PDOStatement    $hardDeleteUsageStmt
 * @param PDOStatement    $invalidateDescriptorStmt
 * @param LoggerInterface $log
 */
function removeIndicators(
    array $deletion,
    $instanceBoxId,
    PDOStatement $invalidateMarkStmt,
    PDOStatement $hardDeleteUsageStmt,
    PDOStatement $invalidateDescriptorStmt,
    LoggerInterface $log
) {
    $log->notice(sprintf("DEL indicator %5d from box %5d: %s", $deletion['id'], $instanceBoxId, $deletion['fullname']), [ 'char' => '-' ]);

    $selection = [
        'submission_box_id' => $instanceBoxId,
        'criterion_id'      => $deletion['id']
    ];

    $logSelection = sprintf("[%s](%s)", implode(',', array_keys($selection)), implode(',', $selection));

    // INVALIDATE all rubric_mark records for this indicator
    $log->info("INVALIDATE rubric_mark $logSelection");
    $invalidateMarkStmt->execute($selection);

    // HARDDELETE the rubric_usage record for this indicator
    $log->info("HARDDELETE rubric_usage $logSelection");
    $hardDeleteUsageStmt->execute($selection);

    // INVALIDATE all rubric_descriptor records attached to this indicator
    $log->info("INVALIDATE rubric_descriptor $logSelection");
    $invalidateDescriptorStmt->execute($selection);
}


/**
 * @param array           $addition
 * @param                 $instanceBoxId
 * @param                 $sourceBoxId
 * @param                 $classId
 * @param PDOStatement    $duplicateCriterionStmt
 * @param PDOStatement    $findSourceDescriptors
 * @param PDOStatement    $findLevelIdStmt
 * @param PDOStatement    $insertDescriptorStmt
 * @param PDOStatement    $findNextAvailableSequenceStmt
 * @param PDOStatement    $insertRubricUsageStmt
 * @param PDOStatement    $insertRubricLevelStmt
 * @param Database        $db
 * @param LoggerInterface $log
 */
function insertIndicators(
    array $addition,
    $instanceBoxId,
    $sourceBoxId,
    $classId,
    PDOStatement $duplicateCriterionStmt,
    PDOStatement $findSourceDescriptors,
    PDOStatement $findLevelIdStmt,
    PDOStatement $insertDescriptorStmt,
    PDOStatement $findNextAvailableSequenceStmt,
    PDOStatement $insertRubricUsageStmt,
    PDOStatement $insertRubricLevelStmt,
    Database $db,
    LoggerInterface $log
) {
    $log->notice(sprintf("ADD indicator %5d into box %5d: %s", $addition['id'], $instanceBoxId, $addition['fullname']), [ 'char' => '+' ]);

    // DUPLICATE the rubric_criterion record from the unit folder into the class folder, compensating for parent_id
    $log->info("DUPLICATE rubric_criterion {$addition['id']} into folder $classId");
    $duplicateCriterionStmt->execute([ 'criterion_id' => $addition['id'], 'parent_id' => $classId ]);
    $newCriterionId = $db->lastInsertId();

    // TRANSPOSE the rubric_descriptor records from the unit indicator to the class indicator, compensating for criterion_id, submission_box_id and level_id
    $log->info("TRANSPOSE rubric_descriptor (criterion_id,submission_box_id)[{$addition['id']},$sourceBoxId] into submission_box [id]($instanceBoxId)");
    // step 1: retrieve value, description, and the SEQUENCE of the level of the source descriptors
    $findSourceDescriptors->execute([ 'source_crit_id' => $addition['id'], 'source_box_id' => $sourceBoxId ]);

    $minus = 1;
    $first = true;
    $errors = [];

    // step 2: iterate results and insert with new criterion, box and level values
    while ($r = $findSourceDescriptors->fetch(PDO::FETCH_NUM)) {
        list ($id, $value, $description, $levelSequence) = $r;

        if ($levelSequence == 0) {
            if ($first) {
                $log->warning("Encountered a sequence value of zero for [descriptor_id]($id); assuming zero-indexed rather than one-indexed");
                $minus = 0;
            } else {
                throw new InvalidArgumentException("Level sequence cannot be zero in non-zero-indexed set: failed to retrieve sequence for [descriptor_id]($id)");
            }
        }

        $offset = $levelSequence - $minus;

        $findLevelIdStmt->bindValue(':nth', $offset, PDO::PARAM_INT);
        $findLevelIdStmt->bindValue(':box_id', $instanceBoxId);
        $findLevelIdStmt->execute();

        $levelId = $findLevelIdStmt->fetchColumn();

        if ($levelId == 0) {
            $log->warning("Failed to retrieve an ID for [box_id,desc_id,seq]($instanceBoxId,$id,$levelSequence) - generating new level");

            $insertRubricLevelStmt->execute([ 'box_id' => $instanceBoxId, 'seq' => $levelSequence ]);

            $levelId = $db->lastInsertId();
        }

        $payload = [
            'crit_id'  => $newCriterionId,
            'box_id'   => $instanceBoxId,
            'level_id' => $levelId,
            'val'      => $value,
            'des'      => $description
        ];

        $paystring = json_encode($payload);

        try {
            $log->debug("Attempting insertion of $paystring");
            $insertDescriptorStmt->execute($payload);

        } catch (PDOException $e) {

            $message = "Could not insert descriptor $paystring";

            if (preg_match('`^SQLSTATE\[\d+\]: (.+)$`', $e->getMessage(), $matches)) {
                $message .= ": " . $matches[1];
            }

            $errors[] = $message;
        }

        $first = false;
    }

    if (count($errors)) {
        throw new PDOException("Errors encountered: \n" . implode("\n   ", $errors));
    }

    // find the next available sequence
    $findNextAvailableSequenceStmt->execute([ 'box_id' => $instanceBoxId ]);
    $nextSequence = $findNextAvailableSequenceStmt->fetchColumn();

    // INSERT  a new rubric_usage record for the new indicator against the box
    $insertRubricUsageStmt->execute([ 'crit_id' => $newCriterionId, 'box_id' => $instanceBoxId, 'seq' => $nextSequence ]);
}

/**
 * @param \Schoolbox\Resource\Folder\Model $folder
 * @throws \Schoolbox\Mapper\Exception
 * @return \Schoolbox\Resource\Folder\Model
 */
function fullyHydrateFolder(Schoolbox\Resource\Folder\Model $folder)
{
    return Schoolbox\Resource\Folder\Mapper::getInstance()->findCheaplyById($folder->getId());
}

/**
 * @param SubmissionBox $box
 * @throws \Schoolbox\Mapper\Exception
 * @return SubmissionBox
 */
function fullyHydrateBox(SubmissionBox $box)
{
    return SubmissionBoxMapper::getInstance()->findById($box->getId());
}

/**
 * @param array           $truth
 * @param array           $test
 * @param LoggerInterface $log
 * @return array
 */
function doubleDiff(array $truth, array $test, LoggerInterface $log)
{
    // index by ID
    $truth = array_combine(array_column($truth, 'id'), $truth);
    $test = array_combine(array_column($test, 'id'), $test);

    if (array_column($truth, 'fullname') == array_column($test, 'fullname')) {
        $log->debug("Perfect match");
        return [];
    }

    $truthNames = array_combine(array_column($truth, 'id'), array_column($truth, 'name'));
    $testNames  = array_combine(array_column($test, 'id'), array_column($test, 'name'));

    asort($truthNames);
    asort($testNames);

    // first try for clean name matching
    if (array_values($truthNames) == array_values($testNames)) {
        $log->debug("Match by names only, changed groupings");
        return [];
    }

    // next, try to compensate for whitespace and punctuation adjustments
    $fuzzyTruth = mapWithKeys(function ($v) { return preg_replace('`[^a-z0-9:]+`i', ' ', trim($v)); }, $truthNames);
    $fuzzyTest  = mapWithKeys(function ($v) { return preg_replace('`[^a-z0-9:]+`i', ' ', trim($v)); }, $testNames);

    asort($fuzzyTruth);
    asort($fuzzyTest);

    if ($fuzzyTruth == $fuzzyTest) {
        $log->debug("Fuzzy match");
        return [];
    }

    // *sigh* ok, try for removing any prepended numberings
    $denumTruth = mapWithKeys(function ($v) { return preg_replace('`^[^a-z]+`i', '', trim($v)); }, $fuzzyTruth);
    $denumTest  = mapWithKeys(function ($v) { return preg_replace('`^[^a-z]+`i', '', trim($v)); }, $fuzzyTest);

    asort($denumTruth);
    asort($denumTest);

    if ($denumTruth == $denumTest) {
        $log->debug("Match after denumbering");
        return [];
    }

    $add = [];
    $del = [];

    foreach ($denumTruth as $i => $truthElement) {
        if (!in_array($truthElement, $denumTest)) {
            $add[] = $truth[$i];
        }
    }

    foreach ($denumTest as $i => $testElement) {
        if (!in_array($testElement, $denumTruth)) {
            $del[] = $test[$i];
        }
    }

    // both blank... everything lined up in the end...
    if (count($add) + count($del) == 0) {
        return [];
    }

    return [ $add, $del ];
}

/**
 * @param callable $callback
 * @param array    $array
 * @return array
 */
function mapWithKeys(callable $callback, array $array)
{
    return array_combine(array_keys($array), array_map($callback, $array));
}
