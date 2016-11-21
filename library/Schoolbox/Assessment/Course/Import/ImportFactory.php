<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Assessment\Course\Import;

use Alaress\IDatabase;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkAssessment;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessment;
use Schoolbox\Learning\Assessment\Task\Model as TaskAssessment;

use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Assessment\Course\Task as Task;

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
     * @param UnitImplementation[] $unitImpls Each UnitImpl links to a UnitSpec to get Assessments for
     * @return DataTransferObject
     */
    public function buildForNewImport($unitImpls)
    {
        // No AssessmentImpl to AssessmentSpec mapping (this is a new import)
        $dto = new DataTransferObject([]);

        /**
         * Extract all UnitSpecs into a cache, indexed by unitSpec.id
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecCache = [];

        /**
         * Maintains a list of UnitSpecs encountered while traversing DB query result set.
         * Required so we can set the UnitSpec name once only.
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecDBCache = [];

        // Add our proposed UnitImpl objects, each linked to an UnitSpec (which may be deleted)
        foreach ($unitImpls as $unitImpl) {
            $dto->addUnitImplementation($unitImpl);
            $unitSpecCache[$unitImpl->getSpecification()->getId()] = $unitImpl->getSpecification();
        }


        $query = "
            SELECT 
                us.id AS 'us.id',
                usf.name AS 'usf.name',
                sa.id AS 'sa.id',
                sa.title AS 'sa.title',
                sdw.assessment_id AS 'sdw.id',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id'
            FROM course_unit_specification us
            LEFT JOIN folder usf ON usf.id = us.folder_id
            LEFT JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            LEFT JOIN assessment sa ON sa.id = aus.assessment_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz ON sqz.assessment_id = sa.id
            LEFT JOIN task stk ON stk.assessment_id = sa.id
            WHERE  us.id IN (" . implode(',', array_keys($unitSpecCache)) . ") AND sa.deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();


        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            // Is is the first time this UnitSpec has been seen?
            if(!$unitSpecDBCache[$row['us.id']] instanceof UnitSpecification){
                // Add a Folder and name it so we can later render out this UnitSpec's name.
                $unitSpecDBCache[$row['us.id']] = $unitSpecCache[$row['us.id']];
                $folder = new Folder();
                $folder->setName($row['usf.name']);
                $unitSpecCache[$row['us.id']]->setFolder($folder);
            }

            if ($row['sdw.id']) {
                $assessment = new DueWorkAssessment();
            } elseif ($row['sqz.id']) {
                $assessment = new QuizAssessment();
            } elseif ($row['stk.id']) {
                $assessment = new TaskAssessment();
            } else {
                throw new \DomainException("Unknown assessment type: {$row['sa.id']}");
            }
            $assessment->setId($row['sa.id']);
            $assessment->setTitle($row['sa.title']);
            $assessment->setDeletedAt(new \Alaress_Date_NullDate());

            $dto->addAssessmentSpec($unitSpecCache[$row['us.id']], $assessment);
        }

        return $dto;
    }

    /**
     * Called during a modification of prior import
     *
     * UnitImpl + AssessmentImpl will be non-null if already imported.
     *
     * @param UnitImplementation[] $unitImpls Each UnitImpl links to a UnitSpec to get Assessments for
     * @return DataTransferObject
     */
    public function buildForImportModify(array $unitImpls)
    {

        /**
         * Extract all UnitSpecs into a cache, indexed by unitSpec.id, so we can use their IDs in queries
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitImplCache = [];
        $unitSpecCache = [];
        foreach($unitImpls as $unitImpl){
            $unitImplCache[$unitImpl->getId()] = $unitImpl;
            $unitSpecCache[$unitImpl->getSpecification()->getId()] = $unitImpl->getSpecification();
        }

        /**
         * Maintains a list of UnitSpecs encountered while traversing DB query result set.
         * Required so we can set the UnitSpec name once only.
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecDBCache = [];


        /**
         * Maps each AssessmentImplId to an AssessmentSpecId.
         * SpecId will be null if Impl was created in UnitImpl.
         * Array will be empty if no UnitImps, or if no Assessments imported to UnitImps.
         *
         * @var int[]
         */
        $assessmentImplToSpecMap = [];
        if (count($unitImpls)) {
            // We DO want to retrieve records for deleted Specs.
            // We DO NOT want to retrieve records for deleted Impls.
            $query = "
                SELECT 
                    aui.assessment_id               AS 'ia.id',
                    aui.assessment_specification_id AS 'sa.id'
                FROM course_unit_specification us
                JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
                JOIN assessment_course_unit_implementation aui ON aui.assessment_specification_id = aus.assessment_id
                JOIN assessment ia ON ia.id = aui.assessment_id AND ia.deleted_at IS NULL
                WHERE us.id IN (" . implode(',', array_keys($unitSpecCache)) . ")
            ";
            $statement = $this->database->prepare($query);
            $statement->execute();
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $assessmentImplToSpecMap[$row['ia.id']] = $row['sa.id'];
            }
        }

        $dto = new DataTransferObject($assessmentImplToSpecMap);
        foreach($unitImpls as $unitImpl){
            $dto->addUnitImplementation($unitImpl);
        }



        // Get the following Assessments:
        // 1. AssessmentSpec in UnitSpec where UnitSpec not imported to UnitImpl
        // 2. AssessmentSpec in UnitSpec, UnitSpec imported to UnitImpl, but AssessmentSpec not imported
        // 3. AssessmentSpec in UnitSpec, UnitSpec imported to UnitImpl, AssessmentSpec imported
        // 4. AssessmentSpec in UnitSpec, UnitSpec imported to UnitImpl, AssessmentSpec imported but deleted
        $query1 = "
            SELECT 
                us.id AS 'us.id',
                usf.name AS 'usf.name',
                sa.id AS 'sa.id',
                sa.title AS 'sa.title',
                sa.deleted_at AS 'sa.deleted_at',
                sdw.assessment_id AS 'sdw.id',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id',
                ia.id AS 'ia.id',
                ia.title AS 'ia.title',
                ia.sequence       AS 'ia.sequence',
                idw.assessment_id AS 'idw.id',
                iqz.assessment_id AS 'iqz.id',
                itk.assessment_id AS 'itk.id'                
            FROM course_unit_specification us
            LEFT JOIN folder usf ON usf.id = us.folder_id
            LEFT JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            LEFT JOIN assessment sa ON sa.id = aus.assessment_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz ON sqz.assessment_id = sa.id
            LEFT JOIN task stk ON stk.assessment_id = sa.id
            LEFT JOIN assessment_course_unit_implementation aui ON aui.assessment_specification_id = sa.id
            LEFT JOIN assessment ia ON ia.id = aui.assessment_id AND ia.deleted_at IS NULL
            LEFT JOIN submission_box idw ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz ON iqz.assessment_id = ia.id
            LEFT JOIN task itk ON itk.assessment_id = ia.id
            WHERE  us.id IN (" . implode(',', array_keys($unitSpecCache)) . ")
        ";

        // Get the following Assessments:
        // 5. AssessmentImpls created directly in UnitImpl, no corresponding AssessmentSpec
        $query2 = "
            SELECT
                us.id             AS 'us.id',
                usf.name          AS 'usf.name',
                null              AS 'sa.id',
                null              AS 'sa.title',
                null              AS 'sa.deleted_at',
                null              AS 'sdw.id',
                null              AS 'sqz.id',
                null              AS 'stk.id',
                ia.id             AS 'ia.id',
                ia.title          AS 'ia.title',
                ia.sequence       AS 'ia.sequence',
                idw.assessment_id AS 'idw.id',
                iqz.assessment_id AS 'iqz.id',
                itk.assessment_id AS 'itk.id'
            FROM course_unit_specification us
            JOIN course_unit_implementation ui ON us.id = ui.course_unit_specification_id
            JOIN folder usf ON usf.id = us.folder_id
            JOIN assessment_course_unit_implementation aui ON ui.id = aui.course_unit_implementation_id
            JOIN assessment ia ON ia.id = aui.assessment_id AND ia.deleted_at IS NULL
            LEFT JOIN submission_box idw ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz ON iqz.assessment_id = ia.id
            LEFT JOIN task itk ON itk.assessment_id = ia.id            
            WHERE  us.id IN (" . implode(',', array_keys($unitSpecCache)) . ")
        ";

        $query = "SELECT * FROM ($query1) AS t1 UNION SELECT * FROM ($query2) AS t2 ORDER BY `us.id`, `ia.sequence`";

        $statement = $this->database->prepare($query);
        $statement->execute();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            // TODO hack. This should be implemented in the DB query itself
            // The Union is returning dup rows for AssImpl, with+without AssessmentSpec details
            if($row['ia.id'] && !$row['sa.id'] && array_key_exists($row['ia.id'], $assessmentImplToSpecMap)){
                continue;
            }

            // Is is the first time this UnitSpec has been seen?
            if(!$unitSpecDBCache[$row['us.id']] instanceof UnitSpecification){
                // Add a Folder and name it so we can later render out this UnitSpec's name.
                $unitSpecDBCache[$row['us.id']] = $unitSpecCache[$row['us.id']];
                $folder = new Folder();
                $folder->setName($row['usf.name']);
                $unitSpecCache[$row['us.id']]->setFolder($folder);
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
                    $assessmentSpec->setDeletedAt(\Alaress_Date_Format::newLocalShort($row['sa.deleted_at']));
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
                $assessmentImpl->setDeletedAt(new \Alaress_Date_NullDate());
            }

            if($assessmentSpec instanceof IAssessable && $assessmentImpl instanceof IAssessable){
                $dto->addAssessmentPair($unitSpecCache[$row['us.id']], $assessmentSpec, $assessmentImpl);
            } else if($assessmentSpec instanceof IAssessable) {
                $dto->addAssessmentSpec($unitSpecCache[$row['us.id']], $assessmentSpec);
            } else {
                $dto->addAssessmentImpl($unitSpecCache[$row['us.id']], $assessmentImpl);
            }

        }
        return $dto;
    }
}