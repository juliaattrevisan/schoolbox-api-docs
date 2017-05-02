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
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\AssessmentType;
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
use Schoolbox\Filter\Selection;
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


    /**
     * Constructor
     *
     * @param Error\Mapper          $errorMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param GradeGroup\Model      $defaultGradeGroup
     * @param CourseImpl\Mapper     $courseImplMapper
     * @param Term\Mapper           $termMapper
     * @param DueWork_ErrorMapper   $dueWorkErrorMapper
     * @param Access                $access
     */
    public function __construct(
        Error\Mapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        GradeGroup\Model $defaultGradeGroup,
        CourseImpl\Mapper $courseImplMapper,
        Term\Mapper $termMapper,
        DueWork_ErrorMapper $dueWorkErrorMapper,
        Access $access
    ) {
        $this->errorMapper           = $errorMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->defaultGradeGroup     = $defaultGradeGroup;
        $this->courseImplMapper      = $courseImplMapper;
        $this->termMapper            = $termMapper;
        $this->dueWorkErrorMapper    = $dueWorkErrorMapper;
        $this->access                = $access;
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
     * @param \Schoolbox\Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory
     * @param null|User                                           $viewee                Whose markbook are we looking at?
     * @throws RouteNotFound
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException      If the specified filter definition class does not exist
     * @throws ServiceNotFound           If unable to locate HTML web Output
     * @throws \DomainException          If of the filters on this page does not have its slug set
     */
    public function getMarkbook(AssessmentFilter\FactoryFactory $filterFactoryFactory, User $viewee = null)
    {
        // If viewee not specified, then display current user's markbook.
        if(null === $viewee){
            $viewee = $this->getUser();
        }
        $page      = FilterOption\Model::PAGE_MARK_BOOK;
        $route     = $this->getRouter()->generate('learningMarkbook.postMarkbook', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, $queryArgs);

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
     * @param AssessmentFilter\IdFinder       $assessmentIdFinder
     * @param AssessmentFilter\FactoryFactory $filterFactoryFactory
     * @todo convert this to a GET on the same route name as getMarkbook, but distinguish as being AJAX
     */
    public function postMarkbook(AssessmentFilter\IdFinder $assessmentIdFinder, AssessmentFilter\FactoryFactory $filterFactoryFactory)
    {

        try {
            $postFilterSelections = $this->request->post['filter_selection'] ?: [];

            $user = $this->getUser();
            $page = FilterOption\Model::PAGE_MARK_BOOK;

            $keyConverters        = $filterFactoryFactory->buildKeyConverters($page);
            $selectionsPerFilter  = Filter\Selection\Mapper::getInstance()->updateSelections($keyConverters, $user, $page, $postFilterSelections);
            $selectionTransformer = new AssessmentFilter\Transformer($keyConverters, $selectionsPerFilter);
            $filters              = $selectionTransformer->getFilters();

            // Explicitly inject filter to ensure we don't get assessments that live directly in UnitSpecs,
            // regardless of any filter selections send by user
            $filters[AssessmentFilter\NullableContext\UnitSpec\Definition::SLUG] = new Collection();

            $assessmentIds = $assessmentIdFinder->findIdsByFilters($filters);
            $content       = $this->generateMarkbookReport($assessmentIds);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $content,
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
     * @param int[] $assessmentIds
     * @throws ServiceNotFound If unable to find the HTML Output rendering service
     * @return string
     */
    private function generateMarkbookReport(array $assessmentIds)
    {
        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());
        $markbook->getAssessments()->sortBy(['dueAt', 'sequence'], ['asc', 'asc']);

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

        // What text should we display if student has submitted but not been marked?
        try {
            $pendingLabel = $this->dueWorkErrorMapper->findDefaultForNewSubmissions()->getDisplayAs();
        } catch (Mapper\Exception $e) {
            // Let Twig figure it out
            $pendingLabel = null;
        }

        // And output
        return $this->buildUnthemedHtmlOutput('@learningMarkbook/markbookInner.twig', [
            'markbook'     => $markbook,
            'writable'     => $writable,
            'contextTypes' => AssessmentContextType::getInstance(),
            'pendingLabel' => $pendingLabel,
        ])->getAsString();
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
    public function getMarkbookExport(Folder\Model $class, Term\Model $term)
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

                    $participant        = $assessment->getParticipant($student);
                    $mostRecentFeedback = $participant->findMostRecentFeedback();

                    $comment = $mostRecentFeedback ? $mostRecentFeedback->getComment() : '';

                    if( $assessment->getMarkScheme() instanceof TotalMarkAbstract) {
                        preg_match('/(\d+) \/ (\d+)/',$assessment->getHumanReadableMark($student, true), $markParts);
                        $data[$title] = $markParts[1];
                        $data[$title. ' (Total)'] = $markParts[2];
                    } else {$data[$title] = $assessment->getHumanReadableMark($student, true);
                    }
                    if( !( $assessment->getMarkScheme() instanceof None || $assessment->getMarkScheme() instanceof Completion)) {
                        $data[$title . ' (Normalized)'] = $assessment->getNormalisedMark($student, true);
                        if(!$assessment->getMarkScheme() instanceof Completion) {
                            $data[$title . ' (Comment)'] = $comment;

                    }
                }
               } else
                {
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

    /* Getters/Setters
     */

}
