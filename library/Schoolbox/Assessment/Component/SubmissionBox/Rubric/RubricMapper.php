<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Database;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\Competency\Level as CompetencyLevel;
use Schoolbox\Assessment\Rubric\Criteria;
use Schoolbox\Assessment\Rubric\Factory;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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
     * @param AssessmentContext $context
     * @param string[]          $levelNames
     * @return Rubric
     */
    public function createForContext(AssessmentContext $context, array $levelNames = [])
    {
        // Go one deeper to get unit and course
        if ($context instanceof Project) {
            return $this->createForContext($context->getBaseContext(), $levelNames);
        }

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
            $capabilityNormalisedName = $this->normaliseForComparison($r['grouping']);

            // If first instance of Capability
            if (!array_key_exists($capabilityNormalisedName, $capabilityCache)) {
                // Save the Capability
                $capabilityCache[$capabilityNormalisedName] = new Criteria\Group($groupId++, $this->normalizeSpace($r['grouping']));

                // Instantiate Indicator cache for the Capability
                $indicatorCache[$capabilityNormalisedName] = [];
            }

            $indicatorNormalisedName = $this->normaliseForComparison($r['name']);

            // First time we've seen this Capability + Indicator, or Indicator
            // is from the parentFolder (higher priority) ?
            if(!isset($indicatorCache[$capabilityNormalisedName][$indicatorNormalisedName])
            || ($parentFolderId && $parentFolderId == $r['parent_id'])
            ) {
                $indicator = new Criteria\Criterion($r['id'], $this->normalizeSpace($r['name']));
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
    public function findBySubmissionBox(SubmissionBox $box, $getParentCriteria = false, $editMode = false)
    {
        list ($groups, $criteria) = $this->findCriteria($box, $getParentCriteria);
        $descriptors = $this->findDescriptors($box);
        $levelList   = $this->generateLevelList($box);

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
     * @param DueWork\Model $dueWork
     * @param bool          $getParentCriteria - whether to include box's folder's parent's criteria
     * @param bool          $editMode          - whether to include criteria not in use on box
     * @return Rubric
     */
    public function findByDueWork(DueWork\Model $dueWork, $getParentCriteria = false, $editMode = false)
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
                MAX(`sequence`) sequence,
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

        /** @var Collection|Descriptor[] $descriptorCollection */
        $descriptorCollection = DescriptorMapper::getInstance()->findByColumnList(['level_id' => $levelIds]);

        $descriptors = [];

        foreach ($descriptorCollection as $d) {
            $descriptors[$d->getCriterion()->getId()][$d->getLevel()->getSequence()] = [
                'id'     => $d->getId(),
                'text'   => $d->getDescription(),
                'points' => $d->getValue(),
            ];
        }

        return $descriptors;
    }


    /**
     * @param SubmissionBox $box
     * @return array
     */
    protected function generateLevelList(SubmissionBox $box)
    {
        $levelList = [];

        $levelCollection = LevelMapper::getInstance()->findByColumn('box_id', $box->getId(), [ 'order' => [ 'sequence' => 'ASC' ] ]);

        foreach ($levelCollection as $level) {
            $levelList[$level->getSequence()] = [
                'id'   => $level->getId(),
                'name' => $level->getName()
            ];
        }

        // ensure there are ALWAYS at least 2 levels
        while (count($levelList) < 2) {
            $levelList[] = [ 'name' => '' ];
        }

        return $levelList;
    }


    /**
     * @param Rubric $grid
     * @param int[]  $selectedDescriptorIds
     * @return Rubric
     */
    public function applyMark(Rubric $grid, array $selectedDescriptorIds)
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
     * @param bool   $publishedOnly       Whether to exclude draft marks
     * @return Rubric
     */
    public function applyStudentMark(Rubric $grid, User $student, $publishedOnly)
    {
        if (!$grid->getSubmissionBox() instanceof SubmissionBox) {
            throw new \InvalidArgumentException("Cannot apply marks without a submission box");
        }

        $publishedClause = $publishedOnly ? 'AND rm.published_at IS NOT NULL' : '';

        // retrieve marks (descriptor selections for student)
        $stmt = $this->database->query("
            SELECT rm.descriptor_id
            FROM rubric_mark rm
            JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
            JOIN rubric_level rl ON rl.id = rd.level_id
            LEFT JOIN submission_return sr ON sr.box_id = :boxId AND sr.owner = :userId
            WHERE rm.valid = TRUE
            $publishedClause
            AND rm.created_at = (
              SELECT MAX(rm.created_at)
              FROM rubric_mark rm
              JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
              JOIN rubric_level rl ON rl.id = rd.level_id
              LEFT JOIN submission_return sr ON sr.box_id = :boxId AND sr.owner = :userId
              WHERE rm.valid = TRUE
              $publishedClause
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
    public function findBySubmissionBoxAndStudent(SubmissionBox $box, User $student)
    {
        $grid = $this->findBySubmissionBox($box);
        return $this->applyStudentMark($grid, $student, true);
    }

    /**
     * Normalises spaces.
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


    /**
     * Converts a Criterion name for comparison. Do NOT use for mutation before storage.
     *
     * * converts to lowercase
     * * Replaces multiple whitespace with single space character
     * * trims whitespace
     *
     * @param $string
     * @return string
     */
    public function normaliseForComparison($string)
    {
        return strtolower($this->normalizeSpace($string));
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
     * @param Participant                $participant
     * @param bool[]                     $descriptors key = descriptor.id, val = whether it is checked
     * @param bool                       $isDraft     Whether these are draft marks
     * @param int                        $returnId
     */
    public function saveForParticipant(Participant $participant, array $descriptors = [], $isDraft, $returnId)
    {
        $descriptorMapper   = DescriptorMapper::getInstance();
        $markMapper         = MarkMapper::getInstance();
        $publishedAt        = $isDraft ? new \Alaress_Date_NullDate() : Alaress_Date_Format::now();

        $returnedWorkMapper = SubmissionBox\ReturnedWorkMapper::getInstance();

        // Blow away any previous draft marks for this student and assessment
        $markMapper->deleteDraftMarks($participant->getUser(), $participant->getAssessable());

        $marksToSave = $markMapper->createCollection();

        foreach($descriptors as $descriptorId => $isChecked){
            if(!$isChecked){
                continue;
            }

            $marksToSave->add(
                $markMapper->createModel()
                    ->setSubmissionReturn($returnedWorkMapper->createModel($returnId))
                    ->setStudent($participant->getUser())
                    ->setDescriptor($descriptorMapper->createModel($descriptorId))
                    ->setPublishedAt($publishedAt)
                    ->setValid(true)
            );
        }
        $markMapper->save($marksToSave);
    }

    /**
     * @param Rubric $rubric
     * @param SubmissionBox $submissionBox
     */
    public function save(Rubric $rubric, SubmissionBox $submissionBox)
    {
        // juuust in case
        $rubric->setSubmissionBox($submissionBox);

        // TODO some way to wrap all this in a transaction would be nice... -_-;
        $existingLevelIds = LevelMapper::getInstance()->findIdsByColumn('box_id', $submissionBox->getId());
        $existingDescriptors = DescriptorMapper::getInstance()->findByColumn('level_id', $existingLevelIds);
        $existingDescriptors = $existingDescriptors->map(function(Descriptor $d){return clone $d;});

        // first, persist the row and column headers (criteria and levels, respectively)
        CriterionMapper::getInstance()->save($criteria = $rubric->getORMCriteria());
        LevelMapper::getInstance()->save($levels = $rubric->getORMLevels());

        // usages are a join table with a sequence, not a strict "object" - as such, we can purge the relevant records and simply re-create them
        UsageMapper::getInstance()->deleteByColumn('submission_box_id', $submissionBox->getId());
        UsageMapper::getInstance()->save($usages = $rubric->getORMUsages());

        // finally, now that everything else is saved, save the descriptors
        $descIds  = DescriptorMapper::getInstance()->save($descs = $rubric->getORMDescriptors());

        $this->checkDescriptorValueChanges($existingDescriptors, $descs);

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
     * Soft-delete any rubric levels used by the specified submission boxes if they
     * no longer contain any non-deleted descriptors.
     *
     * Returns the count of levels soft-deleted.
     *
     * @param int[] $submissionBoxIds
     * @return int
     */
    public function deleteEmptyLevels(array $submissionBoxIds)
    {
        if (!count($submissionBoxIds)) {
            return 0;
        }

        $levelMapper       = LevelMapper::getInstance();
        $pb                = $levelMapper->getPredicateBuilder()->equals('rl.box_id', $submissionBoxIds);
        $boxIdPlaceholders = $pb->getWhere()[0];

        $query = "
            SELECT rl.id FROM rubric_level rl
            LEFT JOIN (SELECT id, level_id FROM rubric_descriptor WHERE deleted_at IS NULL) rd ON rd.level_id = rl.id
            WHERE rd.id IS NULL
            AND rl.deleted_at IS NULL
            AND {$boxIdPlaceholders};
        ";

        $statement = $this->database->query($query, $pb->getParams());
        $levelIds  = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $levelMapper->deleteByColumn($levelMapper->getIdentifierColumn(), $levelIds);
    }


    /**
     * Invalidates selected student marks if already given for this rubric and the rubric has changed.
     *
     * * If an existing descriptor has had its value changed then invalidate all marks for the rubric.
     * * If an indicator is added, do nothing.
     * * If an indicator is removed, invalidate marks for that indicator.
     *
     * @param Collection|Descriptor[] $existingDescriptors
     * @param Collection|Descriptor[] $newDescriptors The newly saved descriptors
     * @return bool Did we invalidate any descriptors?
     */
    protected function checkDescriptorValueChanges(Collection $existingDescriptors, Collection $newDescriptors)
    {
        // Group descriptors by criterionId, then LevelId, to enable O(1) lookup
        $criteriaCallback = function(Descriptor $descriptor){
            return $descriptor->getCriterion()->getId();
        };
        /** @var Collection|Descriptor[][] $existingCriteriaDescriptors */
        $existingCriteriaDescriptors = $existingDescriptors->groupByCallback($criteriaCallback);
        /** @var Collection|Descriptor[][] $newCriteriaDescriptors */
        $newCriteriaDescriptors = $newDescriptors->groupByCallback($criteriaCallback);

        $levelsCallback = function(Descriptor $descriptor){
            return $descriptor->getLevel()->getId();
        };
        foreach($existingCriteriaDescriptors as $existingCriterionDescriptors){
            $existingCriterionDescriptors->indexByCallback($levelsCallback);
        }
        foreach($newCriteriaDescriptors as $newCriterionDescriptors){
            $newCriterionDescriptors->indexByCallback($levelsCallback);
        }

        // Have any descriptor values been updated ? (but not completely deleted)
        $hasDescriptorValuesChanged = false;
        $deletedDescriptors = DescriptorMapper::getInstance()->createCollection();
        foreach($existingCriteriaDescriptors as $criterionId => $existingCriterionDescriptors)
        {
            /** @var Collection $newCriterionDescriptors */
            $newCriterionDescriptors = $newCriteriaDescriptors->getOffset($criterionId);

            // Have we deleted this criterion?
            if(!$newCriterionDescriptors){
                // Make a note of them, we may need you later...
                $deletedDescriptors = $deletedDescriptors->merge($existingCriterionDescriptors);
                continue;
            }

            // Examine each descriptor in this criterion
            foreach($existingCriterionDescriptors as $levelId => $existingDescriptor){
                /** @var Descriptor $newDescriptor */
                $newDescriptor = $newCriterionDescriptors->getOffset($levelId);

                // Has the descriptor been deleted?
                if(!$newDescriptor){
                    $hasDescriptorValuesChanged = true;
                    break;
                }

                // Has the value changed ?
                $existingValue = $existingDescriptor->getValue();
                $newValue      = $newDescriptor->getValue();
                if($existingValue != $newValue){
                    $hasDescriptorValuesChanged = true;
                    break;
                }
            }
        }

        // Did we detect a value change?
        if($hasDescriptorValuesChanged){
            $this->invalidateDescriptorMarks($existingDescriptors);
            return true;
        }

        // Did we delete any criterion?
        if($deletedDescriptors->count()){
            $this->invalidateDescriptorMarks($deletedDescriptors);
            return true;
        }
        return false;
    }


    /**
     * Invalidates all marks given for the specified descriptors
     *
     * @param Collection|Descriptor[] $descriptors
     */
    protected function invalidateDescriptorMarks(Collection $descriptors)
    {
        $markMapper = MarkMapper::getInstance();
        $descriptorIds = $descriptors->getColumnValues('id');
        $columnList = [
            'descriptor_id' => $descriptorIds,
            'valid'         => true,
        ];
        $marks = $markMapper->findByColumnList($columnList);
        foreach($marks as $mark){
            $mark->setValid(false);
        }
        $markMapper->save($marks);
    }


    /**
     * @param IMapper $mapper
     * @param array $search
     * @param array $exclude
     * @return bool
     */
    protected function tidyUp(IMapper $mapper, array $search, array $exclude)
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
     * student and set of DueWorks. Data is returned in a primitive hierarchy of:
     *   - capability
     *   - indicator
     *   - assessment
     *   - descriptor (student result)
     *
     * @param GenericCollection|DueWork\Model[] $dueWorks
     * @param User                              $student
     * @return array $longitude
     * @var array  $longitude[$capability]
     * @var string $longitude[$capability]['name']
     * @var array  $longitude[$capability][$indicator]
     * @var string $longitude[$capability][$indicator]['name']
     * @var array  $longitude[$capability][$indicator]['activities']
     * @var array  $longitude[$capability][$indicator]['activities'][$assessmentId]
     * @var int    $longitude[$capability][$indicator]['activities'][$assessmentId]['id']
     * @var string $longitude[$capability][$indicator]['activities'][$assessmentId]['name']
     * @var string $longitude[$capability][$indicator]['activities'][$assessmentId]['descriptor']
     */
    public function getLongitudinal(GenericCollection $dueWorks, User $student)
    {
        if(0 === $dueWorks->count()){
            return [];
        }

        $pb = new Builder($this->database, '');

        $pb
            ->equals('sb.assessment_id', $dueWorks->getColumnValues('id'))
            ->equals('sr.owner', $student->getId())
            ->notNull('rm.published_at')
            ->isTrue('rm.valid')
            ->isNull('rd.deleted_at')
            ->isNull('rl.deleted_at')
            ->isNull('rc.deleted_at')
            ->isNull('a.deleted_at');

        $where = implode(' AND ', $pb->getWhere());

        // While MariaDB appears to return the desired value in a column not in a group aggregate function, this
        // behaviour is NOT guaranteed, the value in such cases is officially indeterminate.

        // We have two implementation options here:
        // 1. Get more results than we really want, sort in order so that the last per group is
        //    the only one we want, then discard some results during processing, or
        // 2. More complex query to only get results we want

        // While the former is simpler, it's too fragile and will break if someone changes the sorting.
        // The below query is complex, but is the standard pattern:
        // see http://stackoverflow.com/questions/7745609/sql-select-only-rows-with-max-value-on-a-column

        $stmt = $this->database->prepare("
            SELECT rc.grouping, rc.name, a.id, a.title, rd.description
            FROM assessment         a
            JOIN submission_box    sb ON  a.id = sb.assessment_id
            JOIN rubric_level      rl ON sb.id = rl.box_id
            JOIN rubric_descriptor rd ON rl.id = rd.level_id
            JOIN rubric_criterion  rc ON rc.id = rd.criterion_id
            JOIN rubric_mark       rm ON rd.id = rm.descriptor_id
            JOIN submission_return sr ON sr.id = rm.return_id
            JOIN (
                SELECT a.id AS 'a.id', rc.id AS 'rc.id', max(rm.published_at) AS 'rm.max_published_at'
                FROM rubric_mark       rm
                JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
                JOIN rubric_criterion  rc ON rc.id = rd.criterion_id
                JOIN rubric_level      rl ON rl.id = rd.level_id
                JOIN submission_box    sb ON sb.id = rl.box_id
                JOIN assessment         a ON  a.id = sb.assessment_id
                JOIN submission_return sr ON sr.id = rm.return_id
                WHERE {$where}
                GROUP BY sb.assessment_id, rd.criterion_id
            ) t ON `a`.`id` = `t`.`a.id` AND `rc`.`id` = `t`.`rc.id` AND `rm`.`published_at` = `t`.`rm.max_published_at`
            WHERE {$where}
            ORDER BY rc.grouping ASC, rc.name ASC, a.due_date DESC;
        ");

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
     * Copies a Rubric from source DueWork to destination DueWork.
     *
     * Overall logic is to build the source rubric object graph, then clone and clear all IDs.
     *
     * If during traversal we detect that the destination folder already has a criterion with the same
     * name, we use the destination criterion ID to avoid insertion of a duplicate.
     *
     * Logically:
     * * Levels are copied from srcBox to dstBox
     * * Descriptors are copied from srcBox to dstBox
     * * Criterion only copied from src folder to dst folder (if not already present on dst)
     * * Usages are copied from srcBox + srcCriterion to dstBox + dstCriterion
     *
     * @param int $sourceAssessmentId
     * @param int $destinationAssessmentId
     * @return bool Whether a new Rubric was created.
     */
    public function copyRubric($sourceAssessmentId, $destinationAssessmentId)
    {
        // Get required mappers
        $boxMapper        = SubmissionBoxMapper::getInstance();
        $levelMapper      = LevelMapper::getInstance();
        $descriptorMapper = DescriptorMapper::getInstance();
        $criterionMapper  = CriterionMapper::getInstance();
        $usageMapper      = UsageMapper::getInstance();

        $sourceBox = $boxMapper->findOneByColumn('assessment_id', $sourceAssessmentId);

        /** @var Level[]|GenericCollection $srcLevels */
        $srcLevels = $levelMapper->findByColumn('box_id', $sourceBox->getId())->indexBy('id');

        // If no levels, the source box does not have a rubric. Bug out.
        if(!count($srcLevels)){
            return false;
        }

        $dstBox = $boxMapper->findOneByColumn('assessment_id', $destinationAssessmentId);

        // Cannot mutate identity of objects returned from mappers.
        // Index all these collections by source version ID, even though the object will be destination version.
        /** @var Level[]|GenericCollection $dstLevels */
        $dstLevels      = $levelMapper->createCollection();
        /** @var Descriptor[]|GenericCollection $dstDescriptors */
        $dstDescriptors = $descriptorMapper->createCollection();
        /** @var Criterion[]|GenericCollection $dstCriteria */
        $dstCriteria    = $criterionMapper->createCollection();
        /** @var Usage[]|GenericCollection $dstUsages */
        $dstUsages      = $usageMapper->createCollection();

        // Copy Levels
        foreach($srcLevels as $srcLevel){
            $dstLevel = clone $srcLevel;
            $dstLevel->setId(null);
            $dstLevel->setSubmissionBox($dstBox);
            $dstLevel->setDescriptors($descriptorMapper->createCollection());
            $dstLevels->setOffset($srcLevel->getId(), $dstLevel);
        }

        // Get Source Descriptors
        /** @var Descriptor[]|GenericCollection $srcDescriptors */
        $srcDescriptors = $descriptorMapper->findByColumn('level_id', $srcLevels->getColumnValues('id'))->indexBy('id');
        $srcCriteria = $criterionMapper->createCollection();

        /* Get existing destination criteria.
         *
         * If during destination object graph build, we detect that a source descriptor
         * already exists on destination, use the destination version instead of creating a new criterion
         *
         * For now, need to by-pass cache, as we may have inserted criteria during previous call to this function.
         * //TODO - the mappers should be invalidating the SELECT cache on INSERT/UPDATE @see SA-1951
         */
        /** @var Descriptor $firstSrcDescriptor */
        $firstSrcDescriptor = $srcDescriptors->first();
        $existingCriteria = $criterionMapper->findByColumnList([
            'parent_id'   => $dstBox->getCategory()->getId(),
            'parent_type' => $firstSrcDescriptor->getCriterion()->getParentType(),
        ], ['cache' => false]);
        // Convert to 2-D arrays, indexed by 'group' then 'name', so we can later find duplicates.
        /** @var Criterion[][] $existingGroupMap */
        $existingGroupMap = [];
        foreach($existingCriteria as $existingCriterion){
            $group = $this->normaliseForComparison($existingCriterion->getGrouping());
            $name  = $this->normaliseForComparison($existingCriterion->getName());
            $existingGroupMap[$group][$name] = $existingCriterion;
        }


        // Build new Rubric for destination box
        foreach($srcDescriptors as $srcDescriptor){

            // Copy source Criterion or re-use existing Criterion
            $srcCriterionId = $srcDescriptor->getCriterion()->getId();

            // First time we've traversed this Criterion ?
            if(!$srcCriteria->getOffset($srcCriterionId)){

                $srcCriterion = $srcDescriptor->getCriterion();
                $srcCriteria->setOffset($srcCriterionId, $srcCriterion);

                // Does this Criterion already exist on destination folder ?

                $duplicate = false;
                $group = $this->normaliseForComparison($srcCriterion->getGrouping());
                $name  = null;
                if(array_key_exists($group, $existingGroupMap)){
                    $name = $this->normaliseForComparison($srcCriterion->getName());
                    if(array_key_exists($name, $existingGroupMap[$group])){
                        $duplicate = true;
                    }
                }

                // Did we find an existing Criterion with the same name?
                if($duplicate){
                    $dstCriterion = $existingGroupMap[$group][$name];
                } else {
                    $dstCriterion = clone $srcDescriptor->getCriterion();
                    $dstCriterion->setId(null);
                    $dstCriterion->setParentId($dstBox->getCategory()->getId());
                }
                $dstCriteria->setOffset($srcCriterionId, $dstCriterion);
            }

            // Descriptor
            $dstDescriptor = clone $srcDescriptor;
            $dstDescriptor->setId(null);
            $dstDescriptors->setOffset($srcDescriptor->getId(), $dstDescriptor);

            // Link Descriptor + Level
            /** @var Level $dstLevel */
            $dstLevel = $dstLevels->getOffset($srcDescriptor->getLevel()->getId());
            $dstDescriptor->setLevel($dstLevel);
            $dstLevel->getDescriptors()->setOffset($srcDescriptor->getId(), $dstDescriptor);

            // Link Descriptor + Criterion
            $dstDescriptor->setCriterion($dstCriteria->getOffset($srcCriterionId));
        }

        // Get Source Usages
        /** @var Usage[]|GenericCollection $srcUsages */
        $srcUsages = $usageMapper->findByColumnList([
            'criterion_id'      => $srcCriteria->getColumnValues('id'),
            'submission_box_id' => $sourceBox->getId(),
        ]);

        // Copy Usages
        foreach($srcUsages as $srcUsage){
            $dstUsage = clone $srcUsage;

            /** @var Criterion $dstCriterion */
            $dstCriterion = $dstCriteria->getOffset($srcUsage->getCriterion()->getId());

            $dstUsage->setCriterion($dstCriterion);
            $dstUsage->setSubmissionBox($dstBox);
            $dstUsages->add($dstUsage);
        }

        // Save (order is important to ensure IDs are assigned to parents b4 saving children)
        $levelMapper->save($dstLevels);
        $criterionMapper->save($dstCriteria);
        $descriptorMapper->save($dstDescriptors);
        $usageMapper->save($dstUsages);
        return true;
    }
}
