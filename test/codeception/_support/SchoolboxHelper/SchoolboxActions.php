<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace SchoolboxHelper;

use Database;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Component_Definition;
use Component_DefinitionMapper;
use Component_InstanceMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Pastoral\Mapper as PastoralRecordMapper;

class SchoolboxActionsConstants {
    public const HOMEPAGE_COMPONENT_POSITION = ['left'=>'1','right'=>'2','top'=>'3'];
}

trait SchoolboxActions
{
    /** @var Kernel $cli_kernel */
    public $cli_kernel;

    /** @var Database $database */
    public $database;

    /**
     * initDatabase initializes the database for direct requests and mapper requests
     * @throws \Schoolbox\External\Exception
     */
    function initDatabase()
    {
        $this->database=Database::getInstance();
        $this->database->connect(SQL_dsn, SQL_user, SQL_passwd);
    }

    /**
     * initCliKernel initializes the cli kernel so that I can use the dependancy injector.
     * @throws
     */
    function initCliKernel()
    {

        $app_class = new class () extends App {
            public function run() : void{}
        };

        $this->cli_kernel = new Kernel($app_class, []);
    }

    /**
     * getUser get a user object.
     *
     * @param string $username
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @return User
     */
    function getUser($username){

        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return UserMapper::getInstance()->findOneByColumn('username', $username);
    }

    /**
     * getRoleByTitle gets the first found role matching on title.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $title
     *
     * @return \Schoolbox\Role
     */
    function getRoleByTitle($title){
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return RoleMapper::getInstance()->findOneByColumn('name', $title);
    }

    /**
     * getCampusByName gets the first found Campus using its name.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $name
     *
     * @return
     */
    function getCampusByName($name){
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return CampusMapper::getInstance()->findOneByColumn('name', $name);
    }

    /**
     * getCalendarEventByTitle gets the first found calendar event by its title.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $title
     *
     * @return
     */
    public function getCalendarEventByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return EventMapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * getCalendarEventAttendanceList fetch all event attendance records and returns them based on event id.
     *
     * @throws \Schoolbox\External\Exception
     *
     * @param int $event_id
     *
     * @return array
     */
    public function getCalendarEventAttendanceList($event_id){
        if(!isset($this->database)) {
            $this->initDatabase();
        }

        $query = $this->database->prepare('select user_id, id from event_attendance where event_id='.$event_id.';');
        $query->execute();
        return $query->fetchAll();
    }

    /**
     * getComponentByClass gets the first component definition by its class
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $element_class
     *
     * @return Component_Definition
     */
    public function getComponentByClass($element_class)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return Component_DefinitionMapper::getInstance()->findOneByColumn('class',$element_class);
    }

    /**
     * getComponentOnHomepage gets the first component instance based on a few variables.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param int $homepage_id,
     * @param Component_Definition $component
     * @param string $position
     *
     * @return
     */
    public function getComponentOnHomepage($homepage_id, $component, $position)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return Component_InstanceMapper::getInstance()->findOneByColumnList(['component_definition_id'=>$component->getId(),
            'module'=>'homepage',
            'identifier'=>$homepage_id,
            'position'=>$position]);
    }

    /**
     * getTextBoxByHeading gets the first TextBox by its title.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $title
     *
     * @return
     */
    public function getTextBoxByHeading($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return TextBoxMapper::getInstance()->findOneByColumnList(['heading'=>$title, 'deleted' => null]);
    }

    /**
     * getNewsArticle gets the first news article based on its title.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $title
     * @param bool $latest_id
     *
     * @return
     */
    function getNewsArticle($title, $latest_id=true){

        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return NewsMapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * getFirstSubmissionIdByOwnerAndAssessmentID gets the first submission ID by the owner and assignment ID
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param int $assessmentID
     * @param int $assessment_ownerID
     * @param int $submission_ownerID
     *
     * @return
     */
    function getFirstSubmissionIdByOwnerAndAssessmentID($assessmentID, $assessment_ownerID, $submission_ownerID)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        $box_id=SubmissionBoxMapper::getInstance()->findOneByColumnList(['assessment_id'=>$assessmentID, 'owner'=>$assessment_ownerID])->getID();
        $submission = SubmissionMapper::getInstance()->findOneByColumnList(['box_id'=>$box_id, 'owner'=>$submission_ownerID]);
        return $submission->getId();
    }

    /**
     * getFirstHomepageIdByTitle gets the ID of the first homepage that matches the title
     *
     * @throws \Schoolbox\External\Exception
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param string $title
     *
     * @return int
     *
     */
    function getFirstHomepageIdByTitle($title)
    {
        return $this->getFirstFolderByTitle($title)->getId();
    }

    /**
     * getFirstFolderByTitle gets the first folder that matches the title
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $title
     *
     * @return
     */
    function getFirstFolderByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        /** @var Folder $folder */
        return FolderMapper::getInstance()->findOneByColumn('name', $title);
    }

    /**
     * getFirstCourseByTitle gets the first course specification that matches the title
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     * @throws \Schoolbox\External\Exception
     *
     * @param string $title
     *
     * @return
     */
    function getFirstCourseByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        $course_folder = $this->getFirstFolderByTitle($title);
        return CourseSpecificationMapper::getInstance()->findOneByColumn('folder_id', $course_folder->getId());
    }

    /**
     * getUnitsByCourseSpecId gets all the units by the course spec ID
     *
     * @throws \Schoolbox\External\Exception
     *
     * @param int $course_spec_id
     *
     * @return \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model[]
     */
    function getUnitsByCourseSpecId($course_spec_id)
    {
        if (!isset($this->database)) {
            $this->initDatabase();
        }
        return UnitMapper::getInstance()->findAllByCourseId($course_spec_id);
    }

    /**
     * getAssessmentIdByCourseUnitSpecId gets all assessment ID by the Course Unit Spec Id
     *
     * @throws \Schoolbox\External\Exception
     *
     * @param int $unit_id
     *
     * @return array
     */
    function getAssessmentIdByCourseUnitSpecId($unit_id)
    {
        if (!isset($this->database)) {
            $this->initDatabase();
        }
        $query = $this->database->prepare('select * from assessment_course_unit_specification where course_unit_specification_id='.$unit_id.';');
        $query->execute();
        return $query->fetchAll();
    }

    /**
     * getFirstUnitByTitle gets first Unit based on the title
     *
     * @throws \Schoolbox\External\Exception
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param string $title
     *
     * @return
     */
    function getFirstUnitByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        $unit_folder = $this->getFirstFolderByTitle($title);
        return UnitMapper::getInstance()->findOneByColumn('folder_id', $unit_folder->getId());
    }

    /**
     * getFirstAssessmentByTitle gets first Assessment based on the title
     *
     * @throws \Schoolbox\External\Exception
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param string $title
     *
     * @return
     */
    function getFirstAssessmentByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return AssessmentMapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * getFirstAssessmentIdByTitle gets first Assessment ID based on the title
     *
     * @throws \Schoolbox\External\Exception
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param string $title
     *
     * @return int
     */
    function getFirstAssessmentIdByTitle($title)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        /** @var \Schoolbox\Learning\Assessment\DataModel\Mapper $DueWork */
        $assessment = $this->getFirstAssessmentByTitle($title);
        return $assessment->getId();
    }

    /**
     * getAssessmentRubricGrid gets first rubric grid based on assessment ID
     *
     * @param int $assessmentID
     *
     * @throws \Schoolbox\External\Exception
     * @throws Exception if no instance of model is found
     * @throws \Aura\Di\Exception\ServiceNotFound
     *
     * @return \Schoolbox\Assessment\Rubric\Grid\Grid
     */
    function getAssessmentRubricGrid($assessmentID)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        if(!isset($this->cli_kernel)) {
            $this->initCliKernel();
        }

        /** @var IAssessable $assessment */
        $assessment = $this->cli_kernel->get('assessmentDataSource')->findById($assessmentID);

        return RubricMapper::getInstance()->retrieveGrid($assessment, true);
    }

    /**
     * getPastoralRecordByTitle gets first pastoral record based on its title
     *
     * @param string $title
     *
     * @throws \Schoolbox\External\Exception
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @return \Schoolbox\Pastoral\Model
     */
    function getPastoralRecordByTitle($heading)
    {
        if(!isset($this->database)) {
            $this->initDatabase();
        }
        return PastoralRecordMapper::getInstance()->findOneByColumn('heading', $heading);
    }
}