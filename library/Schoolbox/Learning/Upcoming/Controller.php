<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Upcoming;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;
use DomainException;
use External_ClassInterface;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Calendar\Events\Api;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

use function func_get_args;

/**
 * Controller for the upcoming work screen.
 */
class Controller extends Base
{

    /**
     * Limit of upcoming assessments shown in list
     */
    protected const UPCOMING_WORK_LIMIT = 20;

    /**
     * External Class data source
     *
     * @var External_ClassInterface
     */
    protected $externalClass;

    /**
     * Folder data source
     *
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * User data source
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Term data source
     *
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * Assessment data source
     *
     * @var AssessmentMapperProxy
     */
    protected $assessmentDataSource;

    /**
     * Participant data source
     *
     * @var ParticipantDataSource
     */
    protected $participantDataSource;

    /** @var Filter\IFactoryFactory */
    private $filterFactoryFactory;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;

    /** @var Access */
    private $access;


    /**
     * Constructor
     *
     * @param External_ClassInterface            $externalClass
     * @param FolderMapper                       $folderMapper
     * @param UserMapper                         $userMapper
     * @param TermMapper                         $termMapper
     * @param AssessmentMapperProxy              $assessmentDataSource
     * @param ParticipantDataSource              $participantDataSource
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Access                             $access
     */
    public function __construct(
        External_ClassInterface $externalClass,
        FolderMapper $folderMapper,
        UserMapper $userMapper,
        TermMapper $termMapper,
        AssessmentMapperProxy $assessmentDataSource,
        ParticipantDataSource $participantDataSource,
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Filter\Selection\Mapper $filterSelectionMapper,
        Access $access
    ) {
        $this->externalClass         = $externalClass;
        $this->folderMapper          = $folderMapper;
        $this->userMapper            = $userMapper;
        $this->termMapper            = $termMapper;
        $this->assessmentDataSource  = $assessmentDataSource;
        $this->participantDataSource = $participantDataSource;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->access                = $access;
    }


    /**
     * Redirects to the upcoming work screen for the current user.
     *
     * @api GET /learning/due/
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getUpcoming(): void
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningUpcoming.getUpcomingForStudent',
                ['studentId' => $this->getUser()->getId(),]
            )
        );
    }


    /**
     * Displays upcoming work for the given user.
     *
     * @api   GET /learning/due/:studentId{/:date}
     * @param User                $student
     * @param Alaress_Date_Format $date
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws DefinitionException
     * @throws \UnderflowException
     * @throws UnexpectedValueException
     */
    public function getUpcomingForStudent(User $student, Alaress_Date_Format $date): void
    {
        // This implementation ignores that URL date. Calendar and Subject list is always current.
        /** @noinspection SuspiciousAssignmentsInspection */
        $date = Alaress_Date_Format::now();

        $page        = Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS;
        $route       = $this->getRouter()->generate("learningUpcoming.postUpcomingForStudent", ['student' => $student, 'studentId' => $student->getId()]);
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            $page,
            $route,
            $student,
            [$student, $date]
        );

        // Only get subjects from external if student's timetable isn't hidden.
        // I don't get this logic as class list != timetable, but apparently
        // it's important. ¯\_(ツ)_/¯
        $folders = $this->folderMapper->findForUser($student, !$student->getHideTimetable(), UserAccess::ACCESS_READ, false, true);

        // Student's subjects and teachers
        $subjects = [];

        // Calculate whether we need to load participation so we can calculate class aggregate grades
        $displayGradesForFolders = [];
        $isStaff                   = $this->getUser()->getRole()->isStaff();
        $isStudentOrParent         = $this->getUser()->getRole()->isStudent() || $this->getUser()->getRole()->isParent();

        foreach ($folders as $folder) {
            // Load teachers for this subject (if this subject has them)
            $teachers = [];
            if (\count($folder->getCodes())) {
                // Actual teachers only
                $teacherCodes = $this->externalClass->getTeachers($folder->getCodeString());
                if (\count($teacherCodes)) {
                    $pb       = $this->userMapper->getPredicateBuilder()
                                                 ->in('synergy_id', $teacherCodes)
                                                 ->equals('g.group_type', Role::STAFF);
                    $teachers = $this->userMapper->findByPredicates($pb);
                }
            }
            $perm = $this->access->getFolderAccess($this->getUser(), $folder->getId());

            // We need to fetch assessments so we can show overall grades for each folder if:
            // * viewer is staff/instructor
            // * viewer is student/parent && 'show_overall_grade' is ON .. will be taken care of later
            if ($isStaff || $perm >= UserAccess::ACCESS_WRITE) {
                $displayGradesForFolders[] = $folder;
            }

            $subjects[$folder->getId()] = [
                'teachers' => $teachers,
                'folder'   => $folder,
                'grade'    => '',
            ];
        }

        $term = $this->termMapper->findForDate($date);

        $markSchemeFactory = new Factory($this->getConfigLoader());

        if (\count($displayGradesForFolders)) {
            $visibility = new Visibility($this->access);
            // Student's work
            $workPerFolder = $this->assessmentDataSource
                ->findAllByStudent($student, true, $term)
                ->filter(function (IAssessable $assessable) use ($visibility, $student) {
                    return $visibility->isVisibleToUser($assessable, $student, true);
                })
                ->groupByCallback(function (IAssessable $assessable) {
                    return $assessable->getContext()->getFolder()->getId();
                });


            foreach ($workPerFolder as $folderId => $assessments) {
                $workPerFolder[$folderId] = $assessments->indexBy('id');
            }

            foreach ($displayGradesForFolders as $folder) {
                if ($workPerFolder->keyExists($folder->getId())) {
                    $markScheme = $markSchemeFactory->spawnForFolder($folder, $isStaff);
                    $markbook   = new Markbook($workPerFolder[$folder->getId()], $markScheme, $this->getUser());
                    $subjects[$folder->getId()]['grade'] = $markbook->getStudentStatistics()[$student->getId()];
                }
            }
        }

        $data = [
            'student'         => $student,
            'date'            => $date,
            'subjects'        => $subjects,
            'mailHandle'      => $this->getSession()->get('mailhandle'),
            'currentTerm'     => $term,
            'children'        => $this->getUser()->getChildren(),
            'filterPanel'     => $filterPanel,
            'filterIdSlugMap' => $this->buildFilterIdSlugMap($filterPanel),
        ];

        $namespace = Module::BASENAME;
        $this->getResponse()->content->set(
            $this->buildHtmlOutput("@$namespace/upcomingOuter.twig", $data)
                 ->setTitle("Upcoming Due Work")
                 ->setTab('submission') // ¯\_(ツ)_/¯
        );
    }


    /** @noinspection PhpUnused */
    /**
     * Returns a JSON payload containing a HTML fragment where the HTML fragment is a filtered list of assessments.
     *
     * @api /learning/due/{studentId:\d+}
     *
     * @param User $student
     * @throws Filter\BrokenFilterException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws DefinitionException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function postUpcomingForStudent(User $student): void
    {
        $request             = $this->request->post->get('filter_selection', []);
        $page                = Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS;
        $codec               = new Codec(Filter\Mapper::getInstance(), $this->filterSelectionMapper);
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $filters             = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Only fetch assessments that are:
        // * assessment impls
        $filters = \array_merge($filters, [
            Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
        ]);

        // If there's a huge number of IDs, we may need to chunk them and query
        // on each chunk.
        $ids     = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
        if (count($ids) > 0) {
            $allWork = $this->assessmentDataSource
                ->findAllByStudentBetweenDates($student, true, null, null, ['ids' => $ids]);

            $visibility = new Visibility($this->access);
            /** @var IAssessable[]|GenericCollection $upcomingWork */
            $upcomingWork = $allWork
                ->filter(static function (IAssessable $assessable) use ($student, $visibility) {
                    $participation = $assessable->getParticipantIfExtant($student);
                    return $participation
                        && ($participation->isResponseRequired() || $participation->isResubmissionRequired())
                        && $visibility->isVisibleToUser($assessable, $student, false);
                })
                ->sortByCallback(new DueDateSortStrategy($student))
                ->limit(self::UPCOMING_WORK_LIMIT);
        } else {
            $upcomingWork = new Collection();
        }

        // Get list of folders current user is permitted to visit
        $readableFolders = [];
        foreach ($upcomingWork as $assessable) {
            $folder = $assessable->getContext()->getFolder();
            if ($this->access->getFolderAccess($this->getUser(), $folder->getId()) >= UserAccess::ACCESS_READ) {
                $readableFolders[] = $folder->getId();
            }
        }

        $data = [
            'student'         => $student,
            'upcomingWork'    => $upcomingWork->limit(self::UPCOMING_WORK_LIMIT),
            'returnUrl'       => Redirect::queryString(RequestUriParser::parseUrlPath($this->request->server->getArrayCopy())),
            'readableFolders' => \array_unique($readableFolders),
            'isFiltered'      => (new Filter\RequestChecker())->isFilteredRequest($request),
        ];

        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 200,
            'content' => $this->buildUnthemedHtmlOutput('@learningUpcoming/upcomingInner.twig', $data)->getAsString(),
        ]));
    }


    /**
     * Returns a array that maps filter ids to slugs so we can apply semantics to the IDs
     *
     * @param Filter\Panel $filterPanel
     * @return array
     */
    private function buildFilterIdSlugMap(Filter\Panel $filterPanel): array
    {
        $filterIdMap = [];
        foreach ($filterPanel->getComponents() as $component) {
            $definition                                     = $component->getDefinition();
            $filterIdMap[$definition->getFilter()->getId()] = $definition::getSlug();
        }
        return $filterIdMap;
    }

    /**
     * @param User $student
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(User $student): void
    {
        // Due work calendar could take a while
        $this->extendExecutionTime();

        $request             = $this->request->query->get('filters', []);
        $page                = Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS;
        $codec               = new Codec(Filter\Mapper::getInstance(), $this->filterSelectionMapper);
        $selectionsPerFilter = $codec->convertRequestToSelections($student, $page, $request);
        $filters             = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);
        $visibility          = new Visibility($this->access);

        // User must have provided a start and end date: error out otherwise
        $start = filter_var($this->request->query->get('start'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $end   = filter_var($this->request->query->get('end'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (!isset($start, $end)) {
            // No you don't throw any exception: only ApplicationException
            /** @noinspection PhpUnhandledExceptionInspection */
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INVALID_DATE);
        }

        // Query the API for the events in this time span
        $calendar = new Renderer(
            new Api(
                $student,
                $this->assessmentDataSource,
                $visibility,
                $this->assessmentIdFinder,
                $filters
            ),
            $start,
            $end,
            $student,
            $this->getAuthorizer(),
            $this->getRouter(),
            $this->getUser()
        );

        $this->response->content->set($this->buildJsonOutput(
            array_map('json_decode', $calendar->renderAll(EventBase::RENDER_FORMAT_JSON))
        ));
    }
}
