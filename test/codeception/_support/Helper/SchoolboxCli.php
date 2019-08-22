<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace Helper;

/**
 * This class instantiates a Schoolbox CliTool kernel. This enables Cests, Actors and Helpers to:
 * 1. Use the CliTool Kernel's DI to conveniently fetch services
 * 2. Invoke native Schoolbox code in a production-like environment (eg Mappers)
 *
 * This class is responsible for providing low-level 'how' (as opposed to 'what') actions are performed.
 *
 * Any public methods declared in this helper class will be available in the Actor ($I)
 *
 * Note that we must use FQDN in our annotations because codeception copies them verbatim into the generated trait.
 *
 * @see https://codeception.com/docs/06-ModulesAndHelpers
 */
class SchoolboxCli extends \Codeception\Module
{
    // TODO this is a page object responsibility
    public const HOMEPAGE_COMPONENT_POSITION = ['left' => '1', 'right' => '2', 'top' => '3'];

    /** @var \Schoolbox\Application\CliTool\Kernel */
    public $cliKernel;

    /**
     * HOOK: used after configuration is loaded
     *
     * Initializes the cli kernel so that we can use the dependency injector.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \Schoolbox\External\Exception
     */
    public function _initialize(): void
    {
        $app = new class () extends \Schoolbox\Application\CliTool\App
        {
            public function run(): void
            {
            }
        };
        // TODO Expose kernel options as Helper config
        $this->cliKernel = new \Schoolbox\Application\CliTool\Kernel(
            $app,
            [\Schoolbox\Application\CliTool\Kernel::OPTION_ERRORS => E_ALL]
        );
    }

    /**
     * Returns Schoolbox's internal DB connection
     *
     * @return \Alaress\Database\Connector\IConnector
     * @throws \RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getDatabaseInternal(): \Alaress\Database\Connector\IConnector
    {
        return $this->cliKernel->get('databaseInternal');
    }

    /**
     * Returns a user model
     *
     * @param string $username
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     * @return \Schoolbox\User
     */
    public function getUser(string $username): \Schoolbox\User
    {
        return \Schoolbox\UserMapper::getInstance()->findOneByColumn('username', $username);
    }

    /**
     * Returns the the first found role matching on title.
     *
     * @param string $title
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     * @return \Schoolbox\Role
     */
    public function getRoleByTitle(string $title): \Schoolbox\Role
    {
        return \Schoolbox\RoleMapper::getInstance()->findOneByColumn('name', $title);
    }

    /**
     * Returns the first found Campus using its name.
     *
     * @param string $name
     * @return \Schoolbox\Campus
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getCampusByName(string $name): \Schoolbox\Campus
    {
        return \Schoolbox\CampusMapper::getInstance()->findOneByColumn('name', $name);
    }

    /**
     * Returns the first found calendar event by its title.
     *
     * @param string $title
     * @return \Schoolbox\Calendar\Event
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getCalendarEventByTitle(string $title): \Schoolbox\Calendar\Event
    {
        return \Schoolbox\Calendar\EventMapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * Returns event attendance records for the specified event
     *
     * @param int $eventId
     * @return array
     * @throws \RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Exception
     */
    public function getCalendarEventAttendanceList(int $eventId): array
    {
        $query = 'SELECT user_id, id FROM event_attendance WHERE event_id = :event_id';
        return $this->getDatabaseInternal()->executeQuery($query, ['event_id' => $eventId])->fetchAll();
    }

    /**
     * Returns the first component definition by its class
     *
     * @param string $componentClass
     * @return \Component_Definition
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getComponentByClass(string $componentClass): \Component_Definition
    {
        return \Component_DefinitionMapper::getInstance()->findOneByColumn('class', $componentClass);
    }

    /**
     * Returns the first component instance based on a few variables.
     *
     * @param int                   $folderId
     * @param \Component_Definition $component
     * @param string                $position
     * @return \Component_Instance
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getComponentOnHomepage(
        int $folderId,
        \Component_Definition $component,
        string $position
    ): \Component_Instance {
        $columnList = [
            'component_definition_id' => $component->getId(),
            'module'                  => \Component_Instance::MODULE_HOMEPAGE,
            'identifier'              => $folderId,
            'position'                => $position,
        ];

        return \Component_InstanceMapper::getInstance()->findOneByColumnList($columnList);
    }

    /**
     * Returns the the first TextBox by its title.
     *
     * @param string $title
     * @return \Schoolbox\Resource\Textbox\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getTextBoxByHeading(string $title): \Schoolbox\Resource\Textbox\Model
    {
        return \Schoolbox\Resource\Textbox\Mapper::getInstance()->findOneByColumn('heading', $title);
    }

    /**
     * Returns the first news article based on its title.
     *
     * @param string $title
     * @return \Schoolbox\Comms\News\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getNewsArticleByTitle(string $title): \Schoolbox\Comms\News\Model
    {
        return \Schoolbox\Comms\News\Mapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * Returns the first submission ID by the owner and assignment ID
     *
     * @param int $assessmentId
     * @param int $assessmentOwnerId
     * @param int $submissionOwnerId
     * @return int
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstSubmissionIdByOwnerAndAssessmentID(
        int $assessmentId,
        int $assessmentOwnerId,
        int $submissionOwnerId
    ): int {
        $box_id     = \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->findOneByColumnList([
            'assessment_id' => $assessmentId,
            'owner'         => $assessmentOwnerId,
        ])->getId();
        $submission = \Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper::getInstance()->findOneByColumnList([
            'box_id' => $box_id,
            'owner'  => $submissionOwnerId,
        ]);
        return $submission->getId();
    }

    /**
     * Returns the ID of the first homepage that matches the title
     *
     * @param string $title
     * @return int
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstHomepageIdByTitle(string $title): int
    {
        return $this->getFirstFolderByTitle($title)->getId();
    }

    /**
     * Returns the first folder that matches the title
     *
     * @param string $title
     * @return \Schoolbox\Resource\Folder\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstFolderByTitle(string $title): \Schoolbox\Resource\Folder\Model
    {
        return \Schoolbox\Resource\Folder\Mapper::getInstance()->findOneByColumn('name', $title);
    }

    /**
     * Returns the first course specification that matches the title
     *
     * @param string $title
     * @return \Schoolbox\Learning\Course\DataModel\Specification\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstCourseByTitle(string $title): \Schoolbox\Learning\Course\DataModel\Specification\Model
    {
        $courseFolder = $this->getFirstFolderByTitle($title);
        return \Schoolbox\Learning\Course\DataModel\Specification\Mapper::getInstance()->findOneByColumn('folder_id', $courseFolder->getId());
    }

    /**
     * Returns all the units by the course spec ID
     *
     * @param int $courseSpecId
     * @return \Schoolbox\GenericCollection|\Schoolbox\Learning\Course\DataModel\Specification\Unit\Model[]
     */
    public function getUnitsByCourseSpecId(int $courseSpecId): \Schoolbox\GenericCollection
    {
        return \Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper::getInstance()->findAllByCourseId($courseSpecId);
    }

    /**
     * Returns all assessment ID by the Course Unit Spec Id
     *
     * @param int $unitSpecId
     * @return array
     * @throws \Alaress\Database\Exception
     * @throws \RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAssessmentIdByCourseUnitSpecId(int $unitSpecId): array
    {
        $sql = "SELECT * FROM assessment_course_unit_specification WHERE course_unit_specification_id = :unit_spec_id";
        return $this->getDatabaseInternal()->executeQuery($sql, ['unit_spec_id' => $unitSpecId])->fetchAll();
    }

    /**
     * Returns first Unit based on the title
     *
     * @param $title
     * @return \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstUnitByTitle(string $title): \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model
    {
        $unitFolder = $this->getFirstFolderByTitle($title);
        return \Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper::getInstance()->findOneByColumn('folder_id', $unitFolder->getId());
    }

    /**
     * Returns first Assessment based on the title
     *
     * @param string $title
     * @return \Schoolbox\Learning\Assessment\DataModel\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstAssessmentByTitle(string $title): \Schoolbox\Learning\Assessment\DataModel\Model
    {
        return \Schoolbox\Learning\Assessment\DataModel\Mapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * Returns first Assessment ID based on the title
     *
     * @param string $title
     * @return int
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     */
    public function getFirstAssessmentIdByTitle(string $title): int
    {
        return $this->getFirstAssessmentByTitle($title)->getId();
    }

    /**
     * Returns a rubric grid based on assessment id
     *
     * @param int $assessmentId
     * @return \Schoolbox\Assessment\Rubric\Grid\Grid
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAssessmentRubricGrid(int $assessmentId): \Schoolbox\Assessment\Rubric\Grid\Grid
    {
        /** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy */
        $assessableDataSource = $this->cliKernel->get('assessmentDataSource');
        $assessable           = $assessableDataSource->findById($assessmentId);

        return \Schoolbox\Assessment\Rubric\DataModel\DataSource::getInstance()->retrieveGrid($assessable, true);
    }

    /**
     * Returns first pastoral record based on its title
     *
     * @param string $heading
     * @return \Schoolbox\Pastoral\Model
     * @throws \Schoolbox\Mapper\Exception         If no instance of model is found
     * @throws \Schoolbox\Mapper\Exception\Deleted If the model was found but has been deleted
     *
     */
    public function getPastoralRecordByTitle(string $heading): \Schoolbox\Pastoral\Model
    {
        return \Schoolbox\Pastoral\Mapper::getInstance()->findOneByColumn('heading', $heading);
    }
}
