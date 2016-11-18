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
 * A single public method enforces the consistency of the returned data structure.
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
     * @param array $args Determines what we search on
     *
     * @return UnitImplementation[] $results Indexed by unitSpec.id
     * @var UnitImplementation $results[unitSpecId] Will have no ID if doesn't exist. Will always link to a UnitSpec
     * @var IAssessable[] UnitImplementation->getAssessables()[idx] An AssessmentImpl. No id if not exist. May link to AssessmentSpec

     * @todo enforce the return type by using an DTO (data-transfer-object)
     */
//    public function build(array $args)
//    {
//        if(array_key_exists('unitSpecs', $args)){
//            $results = $this->buildFromUnitSpecs($args['unitSpecs']);
//        } elseif(array_key_exists('courseImplId', $args)){
//            $results = $this->buildFromCourseImplId($args['courseImplId']);
//        } else {
//            throw new \InvalidArgumentException('Invalid args: '.print_r($args, true));
//        }
//
//        return $results;
//    }

    /**
     * Called during a new import.
     *
     * UnitImpls will all have no id, but will have dates already set.
     * AssessmentImpls will have no id, but will have dates populated according to previous stage
     * All AssessmentImpls will link to an AssessmentSpec
     *
     * @param UnitImplementation[] $unitImpls        Each UnitImpl links to a UnitSpec to get Assessments for
     * @return DataTransferObject
     */
    public function buildForNewImport($unitImpls)
    {
        $unitSpecIds = [];
        foreach($unitImpls as $unitImpl){
            $unitSpecIds[] = $unitImpl->getSpecification()->getId();
        }

        $query = "
            SELECT 
                us.id AS 'us.id',
                usf.name as 'usf.name',
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
            WHERE  us.id IN (".implode(',', $unitSpecIds).") AND sa.deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        // No Implementations (this is a new import)
        $implToSpecMap = [];
        $dto = new DataTransferObject($implToSpecMap);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $unitSpecId = $row['us.id'];
            $unitSpecFolderName = $row['usf.name'];
            $assessmentSpecId = $row['sa.id'];
            $assessmentSpecTitle = $row['sa.title'];
            $dueWorkSpecId = $row['sdw.id'];
            $quizSpecId = $row['sqz.id'];
            $taskSpecId = $row['stk.id'];

            // Is this the first time we've seen the UnitSpec ?
            if(!is_array($unitAssessments[$unitSpecId])){
                $unitAssessments[$unitSpecId] = [];
                $unitSpec = new UnitSpecification();
                $unitSpec->setId($unitSpecId);
                $unitSpecFolder = new Folder();
                $unitSpecFolder->setName($unitSpecFolderName);
                $unitSpec->setFolder($unitSpecFolder);
                $unitAssessments[$unitSpecId]['unitSpec'] = $unitSpec;
                $unitImpl = new UnitImplementation();
                $unitImpl->setSpecification($unitSpec);
                $unitImpl->setOpenDate($unitSpecs[$unitSpecId]['openDate']);
                $unitImpl->setDueDate($unitSpecs[$unitSpecId]['closeDate']);
                $unitAssessments[$unitSpecId]['unitImpl'] = $unitImpl;
                $unitAssessments[$unitSpecId]['assessments'] = [];
            }

            if($dueWorkSpecId){
                $assessment = new DueWorkAssessment();
            } elseif($quizSpecId){
                $assessment = new QuizAssessment();
            } elseif($taskSpecId){
                $assessment = new TaskAssessment();
            } else {
                throw new \DomainException("Unknown assessment type: $assessmentSpecId");
            }
            $assessment->setId($assessmentSpecId);
            $assessment->setTitle($assessmentSpecTitle);

            $unitAssessments[$unitSpecId]['assessments'][] = [
                'spec' => $assessment,
                'impl' => null,
            ];
        }

        return $unitAssessments;
    }

    /**
     * Called during a modification of prior import
     *
     * UnitImpl + AssessmentImpl will be non-null if already imported.
     *
     * @param int $courseImplId
     * @param array $unitSpecs                                          Units to import, indexed by unitSpecId
     * @var int $unitSpecs[unitSpecId]['id']                            UnitSpec.id
     * @var \Alaress_Date_Format $unitSpecs[unitSpecId]['openDate']     Open date as input by user
     * @var \Alaress_Date_Format $unitSpecs[unitSpecId]['closeDate']    Close date as input by user
     * @param array $unitImpls // TODO is this required?
     * @return DataTransferObject
     */
    public function buildForImportModify($courseImplId, array $unitSpecs, array $unitImpls)
    {
        /**
         * Maps each AssessmentImplId to an AssessmentSpecId.
         * SpecId will be null if Impl was created in UnitImpl.
         * Array will be empty if no UnitImps, or if no Assessments imported to UnitImps.
         *
         * @var int[]
         */
        $assessmentImplToSpecMap = [];
        if(count($unitImpls)){
            // We DO want to retrieve records for deleted Specs.
            // We DO NOT want to retrieve records for deleted Impls.
            $query = "
                SELECT 
                    aui.assessment_id               AS 'ia.id',
                    aui.assessment_specification_id AS 'sa.id'
                FROM assessment_course_unit_implementation aui
                JOIN assessment `ai` ON `ai`.`id` = aui.assessment_id AND `ai`.deleted_at IS NULL
                WHERE  aui.course_unit_implementation_id IN (".implode(',', $unitImpls).")
            ";
            $statement = $this->database->prepare($query);
            $statement->execute();
            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $assessmentImplToSpecMap[$row['ia.id']] = $row['sp.id'];
            }
        }

        // Get all Assessments in either UnitSpecs or UnitImpls

        $query1 = "
            SELECT 
                us.id AS 'us.id',
                null  AS 'ui.id',
                usf.name as 'usf.name',
                sa.id AS 'sa.id',
                sa.title AS 'sa.title',
                sdw.assessment_id AS 'sdw.id',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id',
                null AS 'ia.id',
                null AS 'ia.title',
                null AS 'idw.id',
                null AS 'iqz.id',
                null AS 'itk.id'                
            FROM course_unit_specification us
            LEFT JOIN folder usf ON usf.id = us.folder_id
            LEFT JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            LEFT JOIN assessment sa ON sa.id = aus.assessment_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz ON sqz.assessment_id = sa.id
            LEFT JOIN task stk ON stk.assessment_id = sa.id
            WHERE  us.id IN (".implode(',', array_keys($unitSpecs)).") AND sa.deleted_at IS NULL
        ";

        $query2 = "
            SELECT 
                us.id AS 'us.id',
                ui.id AS 'ui.id',
                usf.name as 'usf.name',
                sa.id AS 'sa.id',
                sa.title AS 'sa.title',
                sdw.assessment_id AS 'sdw.id',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id',
                ia.id AS 'ia.id',
                ia.title AS 'ia.title',
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
            WHERE  us.id IN (".implode(',', array_keys($unitSpecs)).") AND sa.deleted_at IS NULL
        ";

        $query = "
            SELECT 
                us.id AS 'us.id',
                ui.id AS 'ui.id',
                usf.name as 'usf.name',
                sa.id AS 'sa.id',
                sa.title AS 'sa.title',
                sdw.assessment_id AS 'sdw.id',
                sqz.assessment_id AS 'sqz.id',
                stk.assessment_id AS 'stk.id',
                ia.id AS 'ia.id',
                ia.title AS 'ia.title',
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
            WHERE  us.id IN (".implode(',', array_keys($unitSpecs)).") AND sa.deleted_at IS NULL
        ";

        return [];
    }

//    /**
//     * Clones an AssessmentSpec to produce an AssessmentImpl
//     *
//     * Performs appropriate deep-cloning
//     *
//     * @param IAssessableDataModel $assessmentSpec
//     * @return IAssessableDataModel
//     */
//    public function cloneAssessment(IAssessableDataModel $assessmentSpec)
//    {
//        switch(get_class($assessmentSpec)){
//            case Quiz::class:
//                /** @var Quiz $assessmentSpec */
//                $assessmentImpl = clone $assessmentSpec;
//                foreach($assessmentSpec->getSections() as $sectionSpec){
//                    $sectionImpl = clone $sectionSpec;
//                    foreach($sectionSpec->getQuestions() as $questionSpec){
//                        $questionImpl = clone  $questionSpec;
//                        $solutionImpl = clone $questionSpec->getSolution();
//                        $solutionImpl->setQuestion($questionImpl);
//                        $questionImpl->setSolution($solutionImpl);
//                        $sectionImpl->get
//                    }
//                }
//                $assessmentImpl->getSections()
//        }
//        $assessmentImpl = new
//        return $assessmentSpec;
//    }
}