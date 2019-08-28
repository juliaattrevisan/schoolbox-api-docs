<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
declare(strict_types=1);

namespace Helper;

/**
 * This class instantiates a Schoolbox CliTool kernel. This enables Cests, Actors and Helpers to:
 * 1. Use the CliTool Kernel's DI to conveniently fetch ('grab' in codeception nomenclature) services
 * 2. Invoke native Schoolbox code in a production-like environment (eg Mappers)
 *
 * This class is responsible for providing low-level 'how' (as opposed to 'what') actions are performed.
 *
 * Any public methods declared in this helper class will be available in the Actor ($I)
 *
 * Note that we must use FQDN in our annotations because codeception copies them verbatim into the generated trait.
 *
 * So that this class does not grow to be the size of schoolbox, these methods should return high-level services
 * that enable Cests to fetch the data relevant to their scenarios.
 *
 * @see https://codeception.com/docs/06-ModulesAndHelpers
  */
class SchoolboxCli extends \Codeception\Module
{
    /** @var \Schoolbox\Application\CliTool\Kernel */
    public $cliKernel;

    /**
     * HOOK: used after configuration is loaded
     *
     * Initializes the cli kernel so that we can use the dependency injector.
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
     */
    public function grabDatabaseInternal(): \Alaress\Database\Connector\IConnector
    {
        return $this->cliKernel->get('databaseInternal');
    }

    /**
     * Returns a user model
     *
     * @param string $username
     * @return \Schoolbox\User
     */
    public function grabUser(string $username): \Schoolbox\User
    {
        return \Schoolbox\UserMapper::getInstance()->findOneByColumn('username', $username);
    }

    /**
     * Returns the the first found role matching on title.
     *
     * @param string $title
     * @return \Schoolbox\Role
     */
    public function grabRoleByTitle(string $title): \Schoolbox\Role
    {
        return \Schoolbox\RoleMapper::getInstance()->findOneByColumn('name', $title);
    }

    /**
     * Returns the first found Campus using its name.
     *
     * @param string $name
     * @return \Schoolbox\Campus
     */
    public function grabCampusByName(string $name): \Schoolbox\Campus
    {
        return \Schoolbox\CampusMapper::getInstance()->findOneByColumn('name', $name);
    }

    /**
     * Returns the first found calendar event by its title.
     *
     * @param string $title
     * @return \Schoolbox\Calendar\Event
     */
    public function grabCalendarEventByTitle(string $title): \Schoolbox\Calendar\Event
    {
        return \Schoolbox\Calendar\EventMapper::getInstance()->findOneByColumn('title', $title);
    }

    /**
     * Returns event attendance records for the specified event
     *
     * @param int $eventId
     * @return array
     */
    public function grabCalendarEventAttendanceList(int $eventId): array
    {
        $query = 'SELECT user_id, id FROM event_attendance WHERE event_id = :event_id';
        return $this->grabDatabaseInternal()->executeQuery($query, ['event_id' => $eventId])->fetchAll();
    }

    /**
     * Returns the first component definition by its class
     *
     * @param string $componentClass
     * @return \Component_Definition
     */
    public function grabComponentDefinitionByClass(string $componentClass): \Component_Definition
    {
        return \Component_DefinitionMapper::getInstance()->findOneByColumn('class', $componentClass);
    }


    /**
     * Returns the the first TextBox by its title.
     *
     * @param string $title
     * @return \Schoolbox\Resource\Textbox\Model
     */
    public function grabTextBoxByHeading(string $title): \Schoolbox\Resource\Textbox\Model
    {
        return \Schoolbox\Resource\Textbox\Mapper::getInstance()->findOneByColumn('heading', $title);
    }

    /**
     * Returns the first news article based on its title.
     *
     * @param string $title
     * @return \Schoolbox\Comms\News\Model
     */
    public function grabNewsArticleByTitle(string $title): \Schoolbox\Comms\News\Model
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
     */
    public function grabFirstSubmissionIdByOwnerAndAssessmentID(
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
     */
    public function grabFirstHomepageIdByTitle(string $title): int
    {
        return $this->grabFirstFolderByTitle($title)->getId();
    }

    /**
     * Returns the first folder that matches the title
     *
     * @param string $title
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function grabFirstFolderByTitle(string $title): \Schoolbox\Resource\Folder\Model
    {
        return \Schoolbox\Resource\Folder\Mapper::getInstance()->findOneByColumn('name', $title);
    }

    /**
     * Returns the first course specification that matches the title
     *
     * @param string $title
     * @return \Schoolbox\Learning\Course\DataModel\Specification\Model
     */
    public function grabFirstCourseSpecByTitle(string $title): \Schoolbox\Learning\Course\DataModel\Specification\Model
    {
        $courseFolder = $this->grabFirstFolderByTitle($title);
        return \Schoolbox\Learning\Course\DataModel\Specification\Mapper::getInstance()->findOneByColumn('folder_id', $courseFolder->getId());
    }


    /**
     * Returns first Unit based on the title
     *
     * @param $title
     * @return \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model
     */
    public function grabFirstUnitSpecByTitle(string $title): \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model
    {
        $unitFolder = $this->grabFirstFolderByTitle($title);
        return \Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper::getInstance()->findOneByColumn('folder_id', $unitFolder->getId());
    }

    /**
     * Returns first Assessment based on the title
     *
     * @param string $title
     * @return \Schoolbox\Learning\Assessment\IAssessable
     */
    public function grabFirstAssessmentByTitle(string $title): \Schoolbox\Learning\Assessment\IAssessable
    {
        /** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->cliKernel->get('assessmentDataSource');
        return $assessmentMapperProxy->findOneByColumn('title', $title);
    }

    /**
     * Returns first Assessment ID based on the title
     *
     * @param string $title
     * @return int
     */
    public function grabFirstAssessmentIdByTitle(string $title): int
    {
        return $this->grabFirstAssessmentByTitle($title)->getId();
    }

    /**
     * Returns a rubric grid based on assessment id
     *
     * @param int $assessmentId
     * @return \Schoolbox\Assessment\Rubric\Grid\Grid
     */
    public function grabAssessmentRubricGrid(int $assessmentId): \Schoolbox\Assessment\Rubric\Grid\Grid
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
     */
    public function grabPastoralRecordByTitle(string $heading): \Schoolbox\Pastoral\Model
    {
        return \Schoolbox\Pastoral\Mapper::getInstance()->findOneByColumn('heading', $heading);
    }
}
