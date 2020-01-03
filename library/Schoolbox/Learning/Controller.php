<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning;

use Alaress\Mail\AbstractMailer;
use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Filter\Codec;
use function func_get_args;
use External_ClassInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Statistics;
use Schoolbox\Learning\Classes\ClassList;
use Schoolbox\Learning\Students\StudentLists;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;
use \Alaress_Date_Format;
use Schoolbox\Filter;
use Schoolbox\Mapper;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;
use Schoolbox\L10n\RosettaStone;

/**
 * Class Controller
 *
 * @package Schoolbox\Learning
 */
class Controller extends Base
{
    /**
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;
    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /** @var Timetable_ColourMapper */
    private $timetableColourMapper;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;


    /** @var AssessmentFilters\IdFinder */
    private $assessmentIdFinder;

    /** @var Folder\ClassListFactory */
    private $classListFactory;

    /** @var UserMapper */
    private $userMapper;

    /** @var Folder\Log\Mapper */
    private $logMapper;

    /** @var External_ClassInterface */
    private $externalClass;

    /**
     * For translating Terminology
     *
     * @var RosettaStone
     */
    private $rosettaStone;

    private const LABEL_KEY = 'label';

    private const ISCHECKED_KEY = 'isChecked';

    /**
     * Controller constructor.
     *
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param FolderMapper               $folderMapper
     * @param TermMapper                 $termMapper
     * @param Timetable_ColourMapper     $timetableColourMapper
     * @param Filter\Selection\Mapper    $filterSelectionMapper
     * @param AssessmentFilters\IdFinder $assessmentIdFinder
     * @param Folder\ClassListFactory    $classListFactory
     * @param UserMapper                 $userMapper
     * @param Folder\Log\Mapper          $logMapper
     * @param External_ClassInterface    $externalClass
     * @param RosettaStone               $rosettaStone
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        FolderMapper $folderMapper,
        TermMapper $termMapper,
        Timetable_ColourMapper $timetableColourMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        Folder\ClassListFactory $classListFactory,
        UserMapper $userMapper,
        Folder\Log\Mapper $logMapper,
        External_ClassInterface $externalClass,
        RosettaStone $rosettaStone
    ) {
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
        $this->termMapper                 = $termMapper;
        $this->folderMapper               = $folderMapper;
        $this->timetableColourMapper      = $timetableColourMapper;
        $this->filterSelectionMapper      = $filterSelectionMapper;
        $this->assessmentIdFinder         = $assessmentIdFinder;
        $this->classListFactory           = $classListFactory;
        $this->userMapper                 = $userMapper;
        $this->logMapper                  = $logMapper;
        $this->externalClass              = $externalClass;
        $this->rosettaStone               = $rosettaStone;
    }


    /**
     * Redirects to current user
     *
     * @api   GET /learning/pending
     * @since 165
     * @throws RouteNotFound
     */
    public function getPendingAssessments(): void
    {
        // Is current user not SuperUser or Staff ?
        if (!($this->getUser()->isSuperuser() || $this->getUser()->getRole()->isStaff())) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningUpcoming.getUpcomingForStudent', [
                'studentId' => $this->getUser()->getId(),
            ]));
        }

        // If no staff explicitly requested, redirect to current user
        $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getPendingAssessmentsForUser', [
            'userId' => $this->getUser()->getId(),
            'viewee' => $this->getUser(),
        ]));
    }


    /**
     * Displays a blank page with collapsed filter panel
     *
     * @api   GET /learning/pending/:userId
     * @since 165
     * @param User                   $viewee The user whose pending assessments we are viewing.
     * @param Filter\IFactoryFactory $filterFactoryFactory
     * @var mixed[][] $_GET. Comma-separated keys, indexed by filter.id. Eg '1=&2=weighted&3=1,2,3' (1 => [], 2 => [weighted], 3 => [1,2,3])
     * @throws RouteNotFound
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws Mapper\Exception
     * @throws \ReflectionException If unable to find the class for a filter registered on this page
     * @throws \DomainException     If filter factoryFactory has not defined the entity it is filtering
     * @throws NotFound             If $viewee is not staff
     */
    public function getPendingAssessmentsForUser(User $viewee, Filter\IFactoryFactory $filterFactoryFactory): void
    {
        if(!$viewee->getRole()->isStaff()){
            throw new NotFound('Only staff have pending assessments. Found: ' . $viewee->getRole()->getType());
        }

        $page      = Filter\Page\Model::PAGE_PENDING_ASSESSMENTS;
        $route     = $this->getRouter()->generate('learning.postPendingAssessmentsForUser', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, func_get_args(), $queryArgs);

        $title = 'Pending Assessments';
        if($viewee->getId() !== $this->getUser()->getId()){
            $title .= " for {$viewee->getFullName()}";
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learning/pendingAssessmentsOuter.twig', [
                'viewee'      => $viewee,
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle($title)
        );
    }


    /**
     * Displays a list of Assessments that require marking, and Assessments currently unsubmitted
     *
     * @api   GET /learning/pending/:userId
     * @since 165
     * @param User                   $viewee The user whose pending assessments we are viewing.
     * @param Filter\IFactoryFactory $filterFactoryFactory
     * @var  string[][]              $_POST  ['filter_selection'] Indexed by filter.id, then concrete key
     * @throws \LogicException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws ServiceNotFound
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function postPendingAssessmentsForUser(User $viewee, Filter\IFactoryFactory $filterFactoryFactory): void
    {
        try {
            $postFilterSelections = $this->request->post['filter_selection'] ?: [];

            $content = $this->generatePendingAssessmentReport($filterFactoryFactory, $viewee, $postFilterSelections);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $content,
            ]));

        } catch (\Exception $e) {
            // TODO - can remove this block when we abandon PHP 5 support
            $this->returnJsonError($e);
        } catch (\Throwable $e) {
            $this->returnJsonError($e);
        }
    }


    /**
     * Generate a pending assessment report as HTML string
     *
     * @param Filter\IFactoryFactory $filterFactoryFactory
     * @param User                   $viewee
     * @param string[][]             $request - Indexed by filter.id, then concrete key
     * @return string
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \ReflectionException If the specified class does not exist
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws UnexpectedValueException
     */
    protected function generatePendingAssessmentReport(Filter\IFactoryFactory $filterFactoryFactory, User $viewee, array $request)
    {
        $page                = Filter\Page\Model::PAGE_PENDING_ASSESSMENTS;

        // Convert Request to Selection models
        $codec               = new Codec(Filter\Mapper::getInstance(), $this->filterSelectionMapper);
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);

        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        $filters = $filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        $filters = $filterFactoryFactory->checkTeacherFolders($filters, $viewee);

        // Ensure that assessments are not Assessment Specs
        $filters = array_merge($filters, [
            AssessmentFilters\NullableContext\UnitSpec\Definition::class   => [],
        ]);

        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);

        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        $toChaseLimit = \Alaress_Date_Format::now()->addDays(1);

        /**
         * Indexed by assessment.id
         *
         * @var Statistics[]|GenericCollection $assessmentStatistics
         */
        $assessmentStatistics = new GenericCollection(Statistics::class);

        /** @var Collection|IAssessable[] $assessmentsToMark */
        $assessmentsToMark = $this->assessmentMapperProxy->createCollection();
        foreach ($assessments as $assessment) {
            $assessmentStatistics[$assessment->getId()] = new Statistics($assessment);
            if ($assessmentStatistics[$assessment->getId()]->getCountFeedbackRequired()) {
                $assessmentsToMark[$assessment->getId()] = $assessment;
            }
        }
        $assessmentsToMark->sortBy('dueAt', 'asc', null, true);

        /** @var IAssessable[] $assessmentsToMark */
        $assessmentsToChase = $this->assessmentMapperProxy->createCollection();

        $assessmentStudentsToEmail = [];

        foreach ($assessments as $assessment) {
            if ($assessment->getDueAt()->lt($toChaseLimit) && $assessmentStatistics[$assessment->getId()]->getCountResponseRequired()) {
                $assessmentsToChase[$assessment->getId()] = $assessment;
                $assessmentStudentsToEmail[$assessment->getId()] = [];
                foreach($assessment->getAllParticipants() as $participant){
                    if($participant->isResponseRequired()){
                        $student = $participant->getUser();
                        $assessmentStudentsToEmail[$assessment->getId()][$student->getId()] = $student->getFormattedEmailDisplay();
                    }
                }
            }
        }
        $assessmentsToChase->sortBy('dueAt', 'asc', null, true);

        return $this->buildUnthemedHtmlOutput('@learning/pendingAssessmentsContent.twig', [
            'statistics'                => $assessmentStatistics,
            'assessmentsToMark'         => $assessmentsToMark,
            'assessmentsToChase'        => $assessmentsToChase,
            'assessmentStudentsToEmail' => $assessmentStudentsToEmail,
            'emailSeparator'            => AbstractMailer::implodeGlue(),
        ])->getAsString();
    }


    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param \Throwable $e
     */
    protected function returnJsonError($e)
    {
        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 500,
            'code'    => $e->getCode(),
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ]));
    }


    /**
     * Displays a list of classes for the current logged in user
     *
     * If the term includes now (ie is current) then we use current enrollment.
     * Else term is historical, so we display classes where a student has submitted responses or received feedback
     *
     * @api   GET /learning/classes{/term}
     * @since 165
     *
     * @param Term $term
     * @throws ServiceNotFound If unable to find HTML builder
     * @throws DefinitionException
     * @throws UnexpectedValueException
     */
    public function getClasses(Term $term)
    {
        $now           = Alaress_Date_Format::now();
        $isTermCurrent = $term->contains($now);
        $hideTimetable = $this->getUser()->getHideTimetable();
        $hideClasses   = $isTermCurrent && $hideTimetable;

        // Are we not showing classes ?
        if ($hideClasses) {
            $gridData = [
                'term'       => $term,
                'classList'  => $this->folderMapper->createCollection(),
                'classTypes' => [],
                'courses'    => [],
            ];
        } else {
            $classList = new ClassList(
                $this->getUser(),
                $this->folderMapper,
                $this->timetableColourMapper,
                $this->classListFactory
            );
            $gridData  = $classList->getClassList($term, $now);
        }


        $gridData = array_merge($gridData, [
            'now'           => $now,
            'terms'         => $this->termMapper->findAll()->sortBy('start', 'desc'),
            'hideTimetable' => $hideTimetable,
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/classes.twig', $gridData)->setTab('classes'));
    }


    /**
     * @param Folder\Model $folder
     *
     * @throws Mapper\Exception
     * @throws RouteNotFound
     */
    public function getStudentListByClassId(Folder\Model $folder): void
    {
        $students = (new StudentLists(
            $this->externalClass,
            $this->userMapper,
            $folder,
            $this->logMapper,
            $this->getRouter(),
            $this->getConfigLoader()->get('pastoral_enabled'),
            $this->getConfigLoader()->get('mail_enable'),
            $this->getSession()->get('mailhandle'),
            $this->rosettaStone
        ))->getStudentLists();

        $this->getResponse()->content->set($this->buildJsonOutput($students));
    }


    /**
     * @param Folder\Model $folder
     *
     * @throws ServiceNotFound
     */
    public function getClassListPage(Folder\Model $folder): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learning/classLists.twig', [
                'specialNeeds'      => $this->rosettaStone->translate('specialNeeds',true, true),
                'replyTo'           => $this->getUser()->getEmail(),
                'mailEnabled'       => $this->getConfigLoader()->get('mail_enable'),
                'emailGroups'       => $this->getClassListsEmailGroups(),
                'folder'            => $folder,
            ])->setTab('classes')->setTitle($folder->getName())
        );
    }


    /**
     * @return string
     */
    private function getClassListsEmailGroups(): string
    {
        $availableEmailGroups = ['parents' => 'email_all_parents', 'housemasters' => 'email_housemasters'];

        $result[] = [
            self::LABEL_KEY => 'students',
            self::ISCHECKED_KEY => true,
        ];

        foreach ($availableEmailGroups as $key => $configName) {
            if ($this->getConfigLoader()->get($configName)) {
                $result[] = [
                    self::LABEL_KEY => $key,
                    self::ISCHECKED_KEY => false,
                ];
            }
        }

        return json_encode($result);
    }
}
