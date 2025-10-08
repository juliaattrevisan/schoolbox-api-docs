<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module\Db;
use Codeception\TestInterface;
use PDO;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Comms\News\Mapper as NewsMapper;
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
use Schoolbox\Mapper\Exception\Deleted;
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
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Textbox\Mapper as TextBoxMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\User\Token\Mapper as TokenMapper;
use Schoolbox\UserMapper;

/**
 * Custom methods for API testing
 *
 * Run "./vendor/bin/codecept build" to access methods in this class
 */
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

    private NewsMapper $newsMapper;

    private TokenMapper $tokenMapper;

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
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
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
        $this->userMapper   = UserMapper::getInstance();
        $this->folderMapper = FolderMapper::getInstance();
        $this->roleMapper   = RoleMapper::getInstance();
        try {
            $classFolder = $this->folderMapper->findByCode('DEMO2');
        } catch (\Exception $e) {
            $classFolder = $this->folderMapper->findByCode('9ENGA');
        }
        $classFolder->loadTeachers();
        $this->assessmentDataSource = $this->cliKernel->get('assessmentDataSource');
        $this->classFolder          = $classFolder;
        $this->courseSpecMapper     = CourseSpecMapper::getInstance();
        $this->courseImplMapper     = CourseImplMapper::getInstance();
        $this->unitSpecMapper       = UnitSpecMapper::getInstance();
        $this->unitImplMapper       = UnitImplMapper::getInstance();
        $this->eventMapper          = EventMapper::getInstance();
        $this->eventTypeMapper      = EventTypeMapper::getInstance();
        $this->newsMapper           = NewsMapper::getInstance();
        $this->tokenMapper          = TokenMapper::getInstance();
    }

    private array $users = [];

    /**
     * @throws MapperException
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
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
     * @return  \Schoolbox\User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @param string $username
     * @return  \Schoolbox\User
     */
    public function getUserByUsername(string $username): \Schoolbox\User
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
        $token = $this->tokenMapper->generateForUser($admin, $admin, \Alaress_Date_Format::now()->addSeconds(JWT::API_JWT_TIMEOUT), User\Token\Model::TYPE_APITEST);
        /** @var Jwt $tokenBuilder */
        $tokenBuilder = $this->cliKernel->get('user.tokenBuilder');
        return $tokenBuilder->generate($admin, $token->getJwtId());
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
     * @return  \Schoolbox\User
     * @throws MapperException
     */
    public function createTestUser(string $username, string $roleType, bool $superUser): \Schoolbox\User
    {
        $user = $this->userMapper->createModel()
                                 ->setUsername($username)
                                 ->setFirstname($username)
                                 ->setLastname($this->generateText(8, true))
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
     * @param \Schoolbox\User $groupAdmin
     * @param \Schoolbox\User $readMember
     * @return Folder
     * @throws MapperException
     */
    public function createGroup(\Schoolbox\User $groupAdmin, \Schoolbox\User $readMember): Folder
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
        return $this->setGroup($folder, $groupAdmin, $readMember);
    }

    /**
     * @param Folder $folder
     * @param User   $groupAdmin
     * @param User   $readMember
     * @return Folder
     * @throws MapperException
     */
    public function setGroup(Folder $folder, User $groupAdmin, User $readMember): Folder
    {
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
            ->setInstructorMarkScheme(new Percentage())
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
            ->setDueAt($close);
            // The function calls below have been commented out to get around a PHPStan warning:
            // "Call to method Schoolbox\Learning\Assessment\Task\Model::setMarkVisibility() on a separate line has no effect."
            //->setCloseAt($close)                                                      // Note Assessment\Task\Model->setCloseAt() doesn't actually do anything.
            //->setMarkVisibility(SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT); // Note Assessment\Task\Model->setMarkVisibility() doesn't actually do anything.

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

    public function addReview(IAssessable $assessment, \Schoolbox\User $student, \Schoolbox\User $teacher, ?float $normalisedMark)
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
            /* Presume Total Mark Fractional mark scheme for historical data,
             * causing human readable mark to be cached in the format "$numerator / $denominator"
             */
            $attempt = (new \Schoolbox\Learning\Assessment\Quiz\Attempt\Model())->setAssessable($assessment)
                                                                                ->setMarksAvailable(100)
                                                                                ->setHumanReadableMark("100 / 100")
                                                                                ->setNormalisedMark(100 * 100 / 100)
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
     * @param User        $owner
     * @param int         $accessLevel
     * @param int         $attendance
     * @param bool        $showAttendance
     * @param Folder|null $folder
     * @return Event
     * @throws MapperException
     */
    public function createEvent(\Schoolbox\User $owner, int $accessLevel, int $attendance, bool $showAttendance, ?Folder $folder): Event
    {
        $event = $this->eventMapper->createModel();
        $event->setTitle($this->generateText(50))
              ->setDetail($this->generateText(50))
              ->setAuthor($owner)
              ->setOwner($owner)
              ->setAccess($accessLevel)
              ->setAttendance($attendance)
              ->setShowAttendees($showAttendance)
              ->setCampusCollection(Campus::createCollection())
              ->setType($this->eventTypeMapper->findAll()->first())
              ->setStartDateTime(\Alaress_Date_Format::now()->addDays(1))
              ->setEndDateTime(\Alaress_Date_Format::now()->addDays(1)->addHours(1));
        if ($folder) {
            $event->setFolderCollection($this->folderMapper->createCollection([$folder]));
        }
        $this->eventMapper->save($event);
        return $event;
    }


    /**
     * @param User   $owner
     * @param Folder $folder
     * @return \Schoolbox\Blog\Homepage\Post\Model
     * @throws MapperException
     */
    public function createBlog(User $owner, Folder $folder): \Schoolbox\Blog\Homepage\Post\Model
    {
        $blogMapper = BlogMapper::getInstance();
        $blog       = $blogMapper->createModel();
        $blog->setFolder($folder)
             ->setCreatedAt(\Alaress_Date_Format::now())
             ->setBody($this->generateText(1000))
             ->setOwner($owner)
             ->setHeading($this->generateText(50));
        $blogMapper->save($blog);
        return $blog;
    }

    /**
     * Return DB module in a type-safe manner.
     *
     * @return Db
     * @throws ModuleException
     */
    private function getDbModule(): Db
    {
        /** @var Db */
        return $this->getModule('Db');
    }

    /**
     * @param int  $length
     * @param bool $alphabetOnly
     * @return string
     */
    public function generateText(int $length, bool $alphabetOnly = false): string
    {
        $characters = $alphabetOnly ? 'abcdefghijklmnopqrstuvwxyz' : 'abcdefghijklmnopqrstuvwxyz.012345678!@#$%*&^()-+';

        $randomString = '';
        // length of a word so that a single word to be more than 3 chars (for search tests)
        $wordLen = 0;
        for ($i = 0; $i < $length; $i++) {
            if (!$alphabetOnly && $length > 8 && $wordLen > 3 && rand(1, 7) === 5) {
                $randomString .= ' ';
                $i++;
                $wordLen = 0;
            }
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
            $wordLen++;
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

    /**
     * @param int|null $assessmentId
     * @return IAssessable|null
     * @throws ModuleException
     */
    public function getExistingAssessment(?int $assessmentId): ?IAssessable
    {
        $db = $this->getDbModule();
        if ($assessmentId) {
            $assessmentIds = [$assessmentId];
        } else {
            $assessmentIds = $db->grabColumnFromDatabase('submission_box', 'assessment_id', []);
        }
        foreach ($assessmentIds as $assessmentId) {
            try {
                $assessment = $this->assessmentDataSource->findById($assessmentId);
                if ($assessment->getContext()->isImplementationContext()) {
                    return $assessment;
                }
            } catch (MapperException $e) {
            }
        }
        return null;
    }

    /**
     * @param int|null $articleId
     * @return \Schoolbox\Comms\News\Model
     * @throws MapperException
     * @throws ModuleException
     */
    public function getExistingNewsArticle(?int $articleId): \Schoolbox\Comms\News\Model
    {
        $db = $this->getDbModule();
        if ($articleId) {
            $articleIds = [$articleId];
        } else {
            $articleIds = $db->_getDriver()->executeQuery(
                // Select the id of a currently published article.
                // Query 'WHERE' conditions copied from News\IndexBuilder->getSqlConditionForCurrentlyPublished().
                'SELECT id
                FROM comms_news_article
                WHERE submitted_at IS NOT NULL AND
                rejected_at IS NULL AND
                accepted_at IS NOT NULL AND
                (publish_at IS NOT NULL AND publish_at <= NOW()) AND
                deleted_at IS NULL
                ORDER BY RAND()
                LIMIT 1',
                []
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        return $this->newsMapper->findById($articleIds[0]);
    }

    /**
     * @return \Schoolbox\Resource\File\Model
     * @throws MapperException
     * @throws ModuleException
     */
    public function getExistingFile(): \Schoolbox\Resource\File\Model
    {
        $db      = $this->getDbModule();
        $fileIds = $db->grabColumnFromDatabase('file', 'id', ['deleted_at' => null]);
        return FileMapper::getInstance()->findById($fileIds[0]);
    }

    /**
     * @return \Schoolbox\Resource\Textbox\Model
     * @throws Deleted
     * @throws MapperException
     * @throws ModuleException
     */
    public function getExistingTextBox(): \Schoolbox\Resource\Textbox\Model
    {
        $db         = $this->getDbModule();
        $textBoxIds = $db->grabColumnFromDatabase('textBox', 'textBoxID', ['deleted_at' => null]);
        /** @var \Schoolbox\Resource\Textbox\Model $textBox */
        $textBox = TextBoxMapper::getInstance()->findById($textBoxIds[0]);
        return $textBox;
    }
}
