<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Assessment\Course\Import;

use Alaress\IDatabase;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
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
     * @return array[] $results Indexed by unitSpec.id
     * @var UnitSpecification $results[unitSpecId]['unitSpec']
     * @var UnitImplementation|null $results[unitSpecId]['unitImpl']
     * @var array $results[unitSpecId]['assessments']
     * @var IAssessable|null $results[unitSpecId]['assessments'][]['spec']
     * @var IAssessable|null $results[unitSpecId]['assessments'][]['impl']
     */
    public function build(array $args)
    {
        if(array_key_exists('unitSpecs', $args)){
            $results = $this->buildFromUnitSpecs($args['unitSpecs']);
        } elseif(array_key_exists('courseImplId', $args)){
            $results = $this->buildFromCourseImplId($args['courseImplId']);
        } else {
            throw new \InvalidArgumentException('Invalid args: '.print_r($args, true));
        }

        return $results;
    }

    /**
     * Called during a new import.
     *
     * UnitImpls will all have no id, but will have dates populated according to previous stage.
     * AssessmentImpls will have no id, but will have dates populated according to previous stage
     *
     * @param array $unitSpecs                                          Units to import, indexed by unitSpecId
     * @var int  $unitSpecs[unitSpecId]['id']                           UnitSpec.id
     * @var \Alaress_Date_Format $unitSpecs[unitSpecId]['openDate']    Open date as input by user
     * @var \Alaress_Date_Format $unitSpecs[unitSpecId]['closeDate']   Close date as input by user
     * @return array
     */
    protected function buildFromUnitSpecs($unitSpecs)
    {
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
            WHERE  us.id IN (".implode(',', array_keys($unitSpecs)).") AND sa.deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $unitAssessments = [];

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
                $assessment = new DueWork();
            } elseif($quizSpecId){
                $assessment = new Quiz();
            } elseif($taskSpecId){
                $assessment = new Task();
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
     * @return array
     */
    protected function buildFromCourseImplId($courseImplId)
    {
        return [];
    }
}