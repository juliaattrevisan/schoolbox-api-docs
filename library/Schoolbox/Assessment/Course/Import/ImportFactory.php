<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Assessment\Course\Import;

use Alaress\IDatabase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkAssessment;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessment;
use Schoolbox\Learning\Assessment\Task\Model as TaskAssessment;
use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Direct DB access to generate a data structure of UnitSpecs, UnitImpls, AssessmentSpecs + AssessmentImpls.
 *
 * Depending on which method is called, results will differ, however the data structure MUST BE the same
 * for all methods.
 *
 * All methods must return an object of the same type
 */
class ImportFactory
{
    /** @var IDatabase */
    private $database;

    /**
     * ImportFactory constructor.
     */
    public function __construct()
    {
        $this->database = \Database::getInstance();
    }

    /**
     * Called during a new import.
     *
     * UnitImpls will all have no id, but will have dates already set.
     * AssessmentImpls will have no id, but will have dates populated according to previous stage
     * All AssessmentImpls will link to an AssessmentSpec
     *
     * @param UnitImplementation[]|GenericCollection $unitImpls Each UnitImpl links to a UnitSpec to get Assessments for
     * @return DataTransferObject
     */
    public function buildForNewImport(GenericCollection $unitImpls)
    {
        /**
         * Extract all UnitSpecs into a cache, indexed by unitSpec.id
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecCache = [];
        $assessmentSpecs = new GenericCollection(GenericCollection::class);
        $assessmentImpls = new GenericCollection(GenericCollection::class);
        $assessmentPairs = new GenericCollection(GenericCollection::class);

        // Instantiate empty sets for each UnitSpecId
        foreach($unitImpls as $unitImpl){
            $courseSpecId = $unitImpl->getSpecification()->getId();
            $unitSpecCache[$courseSpecId] = $unitImpl->getSpecification();
            $assessmentSpecs->setOffset($courseSpecId, new GenericCollection(IAssessable::class));
            $assessmentImpls->setOffset($courseSpecId, new GenericCollection(IAssessable::class));
            $assessmentPairs->setOffset($courseSpecId, new GenericCollection(AssessmentPair::class));
        }


        /**
         * Maintains a list of UnitSpecs encountered while traversing DB query result set.
         * Required so we can set the UnitSpec name once only.
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecDBCache = [];

        $query = "
            SELECT 
                us.id AS 'us.id',
                usf.name AS 'usf.name',
                sa.id AS 'sa.id',
                sa.title AS 'sa.title',
                sdw.assessment_id AS 'sdw.id',
                sdw.accept_submission as 'sdw.accept_submission',
                sdw.multi_submission as 'sdw.multi_submission',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id'
            FROM course_unit_specification us
            LEFT JOIN folder usf ON usf.id = us.folder_id
            LEFT JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            LEFT JOIN assessment sa ON sa.id = aus.assessment_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz ON sqz.assessment_id = sa.id
            LEFT JOIN task stk ON stk.assessment_id = sa.id
        ";

        $pb = new PredicateBuilder($this->database, '');
        $pb->anyOf('us.id', array_keys($unitSpecCache));
        $pb->isNull('sa.deleted_at');
        $query .= 'WHERE' . implode(' AND ', $pb->getWhere());
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());


        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $unitSpecId = $row['us.id'];

            // Is is the first time this UnitSpec has been seen?
            if(!$unitSpecDBCache[$unitSpecId] instanceof UnitSpecification){
                // Add a Folder and name it so we can later render out this UnitSpec's name.
                $unitSpecDBCache[$unitSpecId] = $unitSpecCache[$unitSpecId];
                $folder = new Folder();
                $folder->setName($row['usf.name']);
                $unitSpecCache[$unitSpecId]->setFolder($folder);
            }

            if ($row['sdw.id']) {
                $assessmentSpec = new DueWorkAssessment();
                // Need to know if this accepts submissions, because that
                // determines whether open/late date fields show.
                if ($row['sdw.accept_submission']) {
                    if ($row['sdw.multi_submission']) {
                        $assessmentSpec->setAcceptsMultiple();
                    } else {
                        $assessmentSpec->setAcceptsSingle();
                    }
                } else {
                    $assessmentSpec->setAcceptsNone();
                }
            } elseif ($row['sqz.id']) {
                $assessmentSpec = new QuizAssessment();
            } elseif ($row['stk.id']) {
                $assessmentSpec = new TaskAssessment();
            } else {
                throw new \DomainException("Unknown assessment type: {$row['sa.id']}");
            }
            $assessmentSpec->setId($row['sa.id']);
            $assessmentSpec->setTitle($row['sa.title']);
            $assessmentSpec->setDeletedAt(new \Alaress_Date_NullDate());

            $assessmentSpecs->getOffset($unitSpecId)->add($assessmentSpec);
        }

        // No AssessmentImpl to AssessmentSpec mapping (this is a new import)
        $dto = new DataTransferObject([], $unitImpls, $assessmentSpecs, $assessmentImpls, $assessmentPairs);

        return $dto;
    }



    /**
     * Called during a modification of prior import
     *
     * UnitImpl IDs will be null if not yet imported from UnitSpec.
     * UnitImpls will link to extant UnitSpecs
     *
     * @param UnitImplementation[]|GenericCollection $unitImpls
     * @return DataTransferObject
     */
    public function buildForImportModify(GenericCollection $unitImpls)
    {
        $assessmentSpecs = new GenericCollection(GenericCollection::class);
        $assessmentImpls = new GenericCollection(GenericCollection::class);
        $assessmentPairs = new GenericCollection(GenericCollection::class);

        /**
         * Extract all UnitSpecs into a cache, indexed by unitSpec.id, so we can use their IDs in queries
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecCache = [];

        /**
         * Maintains a list of UnitSpecs encountered while traversing DB query result set.
         * Required so we can populate the UnitSpec name once only.
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecDBCache = [];

        /**
         * IDs of existing UnitImpls. Indexed by UnitSpecId
         *
         * @var int[] $unitImplIds
         */
        $unitImplIds = [];

        // Instantiate empty sets for each UnitSpecId
        foreach($unitImpls as $unitImpl){
            $courseSpecId = $unitImpl->getSpecification()->getId();
            $unitSpecCache[$courseSpecId] = $unitImpl->getSpecification();
            $assessmentSpecs->setOffset($courseSpecId, new GenericCollection(IAssessable::class));
            $assessmentImpls->setOffset($courseSpecId, new GenericCollection(IAssessable::class));
            $assessmentPairs->setOffset($courseSpecId, new GenericCollection(AssessmentPair::class));

            // Is this UnitImpl already imported?
            if($unitImpl->getId()){
                $unitImplIds[$courseSpecId] = $unitImpl->getId();
            }
        }

        // Get the IDs of all non-deleted AssessmentImpls in the specified UnitImpls
        $innerQuery1 = "
            SELECT
              asi.specification_assessment_id AS 'sa.id',
              asi.implementation_assessment_id AS 'ia.id'
            FROM course_unit_implementation `ui_inner`
            JOIN assessment_course_unit_implementation aui ON aui.course_unit_implementation_id = `ui_inner`.id
            JOIN assessment ia ON ia.id = aui.assessment_id
            JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
        ";

        $pbInner1 = new PredicateBuilder($this->database, '');
        $pbInner1->anyOf('ui_inner.id', $unitImplIds);
        $pbInner1->isNull('ia.deleted_at');
        $innerQuery1 .= 'WHERE ' . implode(' AND ', $pbInner1->getWhere());

        // AssessmentSpecs in UnitSpecs, optionally with corresponding AssessmentImpl if it exists
        $query1 = "
            SELECT 
                us.id             AS 'us.id',
                usf.name          AS 'usf.name',
                sa.id             AS 'sa.id',
                sa.title          AS 'sa.title',
                sa.deleted_at     AS 'sa.deleted_at',
                sa.sequence       AS 'sa.sequence',
                sdw.assessment_id AS 'sdw.id',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id',
                ia.id             AS 'ia.id',
                ia.title          AS 'ia.title',
                ia.publish_date   AS 'ia.publish_date',
                ia.open_date      AS 'ia.open_date',
                ia.due_date       AS 'ia.due_date',
                ia.close_date     AS 'ia.close_date',
                ia.sequence       AS 'ia.sequence',
                idw.assessment_id AS 'idw.id',
                iqz.assessment_id AS 'iqz.id',
                itk.assessment_id AS 'itk.id'
            FROM course_unit_specification us
            JOIN folder usf ON usf.id = us.folder_id
            JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            JOIN assessment sa ON sa.id = aus.assessment_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz ON sqz.assessment_id = sa.id
            LEFT JOIN task stk ON stk.assessment_id = sa.id
            LEFT JOIN ($innerQuery1) `t1` ON `t1`.`sa.id` = sa.id
            LEFT JOIN assessment ia ON `t1`.`ia.id` = ia.id
            LEFT JOIN submission_box idw ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz ON iqz.assessment_id = ia.id
            LEFT JOIN task itk ON itk.assessment_id = ia.id
        ";
        $pbOuter1 = new PredicateBuilder($this->database, '');
        $pbOuter1->anyOf('us.id', array_keys($unitSpecCache));
        $query1 .= ' WHERE ' . implode(' AND ', $pbOuter1->getWhere());

        // Get the following Assessments:
        // 5. AssessmentImpls created directly in UnitImpl, no corresponding AssessmentSpec
        $query2 = "
            SELECT
                us.id             AS 'us.id',
                usf.name          AS 'usf.name',
                null              AS 'sa.id',
                null              AS 'sa.title',
                null              AS 'sa.deleted_at',
                null              AS 'sa.sequence',
                null              AS 'sdw.id',
                null              AS 'sqz.id',
                null              AS 'stk.id',
                ia.id             AS 'ia.id',
                ia.title          AS 'ia.title',
                ia.publish_date   AS 'ia.publish_date',
                ia.open_date      AS 'ia.open_date',
                ia.due_date       AS 'ia.due_date',
                ia.close_date     AS 'ia.close_date',
                ia.sequence       AS 'ia.sequence',
                idw.assessment_id AS 'idw.id',
                iqz.assessment_id AS 'iqz.id',
                itk.assessment_id AS 'itk.id'
            FROM course_unit_implementation ui
            JOIN course_unit_specification us ON us.id = ui.course_unit_specification_id
            JOIN folder usf ON usf.id = us.folder_id
            JOIN assessment_course_unit_implementation aui ON aui.course_unit_implementation_id = ui.id
            JOIN assessment ia ON ia.id = aui.assessment_id
            LEFT JOIN submission_box idw ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz ON iqz.assessment_id = ia.id
            LEFT JOIN task itk ON itk.assessment_id = ia.id
            LEFT JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
        ";
        $pbOuter2 = new PredicateBuilder($this->database, '');
        $pbOuter2->isNull('ia.deleted_at');
        $pbOuter2->isNull('asi.implementation_assessment_id');
        $pbOuter2->anyOf('ui.id', $unitImplIds);
        $query2 .= ' WHERE ' . implode(' AND ', $pbOuter2->getWhere());

        $query = "$query1 UNION $query2 ";

        $statement = $this->database->prepare($query);
        $statement->execute(array_merge($pbInner1->getParams(), $pbOuter1->getParams(), $pbOuter2->getParams()));
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $unitSpecId = $row['us.id'];
            // Is is the first time this UnitSpec has been seen?
            if(!$unitSpecDBCache[$unitSpecId] instanceof UnitSpecification){
                // Add a Folder and name it so we can later render out this UnitSpec's name.
                $unitSpecDBCache[$unitSpecId] = $unitSpecCache[$unitSpecId];
                $folder = new Folder();
                $folder->setName($row['usf.name']);
                $unitSpecCache[$unitSpecId]->setFolder($folder);
            }

            // Create AssessmentSpec (if one exists)
            if(is_null($row['sa.id'])){
                $assessmentSpec = null;
            } else {
                if ($row['sdw.id']) {
                    $assessmentSpec = new DueWorkAssessment();
                } elseif ($row['sqz.id']) {
                    $assessmentSpec = new QuizAssessment();
                } elseif ($row['stk.id']) {
                    $assessmentSpec = new TaskAssessment();
                } else {
                    throw new \DomainException("Unknown assessment type: {$row['sa.id']}");
                }
                $assessmentSpec->setId($row['sa.id']);
                $assessmentSpec->setTitle($row['sa.title']);
                if(is_null($row['sa.deleted_at'])){
                    $assessmentSpec->setDeletedAt(new \Alaress_Date_NullDate());
                } else {
                    $assessmentSpec->setDeletedAt($this->createDate($row['sa.deleted_at']));
                }
            }

            // Create corresponding AssessmentImpl (if one exists)
            if(is_null($row['ia.id'])){
                $assessmentImpl = null;
            } else {
                if ($row['idw.id']) {
                    $assessmentImpl = new DueWorkAssessment();
                } elseif ($row['iqz.id']) {
                    $assessmentImpl = new QuizAssessment();
                } elseif ($row['itk.id']) {
                    $assessmentImpl = new TaskAssessment();
                } else {
                    throw new \DomainException("Unknown assessment type: {$row['ia.id']}");
                }
                $assessmentImpl->setId($row['ia.id']);
                $assessmentImpl->setTitle($row['ia.title']);
                $assessmentImpl->setPublishAt($this->createDate($row['ia.publish_date']));
                $assessmentImpl->setOpenAt($this->createDate($row['ia.open_date']));
                $assessmentImpl->setDueAt($this->createDate($row['ia.due_date']));
                $assessmentImpl->setCloseAt($this->createDate($row['ia.close_date']));
                $assessmentImpl->setDeletedAt($this->createDate($row['ia.deleted_at']));
            }

            if($assessmentSpec instanceof IAssessable && $assessmentImpl instanceof IAssessable){
                $assessmentPairs->getOffset($unitSpecId)->add(new AssessmentPair($assessmentSpec, $assessmentImpl));
            } else if($assessmentSpec instanceof IAssessable) {
                // Don't add deleted AssessmentSpecs
                if(!$assessmentSpec->isDeleted()){
                    $assessmentSpecs->getOffset($unitSpecId)->add($assessmentSpec);
                }
            } else {
                $assessmentImpls->getOffset($unitSpecId)->add($assessmentImpl);
            }
        }

        $assessmentImplToSpecMap = $this->getAssessmentImplToSpecMap($unitImplIds);
        $dto = new DataTransferObject($assessmentImplToSpecMap, $unitImpls, $assessmentSpecs, $assessmentImpls, $assessmentPairs);
        return $dto;
    }

    /**
     * Get a map of AssessmentImplIds to AssessmentSpecIds for the specified UnitImpls.
     *
     * AssessmentSpecId will be null if AssessmentImpl was created directly in a UnitImpl
     *
     * DOES include records for deleted Specs.
     * Does NOT include records for deleted Impls.
     *
     * @param int[] $unitImplIds
     * @return int[]
     */
    protected function getAssessmentImplToSpecMap($unitImplIds)
    {
        $query = "
            SELECT
                ia.id AS 'ia.id',
                sa.id AS 'sa.id'
            FROM course_unit_implementation ui
            JOIN assessment_course_unit_implementation aui ON aui.course_unit_implementation_id = ui.id
            JOIN assessment ia ON ia.id = aui.assessment_id AND ia.deleted_at IS NULL
            LEFT JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
            LEFT JOIN assessment sa ON sa.id = asi.specification_assessment_id
        ";
        $pb = new PredicateBuilder($this->database, '');
        $pb->equals('ui.id', $unitImplIds);
        $query .= 'WHERE ' . implode(' AND ', $pb->getWhere());
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());

        $assessmentImplToSpecMap = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $assessmentImplToSpecMap[$row['ia.id']] = $row['sa.id'];
        }
        return $assessmentImplToSpecMap;
    }

    /**
     * @param string $dateString
     * @return \Alaress_Date_Format
     */
    protected function createDate($dateString)
    {
        if(is_null($dateString)){
            return new \Alaress_Date_NullDate();
        } else {
            return new \Alaress_Date_Format($dateString, \Alaress_Date_Format::DATABASE_FORMAT);
        }
    }
}