<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Upcoming;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
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
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnderflowException;
use UnexpectedValueException;

use function array_merge;
use function array_unique;
use function count;

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
     * Constructor
     *
     * @param External_ClassInterface            $externalClass         - External Class data source.
     * @param FolderMapper                       $folderMapper          - Folder data source.
     * @param UserMapper                         $userMapper            - User data source.
     * @param TermMapper                         $termMapper            - Term data source.
     * @param AssessmentMapperProxy              $assessmentDataSource  - Assessment data source.
     * @param ParticipantDataSource              $participantDataSource - Participant data source.
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Access                             $access
     */
    public function __construct(
        protected External_ClassInterface $externalClass,
        protected FolderMapper $folderMapper,
        protected UserMapper $userMapper,
        protected TermMapper $termMapper,
        protected AssessmentMapperProxy $assessmentDataSource,
        protected ParticipantDataSource $participantDataSource,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private Access $access
    ) {}

    /**
     * Redirects to the upcoming work screen for the current user.
     *
     * @api GET /learning/due/
     * @throws RouteNotFound
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
     * @param User                $student
     * @param Alaress_Date_Format $date
     * @throws InvalidColumnException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws UnexpectedValueException|Exception
     * @api   GET /learning/due/:studentId{/:date}
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
        $isStaff                 = $this->getUser()->getRole()->isStaff();

        foreach ($folders as $folder) {
            // Load teachers for this subject (if this subject has them)
            $teachers = [];
            if (count($folder->getCodes())) {
                // Actual teachers only
                $teacherCodes = $this->externalClass->getTeachers($folder->getCodeString());
                if (count($teacherCodes)) {
                    $pb       = $this->userMapper->getPredicateBuilder()
                                                 ->in('synergy_id', $teacherCodes)
                                                 ->equals('g.group_type', Role::STAFF);
                    $teachers = $this->userMapper->findByPredicates($pb);
                }
            }
            $perm = $this->access->getFolderAccess($this->getUser(), $folder->getId());

            // We need to fetch assessments, so we can show overall grades for each folder if:
            // * viewer is staff/instructor
            // * viewer is student/parent && 'show_overall_grade' is ON ... will be taken care of later
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

        if (count($displayGradesForFolders)) {
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
     * Returns a JSON payload containing an HTML fragment where the HTML fragment is a filtered list of assessments.
     *
     * @api /learning/due/{studentId:\d+}
     *
     * @param User $student
     * @throws Filter\BrokenFilterException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws DefinitionException
     * @throws Exception
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
        $filters = array_merge($filters, [
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
            'readableFolders' => array_unique($readableFolders),
            'isFiltered'      => (new Filter\RequestChecker())->isFilteredRequest($request),
        ];

        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 200,
            'content' => $this->buildUnthemedHtmlOutput('@learningUpcoming/upcomingInner.twig', $data)->getAsString(),
        ]));
    }

    /**
     * Returns an array that maps filter ids to slugs, so we can apply semantics to the IDs
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
     * @throws \Exception
     */
    public function getEvents(User $student): void
    {
        // Due work calendar could take a while
        $this->extendExecutionTime();

        $request             = $this->request->query->get('filters', []);
        $request             = $this->fillInMissingKeysAndValues($request, $this->getDefaultFiltersRequestForGetEvents($student));
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

    /**
     * @param array $array1
     * @param array $array2
     * @return array
     */
    private function fillInMissingKeysAndValues(array $array1, array $array2)
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value)) {
                if (!array_key_exists($key, $array1)) {
                    $array1[$key] = [];
                }

                foreach ($value as $keyInner => $valueInner) {
                    if (!array_key_exists($keyInner, $array1[$key])) {
                        $array1[$key][$keyInner] = $valueInner;
                    }
                }
            } else {
                if (!array_key_exists($key, $array1)) {
                    $array1[$key] = $value;
                }
            }
        }

        return $array1;
    }

    /**
     * The purpose of this function is to generate the same request that would have been generated automatically
     * had $this->request->query->get('filters', []) been empty in $this->getEvents().
     * If $this->request->query->get('filters', []) had been empty, the request would have been generated in Javascript
     * by inspecting the filter HTML form.
     *
     * Example return array:
     * [
     *     1 => [
     *         'project' => '0',
     *         'quiz'    => '0',
     *         'dueWork' => '0',
     *         'task'    => '0',
     *         'lti'     => '0',
     *     ],
     *     3 => [
     *         // FolderIds to which the user has access.
     *         50  => '0',
     *         56  => '0',
     *         69  => '0',
     *         72  => '0',
     *         74  => '0',
     *         78  => '0',
     *         80  => '0',
     *         85  => '0',
     *         102 => '0',
     *         189 => '0',
     *         190 => '0',
     *         191 => '0',
     *     ],
     *     10 => [
     *         'cap_upcoming' => '0',
     *         'cap_open'     => '0',
     *         'cap_overdue'  => '0',
     *         'cap_closed'   => '0',
     *     ],
     *     11 => [
     *         'unweighted' => '0',
     *         'weighted'   => '0',
     *     ],
     *     12 => [
     *         1      => '0',
     *         2      => '0',
     *         3      => '0',
     *         4      => '0',
     *         5      => '0',
     *         'none' => '0',
     *     ]
     * ];
     *
     * @param User $student
     * @return array
     * @see Javascript in library/Schoolbox/Filter/templates/panel.twig.
     */
    private function getDefaultFiltersRequestForGetEvents(User $student)
    {
        $folders = $this->folderMapper->findForUser(
            $student, !$student->getHideTimetable(), UserAccess::ACCESS_READ, false, true
        );

        $zeroByFolderId = [];
        foreach ($folders as $folder) {
            $zeroByFolderId[$folder->getId()] = '0';
        }

        $filterState = $this->filterFactoryFactory->buildCurrentState(
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
            false
        );

        $defaultRequest = [];
        foreach ($filterState as $item) {
            $filter = $item->getFilter();

            if ($filter->getId() === 3) {
                $optionIsEnabledByKey = $zeroByFolderId;
            } else {
                foreach ($filter->getPages() as $page) {
                    $optionIsEnabledByKey = [];
                    foreach ($page->getOptions() as $option) {
                        if ($option->getAvailable()) {
                            $optionIsEnabledByKey[$option->getKey()] = '0';
                        }
                    }
                }
            }

            $defaultRequest[$filter->getId()] = $optionIsEnabledByKey;
        }

        return $defaultRequest;
    }
}
