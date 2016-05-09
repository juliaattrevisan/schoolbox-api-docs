<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Database;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\Factory;
use Schoolbox\IMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Rubric\Competency\Level as CompetencyLevel;
use Schoolbox\Assessment\Rubric\Criteria;
use Schoolbox\Collection;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

/**
 * Umm. Not *exactly* a mapper... at the moment it's more like a handler...
 *
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Rubric
 */
class RubricMapper
{

    /**
     * Stores the singleton instance of the mapper
     *
     * @var array[Mapper]
     */
    static protected $instance;


    /**
     * Retrieves the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance()
    {
        $class_name = get_called_class();
        if (!isset(self::$instance[$class_name])) {
            self::$instance[$class_name] = new static();
        }
        return self::$instance[$class_name];
    }



    /** @var Database */
    protected $database;

    /** @var Factory */
    protected $factory;


    /** */
    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->factory  = new Factory(new Rubric());
    }


    /**
     * Given a folder ID, return the base rubric, including criteria inherited from parents
     *
     * @param int   $folderId
     * @param bool  $forceTaskMode - if you need task-level criteria showing
     * @param array $levelNames
     * @return Rubric
     */
    public function findByFolderId($folderId, $forceTaskMode = false, array $levelNames = [])
    {

        /** @var Criteria\Group[] $groupCache */
        $groupCache = [];

        $rubric = new Rubric();

        if (!count($levelNames)) {
            // default to 2 blank levels
            $levelNames = [ '', '' ];
        }

        // inject the levels
        foreach ($levelNames as $i => $level) {
            $rubric->putLevel(new CompetencyLevel($i, $level));
        }

        $args = [ 'folderId' => $folderId ];
        $in   = [ 'folderId' ];

        if ($forceTaskMode) {
            $key      = 'parentFolderId';
            $parentId = Folder\Mapper::getInstance()->findCheaplyById($folderId)->getParent()->getId();

            $args[$key] = $parentId;
            $in[] = $key;
        }

        $in = implode(', :', $in);


        // TODO DI and mapperise
        $stmt = \Database::getInstance()->query("
            SELECT rc.id, rc.grouping, rc.name, COUNT(ru.submission_box_id) usage_count
            FROM rubric_criterion rc
            LEFT JOIN rubric_usage ru ON rc.id = ru.criterion_id
            LEFT JOIN submission_box b ON ru.submission_box_id = b.id
            WHERE rc.parent_type = 'category'
            AND rc.parent_id IN (:$in)
            AND rc.deleted_at IS NULL
            AND b.deleted IS NOT TRUE
            GROUP BY rc.id
        ", $args);

        $groupId = 21;

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $groupName = $r['grouping'];

            if (!array_key_exists($groupName, $groupCache)) {
                $groupCache[$groupName] = new Criteria\Group($groupId++, $groupName);
            }

            $crit = new Criteria\Criterion($r['id'], $r['name']);

            $crit->setReadOnly($r['usage_count'] > 0);

            // cascade the read-onlyness up to the Group
            if ($crit->isReadOnly()) {
                $groupCache[$groupName]->setReadOnly(true);
            }

            $rubric->putCriterion($groupCache[$groupName], $crit);
        }

        return $rubric;
    }


    /**
     * Given a box ID, return the rubric assigned to it, if any
     *
     * @param SubmissionBox $box
     * @param bool          $forceTaskMode - if the box doesn't yet have a task, but you still need the task-level criteria showing
     * @param bool          $editMode
     * @return Rubric
     */
    public function findBySubmissionBox($box, $forceTaskMode = false, $editMode = false)
    {
        $levelCollection = LevelMapper::getInstance()->findByColumn('box_id', $box->getId(), [ 'order' => [ 'sequence' => 'ASC' ] ]);

        $levelList = [];

        foreach ($levelCollection as $level) {
            $levelList[$level->getSequence()] = [
                'id'   => $level->getId(),
                'name' => $level->getName()
            ];
        }

        $args = [
            'boxId' => $box->getId(),
            'folderId' => $box->getCategory()->getId()
        ];

        $in = [ 'folderId' ];

        // if the box has a task, include parent-folder criteria
        if ($box->getTask()->getId() || $forceTaskMode) {

            $key      = 'parentFolderId';
            $parentId = $box->getCategory()->getParent()->getId();

            if (!$parentId) {
                // insufficient hydration
                $parentId = Folder\Mapper::getInstance()->findCheaplyById($box->getCategory()->getId())->getParent()->getId();
            }

            $args[$key] = $parentId;
            $in[] = $key;
        }

        $in = implode(', :', $in);

        $criteriaStatement = $this->database->query("
            SELECT
                COUNT(box_id) usages,
                COALESCE(selected, 0)
                selected,
                crit_id,
                `grouping`,
                `name`
            FROM (
                SELECT
                    b.id box_id,
                    b.id = :boxId selected,
                    c.id crit_id,
                    u.`sequence`,
                    c.`grouping`,
                    c.`name`
                FROM rubric_usage u
                RIGHT JOIN rubric_criterion c ON u.criterion_id = c.id
                LEFT JOIN submission_box b ON u.submission_box_id = b.id AND b.deleted IS NOT TRUE
                WHERE parent_type = 'category'
                AND parent_id IN (:$in)
                AND deleted_at IS NULL
                ORDER BY selected DESC
            ) t
            GROUP BY crit_id
            ORDER BY selected DESC, `sequence` ASC, `grouping` ASC, `name` ASC;
        ", $args);


        // why 20? because it's not 0.
        $groupId = 20;

        $groups = $criteria = $descriptors = [];

        $groupIdMap = [];

        while ($r = $criteriaStatement->fetch(PDO::FETCH_ASSOC)) {

            $groupName = $r['grouping'];

            if (empty($groupIdMap[$groupName])) {
                ++$groupId;

                $groupIdMap[$groupName] = $groupId;
                $groups[$groupId] = [ 'name' => $groupName ];
            }

            // check for usage
            $usedElsewhere = ($r['usages'] - $r['selected']) > 0;

            $criteria[$r['crit_id']] = [
                'groupId'  => $groupIdMap[$groupName],
                'on'       => $r['selected'] > 0,
                'name'     => $r['name'],
                'readonly' => $usedElsewhere
            ];

        }

        // umm... I have *never* had to do this before... how does the while() loop break if not EOF??
        $criteriaStatement->closeCursor();

        /** @var Collection|SubmissionBox\Rubric\Descriptor[] $descriptorCollection */
        $descriptorCollection = SubmissionBox\Rubric\DescriptorMapper::getInstance()->findByColumnList([
            'submission_box_id' => $box->getId(),
            'valid'             => true
        ]);

        foreach ($descriptorCollection as $d) {
            // only populate if a description is given
            $descriptors[$d->getCriterion()->getId()][$d->getLevel()->getSequence()] = [
                'id'     => $d->getId(),
                'text'   => $d->getDescription(),
                'points' => $d->getValue() || $d->getDescription() ? $d->getValue() : ''
            ];
        }

        // mokay. so if at this point you've got no descriptors, check to see if there should be a resultSet we should use
        if (count($descriptors) == 0) {
            // misnomer much
            $resultSetId = $box->getRubricResultSet();

            if ($resultSetId > 0) {
                // got a resultSet - we're going to fake that into a descriptor grid

                /** @var Collection|Result[] $results */
                $results = SubmissionBox\Rubric\ResultMapper::getInstance()->findByColumn('resultset_id', $resultSetId, [ 'order' => [ 'value' => 'desc' ] ]);

                foreach ($results as $result) {
                    foreach ($criteria as $id => $crit) {
                        $descriptors[$id][] = [
                            'text' => $result->getLabel(),
                            'points' => $result->getValue()
                        ];
                        // for legacy support, we leave the name blank
                        $levelList[$result->getValue()] = [ 'name' => '' ];
                    }
                }

            }
        }

        // ensure there are ALWAYS at least 2 levels
        while (count($levelList) < 2) {
            $levelList[] = [ 'name' => '' ];
        }

        // setup the data structure to match what the factory expects
        $postdata = [$box->getId() => [
            'level'      => $levelList,
            'group'      => $groups,
            'criterion'  => $criteria,
            'descriptor' => $descriptors,
        ]];

        // use the factory to produce the rubric object
        return $this->factory->buildFromPostData($postdata, $forceTaskMode || $editMode)->setSubmissionBox($box);
    }


    /**
     * @param Rubric $grid
     * @param array  $selectedDescriptorIds
     * @return Rubric
     */
    public function applyMark($grid, $selectedDescriptorIds)
    {
        foreach ($grid->getAllDescriptors() as $descriptor) {
            $selected = in_array($descriptor->getId(), $selectedDescriptorIds);
            $descriptor->setSelected($selected);
        }

        return $grid;
    }

    /**
     * @param Rubric $grid
     * @param User   $student
     * @return Rubric
     */
    public function applyStudentMark($grid, $student)
    {
        if (!$grid->getSubmissionBox() instanceof SubmissionBox) {
            throw new \InvalidArgumentException("Cannot apply marks without a submission box");
        }

        // retrieve marks (descriptor selections for student)
        $stmt = $this->database->query("
            SELECT descriptor_id
            FROM rubric_mark
            WHERE submission_box_id = :boxId
            AND student_id = :userId
            AND tstamp = (
              SELECT MAX(tstamp)
              FROM rubric_mark
              WHERE submission_box_id = :boxId
              AND student_id = :userId
              AND valid = TRUE
            )
        ", [
            'boxId'  => $grid->getSubmissionBox()->getId(),
            'userId' => $student->getId()
        ]);

        $descriptorIds = [];

        while ($id = $stmt->fetchColumn()) {
            $descriptorIds[$id] = true;
        }

        return $this->applyMark($grid, array_keys($descriptorIds));
    }


    /**
     * Convenience (or safety) method wrapping grid fetch and mark application
     *
     * @param SubmissionBox $box
     * @param User $student
     * @return Rubric
     */
    public function findBySubmissionBoxAndStudent($box, $student)
    {
        $grid = $this->findBySubmissionBox($box, false, false);
        return $this->applyStudentMark($grid, $student);
    }

    /**
     * Normalizes spaces.
     *
     * Trims trailing/leading space, and replaces multiple spaces/newlines/etc.
     * with a single space.
     *
     * @param  string $string
     * @return string
     */
    protected function normalizeSpace($string)
    {
        return trim(preg_replace('`\s+`', ' ', $string));
    }

    protected function getcsv($fp, $limit = 13)
    {
        $line = fgetcsv($fp);

        if (is_null($line)) {
            // EOF
            return null;
        }

        if (is_array($line)) {
            // normal data
            // Normalize line values
            return array_map([$this, 'normalizeSpace'], array_slice($line, 0, $limit));
        }

        // bad data, but not EOF
        return [];
    }

    /**
     * @param IStorage $file
     * @param int      $folderId
     * @param int      $maxLevels
     * @param bool     $taskMode
     * @throws \Schoolbox\Resource\Exception
     * @return Rubric
     */
    public function buildFromCSV(IStorage $file, $folderId, $maxLevels, $taskMode)
    {
        $square = [];

        $folderLookup = [ $folderId ];

        // if we're looking at a task, be observant of the parent folder as well
        if ($taskMode) {
            $folderLookup[] = \Schoolbox\Resource\Folder\Mapper::getInstance()->findCheaplyById($folderId)->getParent()->getId();
        }

        $fp = $file->readStream();

        $headerRow = $this->getcsv($fp, $maxLevels + 2);

        // must be an array, and must have at least 3 "cells"
        if (!is_array($headerRow) || count($headerRow) < 3) {
            throw new \Schoolbox\Resource\Exception("Not a valid rubric CSV file");
        }

        // first 2 "cells" must be blank
        if ($headerRow[0] || $headerRow[1]) {
            throw new \Schoolbox\Resource\Exception("Not a valid rubric CSV file");
        }

        $square[] = $headerRow;

        // list of levels
        $levelNames = array_slice($square[0], 2);

        $workingGroupName = '';

        $criteria = [];

        // build the square, extracting data as we go
        while ($row = $this->getcsv($fp, $maxLevels + 2)) {

            // list of criteria, grouped by... uh, group
            if ($row[0]) {
                $workingGroupName = $row[0];
            }

            if ($row[1]) {

                // copy the groupname into the row
                $row[0] = $workingGroupName;

                // extract the criterion uniquely
                $workingCritName = $row[1];
                $criteria[$workingGroupName][$workingCritName] = [
                    'id'   => null,
                    'name' => $workingCritName
                ];
            }

            $square[] = $row;
        }

        // ensure all given criteria exist: create new ones if necessary
        foreach ($criteria as $group => $crits) {
            foreach ($crits as $crit) {

                $name = $crit['name'];

                try {
                    // if the crit exists, just use that
                    $model = CriterionMapper::getInstance()->findOneByColumnList([
                        'grouping'    => $group,
                        'name'        => $name,
                        'parent_type' => 'category',
                        'parent_id'   => $folderLookup
                    ]);

                } catch (\Schoolbox\Mapper\Exception $e) {

                    // if the crit doesn't exist, create and save
                    $model = CriterionMapper::getInstance()
                                                      ->createModel()
                                                      ->setName($name)
                                                      ->setGrouping($group)
                                                      ->setParentType('category')
                                                      ->setParentId($folderId);

                    CriterionMapper::getInstance()->save($model);
                }

                // yank that ID
                $criteria[$group][$name]['id'] = $model->getId();
            }

        }

        // get a new rubric in array form
        $postdata = $this->findByFolderId($folderId, $taskMode, $levelNames)->toPostData();

        // overlay descriptor data
        for ($i = 1; $i < count($square); $i += 2) {

            $gLookup = $square[$i][0];
            $cLookup = $square[$i][1];

            $crit = $criteria[$gLookup][$cLookup];

            if (!$crit) {
                // given the way we're working with data we've expressly set up just above, this should be impossible...
                continue;
            }

            $critId = $crit['id'];

            for ($j = 2; $j < count($square[$i]); ++$j) {

                $levelSequence = $j - 2;

                $dText = trim($square[$i][$j]);
                $dVal  = trim($square[$i + 1][$j]);

                if (strlen($dText) || strlen($dVal)) {

                    // turn this criterion on
                    $postdata['criterion'][$critId]['on'] = true;

                    // assign the descriptor data to the appropriate cell
                    $postdata['descriptor'][$critId][$levelSequence] = [
                        'id'     => null,
                        'text'   => $dText,
                        'points' => $dVal
                    ];
                }
            }
        }

        return $this->factory->buildFromPostData([ 0 => $postdata ], true);
    }


    /**
     * @param Rubric $rubric
     * @param SubmissionBox $submissionBox
     * @return int
     */
    public function save($rubric, $submissionBox)
    {
        // juuust in case
        $rubric->setSubmissionBox($submissionBox);

        // TODO some way to wrap all this in a transaction would be nice... -_-;

        // first, persist the row and column headers (criteria and levels, respectively)
        CriterionMapper::getInstance()->save($criteria = $rubric->getORMCriteria());
        LevelMapper::getInstance()->save($levels = $rubric->getORMLevels());

        // usages are a join table with a sequence, not a strict "object" - as such, we can purge the relevant records and simply re-create them
        UsageMapper::getInstance()->deleteByColumn('submission_box_id', $submissionBox->getId());
        UsageMapper::getInstance()->save($usages = $rubric->getORMUsages());

        // finally, now that everything else is saved, save the descriptors
        $descIds  = DescriptorMapper::getInstance()->save($descs = $rubric->getORMDescriptors());

        // wash up before dinner, kids

        // remove all levels that have zero descriptors attached - extract from the descriptors
        $seenLevels = [];
        foreach ($descs as $desc) {
            $seenLevels[$desc->getLevel()->getId()] = true;
        }
        $seenLevels   = array_keys($seenLevels);

        $this->tidyUp(LevelMapper::getInstance(), ['box_id' => $submissionBox->getId()], $seenLevels);

        // remove all descriptors that are on this box, but are not among the descriptors we just saved (it indicates blankness / unused-ness)
        $this->tidyUp(DescriptorMapper::getInstance(), ['submission_box_id' => $submissionBox->getId()], $descIds);
    }


    /**
     * @param IMapper $mapper
     * @param array $search
     * @param array $exclude
     * @return Collection
     */
    protected function tidyUp($mapper, $search, $exclude)
    {
        return $mapper->delete(
            $mapper->findByColumnList($search)->filter(
                function ($model) use ($exclude) {
                    return !in_array($model->getId(), $exclude);
                }
            )
        );
    }


    /**
     * @param IMapper    $mapper
     * @param Collection $collection
     * @return array
     */
    protected function foo($mapper, $collection)
    {
        return $mapper->save($collection);
    }

}
