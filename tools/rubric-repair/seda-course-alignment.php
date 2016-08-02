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

$kernel->run(function($di, $loop, $log, $opts) {
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

    $selectedIndicatorStmt = $db->prepare("
        SELECT DISTINCT CONCAT_WS(' :: ', c.grouping, c.name) fullname, c.* 
        FROM rubric_descriptor d
        LEFT JOIN rubric_criterion c ON d.criterion_id = c.id
        WHERE d.valid IS TRUE
        AND d.submission_box_id = ?
        AND c.deleted_at IS NULL
        ORDER BY fullname ASC
    ");

    $notBefore = new Alaress_Date_Format('2016-01-01 00:00:00', 'Y-m-d H:i:s');
    $notAfter  = new Alaress_Date_Format('2016-07-01 00:00:00', 'Y-m-d H:i:s');

    $searchStmt = $db->query("
        SELECT COUNT(t.box_id) unit_boxes, COUNT(ti.box_id) class_boxes, ui.* 
        FROM course_unit_instance ui
        LEFT JOIN category c1 ON ui.category_id = c1.id
        LEFT JOIN category c2 ON ui.unit_category_id = c2.id
        LEFT JOIN task_instance ti ON ti.course_unit_instance_id = ui.id
        LEFT JOIN task t ON t.category_id = c2.id 
        LEFT JOIN submission_box b1 ON ti.box_id = b1.id
        LEFT JOIN submission_box b2 ON t.box_id = b2.id
        WHERE ui.deleted_at IS NULL
        AND t.box_id IS NOT NULL
        AND ti.box_id IS NOT NULL
        AND t.deleted_at IS NULL
        AND ti.deleted_at IS NULL
        AND ui.open_date >= :opendate
        AND ui.due_date <= :duedate
        AND c1.deleted_at IS NULL
        AND c2.deleted_at IS NULL
        AND b1.use_rubric IS TRUE
        AND b2.use_rubric IS TRUE
        GROUP BY ui.id;
    ", [
        'opendate' => $notBefore->getDatabase(),
        'duedate'  => $notAfter->getDatabase()
    ]);

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

            // date bounds check
            if ($instanceBox->getDueDate()->lt($notBefore) || $instanceBox->getDueDate()->gt($notAfter)) {
                $log->debug("Instance box $instanceBoxId outside given dates: skipping.");
                continue;
            }

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

            // return count check
            if ($instanceReturns->count() == 0) {
                $log->debug(sprintf(
                    "No returns exist for [class.instance.box](%d.%d.%d): skipping",
                    $class->getId(), $classTaskInstance->getId(), $instanceBoxId
                ));
                continue;
            }

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

            if (empty($dd)) {
                $log->debug("Indicators match between unit and class");
                continue;
            }

            list ($add, $del) = $dd;

            $log->warning(sprintf(
                "Instance %d: indicators do not match between unit box %d and class box %d! Cleaning up difference [%d +%d/-%d = %d]",
                $instance->getId(), $sourceBoxId, $instanceBoxId, count($instanceBoxIndicators), count($add), count($del), count($sourceBoxIndicators)
            ));

            foreach ($del as $deletion) {
                $log->info(sprintf("DEL indicator %5d from box %5d: %s", $deletion['id'], $instanceBoxId, $deletion['fullname']), [ 'char' => '-' ]);
                // TODO

                // INVALIDATE all rubric_mark records for this indicator
                // HARDDELETE the rubric_usage record for this indicator
                // INVALIDATE all rubric_descriptor records attached to this indicator
            }

            foreach ($add as $addition) {
                $log->info(sprintf("ADD indicator %5d into box %5d: %s", $addition['id'], $instanceBoxId, $addition['fullname']), [ 'char' => '+' ]);
                // TODO

                // DUPLICATE the rubric_criterion record from the unit folder into the class folder, compensating for parent_id
                // TRANSPOSE the rubric_descriptor records from the unit indicator to the class indicator, compensating for criterion_id, submission_box_id and level_id
                // INSERT  a new rubric_usage record for the new indicator against the box
            }

            $grid = (new SubmissionBox\Rubric\RubricMapper)->findBySubmissionBox($instanceBox);

            $log->debug("Verifying integrity of returns");

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

                // load into cache if absent
                if (empty($userCache[$studentId])) {
                    $userCache[$studentId] = Schoolbox\UserMapper::getInstance()->findById($studentId);
                }

                $student = $userCache[$studentId];

                // if the student-visible grade on this return is "X / Y" (total mark), we need to update it to match the rubric
                // in all other cases, we do nothing
                if (!preg_match('`\d+ ?/ ?\d+`', $return->getMark())) {
                    continue;
                }

                SubmissionBox\Rubric\RubricMapper::getInstance()->applyStudentMark($grid, $student);

                $newMark = $grid->getHumanReadableMark();

                $log->info(sprintf("Box %5d student %5d: update %7s to %7s [%s]", $instanceBoxId, $studentId, $return->getMark(), $newMark, $student->formatName()));
            }
        }

    }

});


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
