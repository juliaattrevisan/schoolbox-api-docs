<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress_Date_Format;
use Database;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\Factory;
use Schoolbox\IMapper;
use Schoolbox\Assessment\Rubric\Competency\Level as CompetencyLevel;
use Schoolbox\Assessment\Rubric\Criteria;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Rubric\Rubric as BaseRubric;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

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
     * @param AssessmentContext $context
     * @param array             $levelNames
     * @return Rubric
     */
    public function createForContext(AssessmentContext $context, array $levelNames = [])
    {
        $folderId = $context->getFolder()->getId();
        $parentFolderId = null;

        if ($context instanceof UnitSpecification) {
            $parentFolderId = $context->getFolder()->getParent()->getId();
        }

        return $this->createForFolder($folderId, $parentFolderId, $levelNames);
    }

    /**
     * Generate a base rubric with Indicators (aka Criteria) taken from the specified Folder[/s]
     * @deprecated use of ::createForContext() is preferable
     *
     * @param int $folderId
     * @param int|null $parentFolderId
     * @param string[] $levelNames
     * @return Rubric
     */
    public function createForFolder($folderId, $parentFolderId = null, array $levelNames = [])
    {
        /**
         * Indexed by normalised Capability name
         * @var Criteria\Group[] $capabilityCache
         */
        $capabilityCache = [];

        /**
         * Indexed by normalised Capability name, then normalised Indicator name
         * @var Criteria\Criterion $indicatorCache
         */
        $indicatorCache = [];

        $rubric = new Rubric();

        if (!count($levelNames)) {
            // default to 2 blank levels
            $levelNames = [ '', '' ];
        }

        // inject the levels
        foreach ($levelNames as $i => $level) {
            $rubric->putLevel(new CompetencyLevel($i, $level));
        }

        $args = ['folderId' => $folderId];
        if($parentFolderId){
            $args['parentFolderId'] = $parentFolderId;
        }
        $in = implode(', :', array_keys($args));

        // TODO DI and mapperise
        $stmt = $this->database->query("
            SELECT rc.id, rc.grouping, rc.name, COUNT(ru.submission_box_id) usage_count, rc.parent_id
            FROM rubric_criterion rc
            LEFT JOIN rubric_usage ru ON rc.id = ru.criterion_id
            LEFT JOIN submission_box b ON ru.submission_box_id = b.id  AND (b.deleted IS NOT TRUE OR b.deleted IS NULL)
            WHERE rc.parent_type = 'category'
            AND rc.parent_id IN (:$in)
            AND rc.deleted_at IS NULL
            GROUP BY rc.id
        ", $args);

        $groupId = 21;

        // De-duplicate into caches, prioritising parent if duplicate between
        // folder + parentFolder (eg UnitSpec vs CourseSpec)
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Trim + collapse internal whitespace
            $capabilityNormalisedName = preg_replace('`\s+`', ' ', trim($r['grouping']));

            // If first instance of Capability
            if (!array_key_exists($capabilityNormalisedName, $capabilityCache)) {
                // Save the Capability
                $capabilityCache[$capabilityNormalisedName] = new Criteria\Group($groupId++, $capabilityNormalisedName);

                // Instantiate Indicator cache for the Capability
                $indicatorCache[$capabilityNormalisedName] = [];
            }

            $indicatorNormalisedName = preg_replace('`\s+`', ' ', trim($r['name']));

            // First time we've seen this Capability + Indicator, or Indicator
            // is from the parentFolder (higher priority) ?
            if(!isset($indicatorCache[$capabilityNormalisedName][$indicatorNormalisedName])
            || ($parentFolderId && $parentFolderId == $r['parent_id'])
            ) {
                $indicator = new Criteria\Criterion($r['id'], $indicatorNormalisedName);
                $indicator->setReadOnly($r['usage_count'] > 0);
                $indicatorCache[$capabilityNormalisedName][$indicatorNormalisedName] = $indicator;
            }
        }

        // For each group of Indicators
        foreach($indicatorCache as $capabilityNormalisedName => $indicators){

            // For each Indicator in the Capability
            foreach($indicators as $indicatorNormalisedName => $indicator){
                /** @var Criteria\Criterion $indicator */
                // cascade the read-onlyness up to the Capability
                if ($indicator->isReadOnly()) {
                    $capabilityCache[$capabilityNormalisedName]->setReadOnly(true);
                }
                $rubric->putCriterion($capabilityCache[$capabilityNormalisedName], $indicator);
            }
        }

        return $rubric;
    }


    /**
     * Given a box ID, return the rubric assigned to it, if any
     *
     * Unlike createForContext(), this is a data-level method (ie it works on data models).
     *
     * @param SubmissionBox $box
     * @param bool          $getParentCriteria - whether to include box's folder's parent's criteria
     * @param bool          $editMode          - whether to include criteria not in use on box
     * @return Rubric
     */
    public function findBySubmissionBox($box, $getParentCriteria = false, $editMode = false)
    {
        list ($groups, $criteria) = $this->findCriteria($box, $getParentCriteria);
        $descriptors = $this->findDescriptors($box);
        $levelList   = $this->generateLevelList($box, $descriptors, $criteria);

        // setup the data structure to match what the factory expects
        $postdata = [$box->getId() => [
            'level'      => $levelList,
            'group'      => $groups,
            'criterion'  => $criteria,
            'descriptor' => $descriptors,
        ]];

        // use the factory to produce the rubric object
        /** @var Rubric $rubric */
        $rubric = $this->factory->buildFromPostData($postdata, $getParentCriteria || $editMode);
        return $rubric->setSubmissionBox($box);
    }

    /**
     * Given a DueWork, return the rubric assigned to it, if any
     *
     * @param DueWork $dueWork
     * @param bool          $getParentCriteria - whether to include box's folder's parent's criteria
     * @param bool          $editMode          - whether to include criteria not in use on box
     * @return Rubric
     */
    public function findByDueWork(DueWork $dueWork, $getParentCriteria = false, $editMode = false)
    {
        /** @var SubmissionBox $box */
        $box = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $dueWork->getId());
        return $this->findBySubmissionBox($box, $getParentCriteria, $editMode);
    }


    /**
     * Find all criteria that are available or in use by the $box.
     *
     * We would wish to retrieve parent criteria when the $box lives in a UnitSpec, or project
     * in a UnitSpec. In these cases we need to also retrieve Criteria from the CourseSpec
     *
     * @param SubmissionBox $box
     * @param bool          $getParentCriteria - if specified, also get criteria the box's folder's parent
     * @return array $return
     * @var array  $return[0]                 Groups
     * @var array  $return[0][]               A single Criteria-Group
     * @var string $return[0][]['name']       Name of the Criteria-Group
     * @var array  $return[1]                 Criteria
     * @var array  $return[1][]               A single Criterion
     * @var int    $return[1][]['groupId']    Arbitrary GroupId (not stored in DB, just used to link to a Criteria-Group)
     * @var bool   $return[1][]['on']         Whether this criteria is selected to be used in the $box
     * @var string $return[1][]['name']       Name of Criterion
     * @var bool   $return[1][]['readonly']   Whether user should be prevented from changing name
     */
    protected function findCriteria(SubmissionBox $box, $getParentCriteria)
    {
        // why 20? because it's not 0. (arbitrary start of group indexes)
        $groupId    = 20;

        /** @var array $groupIdMap - indexed by criterion-group name */
        $groupIdMap = [];

        /** @var array $groups - indexed by groupId */
        $groups     = [];

        /** @var array $criteria - indexed by criterion.id */
        $criteria   = [];

        $criteriaStatement = $this->generateCriteriaStatement($box, $getParentCriteria);

        while ($r = $criteriaStatement->fetch(PDO::FETCH_ASSOC)) {

            $groupName = $r['grouping'];

            // First time we've encountered this Group ?
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

        return [ $groups, $criteria ];
    }

    /**
     * Find all criteria that are available for the $box.
     *
     * For each criterion, also get:
     * * how many boxes are using the criteria,
     * * whether the specified box is using the criteria
     *
     * @param SubmissionBox $box
     * @param bool          $getParentCriteria - whether to also get criteria from box's folder's parent
     * @throws \Schoolbox\Mapper\Exception
     * @return \PDOStatement
     */
    protected function generateCriteriaStatement(SubmissionBox $box, $getParentCriteria)
    {
        $args = [
            'boxId'    => $box->getId(),
            'folderId' => $box->getCategory()->getId(),
        ];

        $in = [ 'folderId' ];

        // Retrieve criteria from the box's folder's parent ?
        if($getParentCriteria){

            // Is parent not yet hydrated?
            if (!$box->getCategory()->getParent()) {
                $box->setCategory(FolderMapper::getInstance()->findCheaplyById($box->getCategory()->getId()));
            }

            $key        = 'parentFolderId';
            $args[$key] = $box->getCategory()->getParent()->getId();
            $in[]       = $key;
        }

        $in = implode(', :', $in);

        $criteriaStatement = $this->database->query("
            SELECT
                COUNT(box_id) usages,
                COALESCE(MAX(selected), 0) selected,
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

        return $criteriaStatement;
    }


    /**
     * @param SubmissionBox $box
     * @return array
     */
    protected function findDescriptors(SubmissionBox $box)
    {
        $levelIds = LevelMapper::getInstance()->findIdsByColumn('box_id', $box->getId());

        /** @var Collection|SubmissionBox\Rubric\Descriptor[] $descriptorCollection */
        $descriptorCollection = SubmissionBox\Rubric\DescriptorMapper::getInstance()->findByColumnList([
            'level_id' => $levelIds,
            'valid'    => true
        ]);

        $descriptors = [];

        foreach ($descriptorCollection as $d) {
            // only populate if a description is given
            $descriptors[$d->getCriterion()->getId()][$d->getLevel()->getSequence()] = [
                'id'     => $d->getId(),
                'text'   => $d->getDescription(),
                'points' => $d->getValue() || $d->getDescription() ? $d->getValue() : ''
            ];
        }

        return $descriptors;
    }


    /**
     * @param SubmissionBox $box
     * @param array         $descriptors
     * @param array         $criteria
     * @return array
     */
    protected function generateLevelList(SubmissionBox $box, array $descriptors, array $criteria)
    {
        $levelList = [];

        $levelCollection = LevelMapper::getInstance()->findByColumn('box_id', $box->getId(), [ 'order' => [ 'sequence' => 'ASC' ] ]);

        foreach ($levelCollection as $level) {
            $levelList[$level->getSequence()] = [
                'id'   => $level->getId(),
                'name' => $level->getName()
            ];
        }

        // mokay. so if at this point you've got no descriptors, check to see if there should be a resultSet we should use
        if (count($descriptors) == 0) {
            // misnomer much
            $resultSetId = $box->getRubricResultSet();

            // TODO. Is this still valid in a post 16.5 world?
            // I don't think it's possible to have a resultSet set for a subBox, without having an attached rubric.
            // Instead, the resultSet can be used when creating the rubric, it's never stored against the subBox.
            // If this is correct, the whole IF construct can get deleted.

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

        return $levelList;
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
            SELECT rm.descriptor_id
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            WHERE rl.box_id = :boxId
            AND rm.student_id = :userId
            AND rm.tstamp = (
              SELECT MAX(tstamp)
              FROM rubric_mark rm
              JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id AND rd.valid IS TRUE
              JOIN rubric_level rl ON rl.id = rd.level_id
              WHERE rl.box_id = :boxId
              AND rm.student_id = :userId
              AND rm.valid = TRUE
            )
            AND rm.valid = TRUE
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
        $grid = $this->findBySubmissionBox($box);
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
     * @throws \Schoolbox\Resource\Exception
     * @return Rubric
     */
    public function buildFromCSV(IStorage $file, $folderId, $maxLevels)
    {
        $square = [];

        $folderLookup = [ $folderId ];

        // Determine if the specified folder has a UnitSpec
        /** @var UnitSpecification $unitSpec */
        $unitSpec = UnitSpecificationMapper::getInstance()->findByColumn('folder_id', $folderId)->first();
        if($unitSpec){
            // Add the Unit's Course's Folder's ID
            $folderLookup[] = $unitSpec->getCourse()->getFolder()->getId();
        }

        $fp = $file->readStream();

        $headerRow = $this->getcsv($fp, $maxLevels + 2);

        // must be an array, and must have at least 3 "cells"
        if (!is_array($headerRow) || count($headerRow) < 3) {
            throw new \Schoolbox\Resource\Exception("Not a valid rubric CSV file");
        }

        // first 2 "cells" must be blank
        if ($headerRow[0] || $headerRow[1]) {
            //Excel was getting rid of levels that are not set so lets try and repair
            $headerRow = array_fill(0, count($headerRow),'');
            rewind($fp);
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
        $postdata = $this->createForFolder($folderLookup[0], $folderLookup[1], $levelNames)->toPostData();

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

        /** @var Rubric $rubric */
        $rubric = $this->factory->buildFromPostData([ 0 => $postdata ], true);
        return $rubric;
    }

    /**
     * Save rubrics selections for a particular Participant
     *
     * @param Participant $participant
     * @param $rubricVars
     */
    public function saveForParticipant(Participant $participant, $rubricVars)
    {
        /* Optional rubric
         */
        // If the mark gets passed at the same time as rubrics,
        // then rubrics get clobbered. We don't want that.
        if (is_array($rubricVars)) {

            /* Find the submission box instance
             */
            try {
                /** @var SubmissionBox $submissionBox */
                $submissionBox = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $participant->getAssessable()->getId());

                $descriptorIds = [];

                foreach ($rubricVars as $criterionId => $levelId) {
                    // do this the least expensive way Mappers make possible (a custom query would improve this... just saying...)
                    $descriptorIds[] = DescriptorMapper::getInstance()->findIdsByColumnList(
                        [
                            'criterion_id'      => $criterionId,
                            'level_id'          => $levelId
                        ]
                    )[0];
                }

                $grid = $this->findBySubmissionBox($submissionBox);
                $this->applyMark($grid, $descriptorIds);

                $marks = $grid->getORMMarks($participant->getUser(), \Alaress_Date_Format::now());
                SubmissionBox\Rubric\MarkMapper::getInstance()->save($marks);
            }
            catch (\Schoolbox\Mapper\Exception $e) {

                /* noop (submission box not found)
                 */
            }
        }
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
        $levelIds = LevelMapper::getInstance()->findIdsByColumn('box_id', $submissionBox->getId(), ['deleted' => true]);
        $this->tidyUp(DescriptorMapper::getInstance(), ['level_id' => $levelIds], $descIds);
    }


    /**
     * @param IMapper $mapper
     * @param array $search
     * @param array $exclude
     * @return bool
     */
    protected function tidyUp($mapper, $search, $exclude)
    {
        return $mapper->delete(
            $mapper->findByColumnList($search)->filter(
                function ($model) use ($exclude) {
                    /** @var Descriptor|Level $model */
                    return !in_array($model->getId(), $exclude);
                }
            )
        );
    }


    /**
     * Specialised query for returning the longitudinal cross-section of rubric data for a
     * student in a subject, optionally date-restricted according to the DUE date of the
     * assessment. Data is returned in a primitive hierarchy of:
     *   - capability
     *   - indicator
     *   - assessment
     *   - descriptor (student result)
     *
     * @param Folder              $subject
     * @param User                $student
     * @param Alaress_Date_Format $noEarlierThan
     * @param Alaress_Date_Format $noLaterThan
     * @return array
     */
    public function getLongitudinal(Folder $subject, User $student, Alaress_Date_Format $noEarlierThan = null, Alaress_Date_Format $noLaterThan = null)
    {
        $pb = new PredicateBuilder($this->database, '');

        $pb->equals('rc.parent_type', 'category')
           ->equals('rc.parent_id', $subject->getId())
           ->equals('rm.student_id', $student->getId())
           ->isNull('rc.deleted_at');

        if ($noEarlierThan) {
            $pb->notLessThan('a.due_date', $noEarlierThan->getDatabase());
        }

        if ($noLaterThan && !$noLaterThan instanceof \Alaress_Date_NullDate) {
            $pb->notGreaterThan('a.due_date', $noLaterThan->getDatabase());
        }

        $where = implode(' AND ', $pb->getWhere());

        $stmt = $this->database->prepare("
            SELECT rc.grouping, rc.name, a.id, a.title, rd.description
            FROM rubric_criterion rc
            JOIN rubric_usage ru ON rc.id = ru.criterion_id
            JOIN submission_box sb ON ru.submission_box_id = sb.id
            JOIN assessment a ON sb.assessment_id = a.id
            JOIN rubric_level rl ON sb.id = rl.box_id
            JOIN rubric_descriptor rd ON rd.level_id = rl.id AND rd.criterion_id = rc.id AND rd.valid IS TRUE
            JOIN rubric_mark rm ON rm.descriptor_id = rd.id AND rm.valid IS TRUE
            WHERE {$where}
            ORDER BY rc.grouping ASC, rc.name ASC, a.due_date DESC, rm.tstamp
        ");
        // Note that this will return all marks for the given student and subject through time, but because we
        // have ordered by mark.timestamp, the latter values will overwrite previous marks.

        $stmt->execute($pb->getParams());

        $longitude = [];

        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

            list ($capability, $indicator, $assessmentId, $assessmentTitle, $descriptor) = $r;

            $longitude[$capability]['name'] = $capability;
            $longitude[$capability]['indicators'][$indicator]['name'] = $indicator;
            $longitude[$capability]['indicators'][$indicator]['activities'][$assessmentId] = [
                'id'         => $assessmentId,
                'name'       => $assessmentTitle,
                'descriptor' => $descriptor,
            ];

        }

        return $longitude;
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
