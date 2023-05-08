<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Helper;

use Alaress\Module\Cache\Rebuilder;
use Codeception\Exception\ModuleException;
use Codeception\Module\Db;
use Codeception\TestInterface;
use Psr\Log\NullLogger;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Curriculum\Node\Model as Node;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Summator\Factory;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Action\Mapper as PastoralActionMapper;
use Schoolbox\Pastoral\TypeAction\Mapper as PastoralTypeActionMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubtypeMapper;
use Schoolbox\Pastoral\SubType\Model;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Custom methods for API testing
 *
 * Run "./vendor/bin/codecept build" to access methods in this class
 */
//class Api extends \Codeception\Module
class Api extends \Codeception\Module
{
    /** @var Kernel */
    public Kernel $cliKernel;

    private UserMapper $userMapper;

    private MapperProxy $assessmentDataSource;

    private FolderMapper $folderMapper;

    private RoleMapper $roleMapper;

    private array $originalConfig = [];

    private Folder $classFolder;

    private CourseSpecMapper $courseSpecMapper;

    private CourseImplMapper $courseImplMapper;

    private UnitSpecMapper $unitSpecMapper;

    private UnitImplMapper $unitImplMapper;

    private EventMapper $eventMapper;

    private EventTypeMapper $eventTypeMapper;

    /**
     * HOOK: executed after configuration is loaded
     *
     * Initializes the cli kernel so that we can use the dependency injector.
     * @throws MapperException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \Schoolbox\External\Exception
     */
    public function _initialize(): void
    {
        parent::_initialize();
        $app = new class () extends \Schoolbox\Application\CliTool\App {
            public function run(): void
            {
            }
        };
        // TODO Expose kernel options as Helper config in codeception.dist.yml
        $this->cliKernel    = new Kernel(
            $app,
            [Kernel::OPTION_ERRORS => E_ALL]
        );
        /** @var Rebuilder $cacheBuilder */
        $cacheBuilder = $this->cliKernel->get('moduleCache.rebuilder');
        $cacheBuilder
            ->setLogger(new NullLogger())
            ->rebuild($this->cliKernel->get('config')->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));

        $this->userMapper   = UserMapper::getInstance();
        $this->folderMapper = FolderMapper::getInstance();
        $this->roleMapper   = RoleMapper::getInstance();
        try {
            $classFolder = $this->folderMapper->findByCode('DEMO1');
        } catch (\Exception $e) {
            $classFolder = $this->folderMapper->findByCode('9ENGA');
        }
        $classFolder->loadTeachers();
        $this->assessmentDataSource = $this->cliKernel->get('assessmentDataSource');
        $this->classFolder          = $classFolder;
        $this->courseSpecMapper     = CourseSpecMapper::getInstance();
        $this->courseImplMapper = CourseImplMapper::getInstance();
        $this->unitSpecMapper   = UnitSpecMapper::getInstance();
        $this->unitImplMapper   = UnitImplMapper::getInstance();
        $this->eventMapper      = EventMapper::getInstance();
        $this->eventTypeMapper  = EventTypeMapper::getInstance();
    }

    private array $users = [];

    /**
     * @throws MapperException
     */
    public function _before(TestInterface $test): void
    {
        parent::_before($test);
        $this->userMapper = UserMapper::getInstance();

        $uniqid = uniqid((string)mt_rand(), true);

        $this->users['super']   = $this->createTestUser("tester-superuser-$uniqid", Role::STAFF, true);
        $this->users['staff']   = $this->createTestUser("tester-staff-$uniqid", Role::STAFF, false);
        $this->users['student'] = $this->createTestUser("tester-student-$uniqid", Role::STUDENT, false);
        $this->users['parent']  = $this->createTestUser("tester-parent-$uniqid", Role::PARENT, false);
        $this->users['guest']   = $this->createTestUser("tester-guest-$uniqid", Role::GUEST, false);
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @param string $username
     * @return User
     */
    public function getUserByUsername(string $username): User
    {
        return $this->userMapper->findByUsername($username);
    }

    /**
     * @return Folder
     */
    public function getFolder(): Folder
    {
        return $this->classFolder;
    }

    /**
     * Generate token for user
     * @return string
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAdminToken(): string
    {
        $admin = $this->userMapper->findByUsername('alaress');
        /** @var Jwt $tokenBuilder */
        $tokenBuilder = $this->cliKernel->get('user.tokenBuilder');
        return $tokenBuilder->generate($admin);
    }

    /**
     * Sets database config value temporally
     * @param string $name
     * @param mixed  $value
     * @throws ModuleException
     */
    public function changeDBConfig(string $name, $value)
    {
        $db = $this->getDbModule();

        $currentValue = $db->grabFromDatabase('config', 'value', ['name' => $name]);
        if ($value === $currentValue) {
            // no need to change
            return;
        }

        if (!key_exists($name, $this->originalConfig)) {
            $this->originalConfig[$name] = $currentValue;
        }

        $db->updateInDatabase('config', ['value' => $value], ['name' => $name]);
    }

    /**
     * Resets database value back
     */
    public function restoreDBConfig()
    {
        $db = $this->getDbModule();
        foreach ($this->originalConfig as $name => $value) {
            $db->updateInDatabase('config', ['value' => $value], ['name' => $name]);
        }
    }

    /**
     * @param string $username
     * @param string $roleType
     * @param bool   $superUser
     * @return User
     * @throws MapperException
     */
    public function createTestUser(string $username, string $roleType, bool $superUser): User
    {
        $user = $this->userMapper->createModel()
                                 ->setUsername($username)
                                 ->setFirstname($username)
                                 ->setRole($this->getRole($roleType))
                                 ->setCampusCollection($this->getCampuses())
                                 ->setLocalEmail(false)
                                 ->setUseInternalMail(false)
                                 ->setEportfolioEnable(false)
                                 ->setEnabled(true)
                                 ->setSuperuser($superUser);

        $this->userMapper->save($user);
        return $user;
    }

    /**
     * @param User $groupAdmin
     * @param User $readMember
     * @return Folder
     * @throws MapperException
     */
    public function createGroup(User $groupAdmin, User $readMember): Folder
    {
        $template = HomepageTemplateMapper::getInstance()->findAll()->first();
        $parent   = $this->folderMapper->findByParent(0)->last();

        $folder = $this->folderMapper->createModel()
                                     ->setName('GROUP ' . $this->generateText(5))
                                     ->setParent($parent);

        $homepageConfig = (new HomepageConfig())
            ->setCategory($folder)
            ->setTemplate($template);
        $folder->setHomepage($homepageConfig);
        $this->folderMapper->save($folder);
        HomepageConfigMapper::getInstance()->save($homepageConfig);

        $group = (new Group())->setType(Group::FREE_GROUP)
                              ->setFolder($folder)
                              ->setForceFollow(false)
                              ->setNotificationDisabled(false);

        GroupMapper::getInstance()->save($group);

        $roleIds = $this->roleMapper->findAll()->getPropertyValues('id');
        RoleAccessMapper::getInstance()->savePermissions($folder, array_combine($roleIds, array_fill(0, count($roleIds), 0)));

        $userAccessMapper = UserAccessMapper::getInstance();
        $access           = $userAccessMapper->createModel()
                                             ->setServiceIdentifier($folder->getId())
                                             ->setService(UserAccess::SERVICE_HOMEPAGE)
                                             ->setUser($groupAdmin)
                                             ->setAccessLevel(UserAccess::ACCESS_FULL);
        $userAccessMapper->save($access);
        $access = $userAccessMapper->createModel()
                                   ->setServiceIdentifier($folder->getId())
                                   ->setService(UserAccess::SERVICE_HOMEPAGE)
                                   ->setUser($readMember)
                                   ->setAccessLevel(UserAccess::ACCESS_READ);
        $userAccessMapper->save($access);
        return $folder;
    }

    /**
     * @return GenericCollection
     */
    public function getCampuses(): GenericCollection
    {
        return CampusMapper::getInstance()->findAll();
    }

    /**
     * @param string $roleType
     * @return Role
     * @throws MapperException
     */
    public function getRole(string $roleType): Role
    {
        try {
            return $this->roleMapper->findOneByColumn('group_type', $roleType);
        } catch (MapperException $e) {
            $admin   = $this->userMapper->findByUsername('alaress');
            $newRole = (new Role())->setType($roleType)
                                   ->setUpdatedBy($admin->getId())
                                   ->setName($roleType . ' ' . $this->generateText(5));
            $newRole->setSequence(10);
            $this->roleMapper->save($newRole);
            return $newRole;
        }
    }

    /**
     * @return GenericCollection
     */
    public function getPastoralTypes(): GenericCollection
    {
        return PastoralTypeMapper::getInstance()->findAll();
    }

    /**
     * @return GenericCollection
     */
    public function getPastoralSeverities(): GenericCollection
    {
        return PastoralSeverityMapper::getInstance()->findAll();
    }

    /**
     * @return GenericCollection
     */
    public function getPastoralTags(): GenericCollection
    {
        return PastoralTagMapper::getInstance()->findAll();
    }

    /**
     * @return GenericCollection
     * @throws MapperException
     * @throws ModuleException
     */
    public function preparePastoralActions(): GenericCollection
    {
        $pastoralActionMapper     = PastoralActionMapper::getInstance();
        $pastoralSubtypeMapper    = PastoralSubtypeMapper::getInstance();
        $pastoralTypeMapper       = PastoralTypeMapper::getInstance();
        $pastoralTypeActionMapper = PastoralTypeActionMapper::getInstance();
        $actions                  = $pastoralActionMapper->findAll();

        if ($actions->count() === 0) {
            for ($i = 0; $i < 5; $i++) {
                $newAction = $pastoralActionMapper->createModel()
                                                  ->setName($this->generateText(50))
                                                  ->setSequence($i);
                $pastoralActionMapper->save($newAction);
            }
            $actions = $pastoralActionMapper->findAll();
        }

        $types = $pastoralTypeMapper->findAll();
        foreach ($types as $type) {
            if ($type->getSubTypes()->count()) {
                /** @var Model $subType */
                $subType    = $type->getSubTypes()->first();
                $typeAction = $pastoralTypeActionMapper->createModel()
                                                       ->setAction($actions->first())
                                                       ->setDefault(false)
                                                       ->setSubTypeId($subType->getId());
                $subType->setTypeActions($pastoralTypeActionMapper->createCollection()->add($typeAction));
                $pastoralSubtypeMapper->save($subType);
            } else {
                $typeAction = $pastoralTypeActionMapper->createModel()
                                                       ->setAction($actions->last())
                                                       ->setDefault(false)
                                                       ->setTypeId($type->getId());
                $type->setTypeActions($pastoralTypeActionMapper->createCollection()->add($typeAction));
                $pastoralTypeMapper->save($type);
            }
        }

        $test = $this->getMaxValue('lu_pastoral_action', 'id');

        return $pastoralActionMapper->findAll();
    }


    private function import()
    {
        $now   = \Alaress_Date_Format::now();
        $close = (clone $now)->addDays(10);

        $courseSpecFolder = $this->folderMapper->createModel()
                                               ->setParent($this->folderMapper->findById(2)) // TODO: Course folder
                                               ->setName('Course ' . $this->generateText(10))
                                               ->setCurriculumNodes(new GenericCollection(Node::class))
                                               ->setInherit(true);
        $this->folderMapper->save($courseSpecFolder);


        // Save the course spec
        $courseSpec = $this->courseSpecMapper->createModel()
                                             ->setFolder($courseSpecFolder);
        $this->courseSpecMapper->save($courseSpec);

        // Create and save unit spec folder
        $unitSpecFolder = $this->folderMapper->createModel()
                                             ->setParent($courseSpec->getFolder())
                                             ->setName('Unit ' . $this->generateText(10))
                                             ->setCurriculumNodes(new GenericCollection(Node::class))
                                             ->setInherit(true);
        $this->folderMapper->save($unitSpecFolder);


        // Save the unit spec
        $unitSpec = $this->unitSpecMapper->createModel()
                                         ->setFolder($unitSpecFolder)
                                         ->setCourse($courseSpec);
        $this->unitSpecMapper->save($unitSpec);

        $courseImpl = $this->courseImplMapper->createModel()
                                             ->setSpecification($courseSpec)
                                             ->setFolder($this->classFolder);
        $this->courseImplMapper->save($courseImpl);

        $unitImpl = $this->unitImplMapper->createModel()
                                         ->setTitle($unitSpec->getTitle())
                                         ->setOpenDate($now)
                                         ->setDueDate($close)
                                         ->setSpecification($unitSpec)
                                         ->setCourse($courseImpl);
        $this->unitImplMapper->save($unitImpl);
        return $unitImpl;
    }


    public function createDueWork(AssessmentContext $context, float $weight)
    {
        $staff = $this->getUsers()['staff'];
        $now   = \Alaress_Date_Format::now();
        $close = (clone $now)->addDays(10);

        $dueWork = new DueWork();
        $dueWork
            ->setContext($context)
            ->setTitle('Due Work ' . $this->generateText(6))
            ->setDescription($this->generateText(100))
            ->setWeight($weight)
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE)
            ->setCreator($staff)
            ->setMarkScheme(new Percentage())
            ->setOpenAt($now)
            ->setPublishAt($now)
            ->setDueAt($close)
            ->setCloseAt($close)
            ->setMarkVisibility(SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT);

        if ($context instanceof Project) {
            $context->getAssessables()->add($dueWork);
        }

        $this->assessmentDataSource->save($dueWork, $staff);
        return $dueWork;
    }

    public function createProject(string $summator, bool $requireAll)
    {
        $unit     = $this->import();
        $summator = (new Factory($summator, $requireAll))->produce();

        $staff   = $this->getUsers()['staff'];
        $now     = \Alaress_Date_Format::now();
        $close   = (clone $now)->addDays(10);
        $project = new Project();
        $project
            ->setContext($unit)
            ->setTitle('Project ' . $this->generateText(6))
            ->setDescription($this->generateText(100))
            ->setWeight(0)
            ->setCreator($staff)
            ->setMarkScheme(new Percentage())
            ->setSummator($summator)
            ->setRequireAllActivities($requireAll)
            ->setOpenAt($now)
            ->setPublishAt($now)
            ->setDueAt($close)
            ->setCloseAt($close)
            ->setMarkVisibility(SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT);

        $this->assessmentDataSource->save($project, $staff);
        return $project;
    }

    public function createTask(AssessmentContext $context)
    {
        $staff = $this->getUsers()['staff'];
        $now   = \Alaress_Date_Format::now();
        $close = (clone $now)->addDays(10);

        $task = new Task();
        $task
            ->setContext($context)
            ->setTitle('Task ' . $this->generateText(6))
            ->setDescription($this->generateText(100))
            ->setWeight(0)
            ->setCreator($staff)
            ->setMarkScheme(new Percentage())
            ->setOpenAt($now)
            ->setPublishAt($now)
            ->setDueAt($close)
            ->setCloseAt($close)
            ->setMarkVisibility(SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT);

        $context->getAssessables()->add($task);
        $this->assessmentDataSource->save($task, $staff);
        return $task;
    }

    public function createQuiz(AssessmentContext $context, float $weight)
    {
        $staff = $this->getUsers()['staff'];
        $now   = \Alaress_Date_Format::now();
        $close = (clone $now)->addDays(10);

        $quiz = new Quiz();
        $quiz
            ->setContext($context)
            ->setTitle('Quiz ' . $this->generateText(6))
            ->setDescription($this->generateText(100))
            ->setWeight($weight)
            ->setCreator($staff)
            ->setMarkScheme(new Percentage())
            ->setMaxAttempts(0) // unlimited
            ->setOpenAt($now)
            ->setPublishAt($now)
            ->setDueAt($close)
            ->setCloseAt($close)
            ->setMarkVisibility(SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT);

        $context->getAssessables()->add($quiz);
        $this->assessmentDataSource->save($quiz, $staff);
        return $quiz;
    }

    public function addReview(IAssessable $assessment, User $student, User $teacher, ?float $normalisedMark)
    {
        /** @var FeedbackDataSource $feedbackDataSource */
        $feedbackDataSource = $this->cliKernel->get('feedbackDataSource');

        $now = \Alaress_Date_Format::now();

//        /** @var ResponseDataSource $responseDataSource */
//        $responseDataSource = $this->cliKernel->get('responseDataSource');
        /** @var \Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy $attemptDataSource */
        $attemptDataSource = $this->cliKernel->get('attemptDataSource');

        $feedback = $feedbackDataSource->createModel($assessment)
                                       ->setHumanReadableMark((new Percentage())->getHumanReadableMark($normalisedMark))
                                       ->setNormalisedMark($normalisedMark)
                                       ->setCreatedAt($now)
                                       ->setPublishedAt($now)
                                       ->setReviewee($student)
                                       ->setReviewer($teacher);
        if ($feedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model) {
            $feedback->setReviewRoute(SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);
        }
        if ($feedback instanceof \Schoolbox\Learning\Assessment\Quiz\Feedback\Model) {
            $attempt = (new \Schoolbox\Learning\Assessment\Quiz\Attempt\Model())->setAssessable($assessment)
                                                                                ->setMarksAvailable(100)
                                                                                ->setMarksGiven(100)
                                                                                ->setSubmittedOnBehalfOf($student)
                                                                                ->setSubmittedAt($now)
                                                                                ->setCreatedAt($now);
            $attemptDataSource->save($attempt);
            $feedback->setResponse($attempt);
        }

        $feedbackDataSource->save($feedback);
//        $projectResultCacheMapper = Project\ResultCache\Mapper::getInstance();
//        $projectResultCache       = new Project\ResultCache\ResultCache($projectResultCacheMapper);
//        $projectResultCache->refresh($this->assessmentDataSource, $assessment);
    }

    /**
     * @param User $owner
     * @param int  $accessLevel
     * @param int  $attendance
     * @param bool $showAttendance
     * @return Event
     * @throws MapperException
     */
    public function createEvent(User $owner, int $accessLevel, int $attendance, bool $showAttendance): Event
    {
        $event = $this->eventMapper->createModel();
        $event->setTitle($this->generateText(50))
              ->setAuthor($owner)
              ->setOwner($owner)
              ->setAccess($accessLevel)
              ->setAttendance($attendance)
              ->setShowAttendees($showAttendance)
              ->setCampusCollection(Campus::createCollection())
              ->setType($this->eventTypeMapper->findAll()->first())
              ->setStartDateTime(\Alaress_Date_Format::now()->addDays(1))
              ->setEndDateTime(\Alaress_Date_Format::now()->addDays(1)->addHours(1));
        $this->eventMapper->save($event);
        return $event;
    }

    /**
     * Return DB module in a type-safe manner.
     *
     * @return Db
     * @throws ModuleException
     */
    private function getDbModule(): Db
    {
        return $this->getModule('Db');
    }

    /**
     * @param int $length
     * @return string
     */
    public function generateText(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz.012345678!@#$%*&^()-+';

        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if ($length > 8 && rand(1, 7) === 5) {
                $randomString .= ' ';
                $i++;
            }
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }


    /**
     * @param string $tableName
     * @param string $column
     * @return mixed
     * @throws ModuleException
     */
    public function getMaxValue(string $tableName, string $column): mixed
    {
        return $this->getDbModule()->grabFromDatabase($tableName, "max($column)");
    }
}
