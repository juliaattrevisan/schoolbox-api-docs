<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

use Alaress\Mail\AbstractMailer;
use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use function func_get_args;
use External_ClassInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Statistics;
use Schoolbox\Learning\Classes\ClassCode;
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

    private $rosettaStone;

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
     * @param External_ClassInterface   $externalClass
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
     * @level 165
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
     * @level 165
     * @param User                             $viewee The user whose pending assessments we are viewing.
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @var mixed[][] $_GET. Comma-separated keys, indexed by filter.id. Eg '1=&2=weighted&3=1,2,3' (1 => [], 2 => [weighted], 3 => [1,2,3])
     * @throws RouteNotFound
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws Mapper\Exception
     * @throws \ReflectionException If unable to find the class for a filter registered on this page
     * @throws \DomainException     If filter factoryFactory has not defined the entity it is filtering
     * @throws NotFound             If $viewee is not staff
     */
    public function getPendingAssessmentsForUser(User $viewee, AssessmentFilters\FactoryFactory $filterFactoryFactory): void
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
     * @level 165
     * @param User                             $viewee The user whose pending assessments we are viewing.
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @var  string[][]                        $_POST  ['filter_selection'] Indexed by filter.id, then concrete key
     * @throws \LogicException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws ServiceNotFound
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function postPendingAssessmentsForUser(User $viewee, AssessmentFilters\FactoryFactory $filterFactoryFactory): void
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
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @param User                             $viewee
     * @param string[][]                       $request - Indexed by filter.id, then concrete key
     * @return string
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \ReflectionException If the specified class does not exist
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    protected function generatePendingAssessmentReport(AssessmentFilters\FactoryFactory $filterFactoryFactory, User $viewee, array $request)
    {
        $page                = Filter\Page\Model::PAGE_PENDING_ASSESSMENTS;

        // Convert Request to Selection models
        $codec               = $filterFactoryFactory->buildCodec();
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
     * @level 165
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
     * @param string $classCode
     *
     * @throws Mapper\Exception
     */
    public function getStudentListByClassCode(string $classCode): void
    {
        $students = (new StudentLists($this->externalClass, $this->userMapper, $this->folderMapper, $this->logMapper))
            ->getStudentLists((new ClassCode($classCode))->proper());

        $this->getResponse()->status->set(200);
        $this->getResponse()->content->set($this->buildJsonOutput($students));
    }

    /**
     * @param string $classCode
     *
     * @throws Mapper\Exception
     * @throws ServiceNotFound
     */
    public function getClassListPage(string $classCode): void
    {
        $classCode = new ClassCode($classCode);

        $folder = $this->getFolderByCode($classCode->proper());

        $subjectName = $folder->getName();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learning/classLists.twig', [
                'homepageId'        => $folder->getId(),
                'subjectName'       => $subjectName,
                'classCode'         => $classCode->getClassCode(),
                'specialNeeds'      => $this->rosettaStone->translate('specialNeeds',true, true),
                'replyTo'           => $this->getUser()->getEmail(),
                'mailEnabled'       => $this->getConfigLoader()->get('mail_enable'),
                'pastoralEnabled'   => $this->getConfigLoader()->get('pastoral_enabled'),
                'today'             => Alaress_Date_Format::now()->getURLDate(),
                'mailHandle'        => $this->getSession()->get('mailhandle'),
                'emailGroups'       => $this->getClassListsEmailGroups(),
            ])->setTab('classes')->setTitle($subjectName)
        );
    }

    /**
     * @return false|string
     */
    private function getClassListsEmailGroups(): string
    {
        $result[] = [
            'label' => 'students',
            'isChecked' => true,
        ];

        if ($this->getConfigLoader()->get('email_all_parents')) {
            $result[] = [
                'label' => 'parents',
                'isChecked' => false,
            ];
        }

        if ($this->getConfigLoader()->get('email_housemasters')) {
            $result[] = [
                'label' => 'housemasters',
                'isChecked' => false,
            ];
        }

        return json_encode($result);
    }


    /**
     * @param string $classCode
     *
     * @return Folder\Model
     * @throws Mapper\Exception
     */
    private function getFolderByCode(string $classCode): Folder\Model
    {
        try {
            return $this->folderMapper->findByCode($classCode);
        } catch (Exception $e) {
            // Handle the existence of a campus code prefix.
            // E.g: Class code "EN344" belongs to the Senior campus (code: "S"), and could also be passed in as "S/EN344"
            // to disambiguate it from a "EN344" which may exist in another campus.
            try {
                $codeParts = explode('/', $classCode, 2);
                if ($codeParts) {
                    return $this->folderMapper->findByCode($codeParts[1]);
                }
                else {
                    throw new NotFound();
                }
            }
            catch (Exception $e) {
                throw new NotFound();
            }
        }
    }

    /* Getters/Setters
     */
}
