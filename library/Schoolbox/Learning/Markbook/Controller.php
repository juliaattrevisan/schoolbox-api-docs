<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\TotalMarkAbstract;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Term;
use Alaress\Csv\Writer AS CsvWriter;
use DueWork_ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilter;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\User;

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var GradeGroup\Model
     */
    private $defaultGradeGroup;

    /**
     * @var CourseImpl\Mapper
     */
    private $courseImplMapper;

    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /**
     * @var DueWork_ErrorMapper
     */
    private $dueWorkErrorMapper;

    /**
     * @var Access
     */
    private $access;

    /** @var  AssessmentFilter\IdFinder */
    private $assessmentIdFinder;

    /** @var AssessmentFilter\FactoryFactory */
    private $filterFactoryFactory;

    /**
     * Constructor
     *
     * @param Error\Mapper                    $errorMapper
     * @param AssessmentMapperProxy           $assessmentMapperProxy
     * @param GradeGroup\Model                $defaultGradeGroup
     * @param CourseImpl\Mapper               $courseImplMapper
     * @param Term\Mapper                     $termMapper
     * @param DueWork_ErrorMapper             $dueWorkErrorMapper
     * @param Access                          $access
     * @param AssessmentFilter\IdFinder       $assessmentIdFinder
     * @param AssessmentFilter\FactoryFactory $filterFactoryFactory
     */
    public function __construct(
        Error\Mapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        GradeGroup\Model $defaultGradeGroup,
        CourseImpl\Mapper $courseImplMapper,
        Term\Mapper $termMapper,
        DueWork_ErrorMapper $dueWorkErrorMapper,
        Access $access,
        AssessmentFilter\IdFinder $assessmentIdFinder,
        AssessmentFilter\FactoryFactory $filterFactoryFactory
    ) {
        $this->errorMapper           = $errorMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->defaultGradeGroup     = $defaultGradeGroup;
        $this->courseImplMapper      = $courseImplMapper;
        $this->termMapper            = $termMapper;
        $this->dueWorkErrorMapper    = $dueWorkErrorMapper;
        $this->access                = $access;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterFactoryFactory = $filterFactoryFactory;
    }


    /**
     * Redirect to the markbook for the specified class
     *
     * @param int $folderId
     * @param User|null $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClass($folderId, User $viewee = null)
    {
        $user = $viewee ?: $this->getUser();
        $currentTerm = $this->termMapper->findForToday();
        $route = $this->getRouter()->generate('learningMarkbook.getMarkbook', ['userId' => $user->getId()]);
        $getArgs = "f={$folderId}&td={$currentTerm->getId()}&a=&p=&uis=&wt=&wk=";
        $this->getResponse()->redirect->to("{$route}?{$getArgs}");
    }

    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook?f=1,2&td=3
     * @level 165
     * @param null|User $viewee Whose markbook are we looking at?
     * @throws RouteNotFound
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException      If the specified filter definition class does not exist
     * @throws ServiceNotFound           If unable to locate HTML web Output
     * @throws \DomainException          If of the filters on this page does not have its slug set
     */
    public function getMarkbook(User $viewee = null)
    {
        // If viewee not specified, then display current user's markbook.
        if(null === $viewee){
            $viewee = $this->getUser();
        }
        $page      = FilterOption\Model::PAGE_MARK_BOOK;
        $route     = $this->getRouter()->generate('learningMarkbook.postMarkbook', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, $queryArgs);

        // Is a teacher looking at their own markbook, or admin looking at a teacher's markbook?
        $pageTitle = 'Markbook';
        if($viewee->getId() !== $this->getUser()->getId()){
            $pageTitle .= " for {$viewee->getFullName()}";
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learningMarkbook/markbookOuter.twig', [
                'viewee'      => $viewee,
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle($pageTitle)
        );
    }


    /**
     * @param User $viewee Whose markbook are we fetching?
     * @todo convert this to a GET on the same route name as getMarkbook, but distinguish as being AJAX
     */
    public function postMarkbook(User $viewee)
    {

        try {
            $postFilterSelections = $this->request->post['filter_selection'] ?: [];
            $filters              = $this->generateFilters($postFilterSelections);
            $assessmentIds        = $this->assessmentIdFinder->findIdsByFilters($filters);
            $markbook             = $this->buildMarkbook($assessmentIds);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@learningMarkbook/markbookInner.twig', [
                    'markbook'     => $markbook,
                    'writable'     => $this->determineWritableAssessments($markbook->getAssessments()),
                    'pendingLabel' => $this->getPendingLabel(),
                    'viewee'       => $viewee,
                    'queryArgs'    => $this->buildQueryArgs($filters),
                ])->getAsString(),
            ]));

        } catch (Filter\BrokenFilterException $e){
            $data = [
                'status'  => 500,
                'message' => "Sorry, the {$e->getFilterName()} filter appears to be broken. Please deselect all options and try again",
            ];
            if ($this->getConfigLoader()->get('debug_mode')) {
                $data = array_merge($data, [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'line'    => $e->getLine(),
                    'file'    => $e->getFile(),
                ]);
            }
            $this->getResponse()->content->set($this->buildJsonOutput($data));
        } catch (\Exception $e) {
            $this->returnJsonError($e);
            // TODO - can remove this block when we abandon PHP 5 support
        } catch (\Throwable $e) {
            $this->returnJsonError($e);
        }
    }

    private function generateFilters(array $filterSelections)
    {
        $page = FilterOption\Model::PAGE_MARK_BOOK;

        $keyConverters        = $this->filterFactoryFactory->buildKeyConverters($page);
        $selectionsPerFilter  = Filter\Selection\Mapper::getInstance()->updateSelections($keyConverters, $this->getUser(), $page, $filterSelections);
        $selectionTransformer = new AssessmentFilter\Transformer($keyConverters, $selectionsPerFilter);
        $filters              = $selectionTransformer->getFilters();

        // Explicitly inject filter to ensure we don't get assessments that live directly in UnitSpecs,
        // regardless of any filter selections send by user
        $filters[AssessmentFilter\NullableContext\UnitSpec\Definition::SLUG] = new Collection();

        return $filters;
    }


    /**
     * @param array $assessmentIds
     * @return MarkBook
     */
    private function buildMarkbook(array $assessmentIds)
    {
        // Fetch assessments & participants
        $assessments   = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments   = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());
        $markbook->getAssessments()->sortBy(['dueAt', 'sequence'], ['asc', 'asc']);
        return $markbook;
    }


    /**
     * @param GenericCollection|IAssessable[] $assessments
     * @return bool[]
     */
    private function determineWritableAssessments(GenericCollection $assessments)
    {
        /* Determine which assessments the user should be able to mark.
         *
         * Can only mark an assessment if:
         * * Assessment is/was not due in a term that is archived, and
         * * User has write+ perm to the page
         */
        /** @var bool[] $writableAssessments Indexed by assessment.id */
        $writable = [];

        $terms = $this->termMapper->findAll(['order' => ['start' => 'ASC']]);
        foreach($assessments as $assessment){
            $writable[$assessment->getId()] = $this->isAssessmentMarkable($this->getUser(), $assessment, $terms);
        }
        return $writable;
    }


    /**
     * @return null|string
     */
    private function getPendingLabel()
    {
        // What text should we display if student has submitted but not been marked?
        try {
            return $this->dueWorkErrorMapper->findDefaultForNewSubmissions()->getDisplayAs();
        } catch (Mapper\Exception $e) {
            // Let Twig figure it out
            return null;
        }
    }


    /**
     * @param null[]|Collection[] $filters
     * @return string
     */
    private function buildQueryArgs(array $filters)
    {
        // Convert filters to query args so export can retrieve this report
        // TODO Convert $filters from array to DTO and make this a method on that DTO. SA-3379  SA-3201
        $queryArgs = [];
        foreach ($filters as $slug => $filter) {
            $queryArg = "$slug=";
            if ($filter instanceof Collection) {
                $queryArg .= implode(',', $filter->keys());
            }
            $queryArgs[] = $queryArg;
        }
        return implode('&', $queryArgs);
    }


    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param \Throwable $e
     */
    protected function returnJsonError($e)
    {
        $data = [
            'status'  => 500,
            'message' => 'Sorry, an error occurred during your request and has been logged. Please try deselecting filter options',

        ];
        if ($this->getConfigLoader()->get('debug_mode')) {
            $data = array_merge($data, [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
        }
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

    /**
     * Can the specified user mark the specified assessment?
     *
     * Will be false if assessment's folder is archived, or if user lacks WRITE+ perm to folder
     *
     * @param User              $user
     * @param IAssessable       $assessment
     * @param GenericCollection|Term\Model[] $terms
     * @return bool
     */
    private function isAssessmentMarkable(User $user, IAssessable $assessment, GenericCollection $terms)
    {
        foreach($terms as $term){
            if($term->contains($assessment->getDueAt())){
                if($term->getArchived()){
                    return false;
                }
                // Rely on access cache to catch repeated calls to same folder.
                return $this->access->isInstructor($user, $assessment->getContext()->getFolder());
            }
        }
        // Logically should never get to here. It means assessment is due before first term in system starts.
        return false;
    }


    /**
     * Return the markbook in CSV format
     *
     * @api   GET /learning/markbook/:classId/term/:termCode/export
     * @param Folder\Model $class
     * @param Term\Model   $term
     * @throws ApplicationException\Exception
     * @throws \UnexpectedValueException
     */
    public function getMarkbookExport(User $viewee, AssessmentFilter\FactoryFactory $filterFactoryFactory)
    {
        // Replaces /submission/listSubmission/{termSlug}/{folderId}
        //       eg /submission/listSubmission/{termSlug}/{folderId}
        // Replaces /submission/viewSubmission/{dueWorkId}
        //       eg /submission/viewSubmission/56

        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->assessmentMapperProxy->findAllByFolderIdForReview($class->getId(), null, ['term' => $term]);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());

        /** @var IAssessable $assessment */

        $export = [];

        $data['Student Id']         = 'Class Average';
        $data['Student First Name'] = '';
        $data['Student Last Name']  = '';
        $data['Student Average']    = $markbook->getWeightedMean();

        // This is an ugly hack to get duplicate task names to appear as is the case in demo data
        // Just prepending spaces to the end of the assessment name

        $titlesFound = [];

        foreach ($markbook->getAssessmentsWithProjectContents() as $assessment) {

            $title = $assessment->getTitle() . "\r\n(" . $assessment->getTypeName() . ')';

            while (isset($titlesFound[$title])) {
                $title .= ' ';
            }

            $titlesFound[$title]            = true;
            $data[$title]                   = $markbook->getAssessmentStatistics()[$assessment->getId()]['mean'];
            if ($this->hasTotalMarkColumn($assessment)) {
                $data[$title . ' (Total)'] = '';
            }
            if ($this->hasNormalizedMarkColumn($assessment)) {
                $data[$title . ' (Normalized)'] = '';
            }
            if ($this->hasCommentColumn($assessment)) {
                $data[$title . ' (Comment)']    = '';
            }
        }

        $export[] = $data;

        foreach ($markbook->getStudents() as $student) {

            $data['Student Id']         = $student->getExternalId();
            $data['Student First Name'] = $student->getFirstname();
            $data['Student Last Name']  = $student->getLastname();
            $data['Student Average']    = $markbook->getStudentStatistics()[$student->getId()]['weightedMean'];

            $titlesFound = [];

            foreach ($markbook->getAssessmentsWithProjectContents() as $assessment) {

                $title = $assessment->getTitle() . "\r\n(" . $assessment->getTypeName() . ')';

                while (isset($titlesFound[$title])) {
                    $title .= ' ';
                }

                $titlesFound[$title] = true;

                if ($assessment->isParticipant($student)) {
                    $data[$title] = $this->retrieveTitleField($assessment, $student);
                    if ($this->hasTotalMarkColumn($assessment)) {
                        $data[$title . ' (Total)'] = $this->retrieveTotalMarkField($assessment, $student);
                    }
                    if ($this->hasNormalizedMarkColumn($assessment)) {
                        $data[$title . ' (Normalized)'] = $this->retrieveNormalizedMarkField($assessment, $student);
                    }
                    if ($this->hasCommentColumn($assessment)) {
                        $data[$title . ' (Comment)'] = $this->retrieveCommentField($assessment, $student);
                    }

                } else {
                    $data[$title] = '';
                    if( $this->hasTotalMarkColumn( $assessment)) {
                        $data[$title . ' (Total)'] = '';
                    }
                    if ($this->hasNormalizedMarkColumn( $assessment)) {
                        $data[$title . ' (Normalized)'] = '';
                        }if($this->hasCommentColumn($assessment)) {
                            $data[$title . ' (Comment)'] = '';

                    }
                }

            }
            $export[] = $data;
        }


        //TODO This could probably be rewritten with a resource stream output object and using fputcsv to wrap up this code
        $file   = tmpfile();
        $writer = new CsvWriter($file, $export);
        $writer->write();

        $filename = preg_replace('/[^\w-(),]/', '_', 'Markbook_' . $class->getFolder()->getNameWithCodes());

        header('Content-Disposition: attachment; filename="' . $filename . '.csv";');
        header('Content-Type: application/csv; charset=UTF-8');
        rewind($file);
        echo stream_get_contents($file);
        fclose($file);
        exit;
    }

    /* Helper methods for building the markbook export.
     * TODO: It's not ideal these methods are in the controller instead of in a renderer class or something
     */

    /**
     * Does this assessment have a total mark column?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function hasTotalMarkColumn(IAssessable $assessment)
    {
        return $assessment->getMarkScheme() instanceof TotalMarkAbstract;
    }

    /**
     * Does this assessment have a normalized mark column?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function hasNormalizedMarkColumn(IAssessable $assessment)
    {
        return !($assessment->getMarkScheme() instanceof None || $assessment->getMarkScheme() instanceof Completion);
    }

    /**
     * Does this assessment have a comment column?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function hasCommentColumn(IAssessable $assessment)
    {
        // Comment check is the same as normalized mark check
        return !($assessment->getMarkScheme() instanceof None || $assessment->getMarkScheme() instanceof Completion);
    }

    /**
     * Retrieves title field value for this assessment and student.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return null|string
     */
    protected function retrieveTitleField(IAssessable $assessment, User $student)
    {
        if ($this->hasTotalMarkColumn($assessment)
            && preg_match('/(\\d+)\\s*\\/\\s*(\\d+)/', $assessment->getHumanReadableMark($student, true), $markParts)
        ) {
            return $markParts[1];
        }

        return $assessment->getHumanReadableMark($student, true);
    }

    /**
     * Retrieves total mark field value for this assessment and student.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return null|string
     */
    protected function retrieveTotalMarkField(IAssessable $assessment, User $student)
    {
        if (preg_match('/(\\d+)\\s*\\/\\s*(\\d+)/', $assessment->getHumanReadableMark($student, true), $markParts)) {
            return $markParts[2];
        }
        return '';
    }

    /**
     * Retrieves normalized field value for this assessment and student.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return null|string
     */
    protected function retrieveNormalizedMarkField(IAssessable $assessment, User $student)
    {
        return $assessment->getNormalisedMark($student, true);
    }

    /**
     * Retrieves comment field value for this assessment and student.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return null|string
     */
    protected function retrieveCommentField(IAssessable $assessment, User $student)
    {
        $participant = $assessment->getParticipantIfExtant($student);
        if (!$participant) {
            return '';
        }
        $mostRecentFeedback = $participant->findMostRecentFeedback();
        return $mostRecentFeedback ? $mostRecentFeedback->getComment() : '';
    }

    /* Getters/Setters
     */

}
