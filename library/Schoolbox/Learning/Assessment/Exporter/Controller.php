<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;

/**
 * Controller for assessment export actions.
 */
class Controller extends BaseController
{
    /**
     * @var Access
     */
    protected $access;

    /**
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;

    /**
     * @var QuizMapper
     */
    private $quizMapper;

    /**
     * Constructor
     *
     * @param Access                   $access
     * @param AssessmentDataSource     $assessmentDataSource
     * @param UnitImplementationMapper $unitImplementationMapper
     * @param QuizMapper               $quizMapper
     */
    public function __construct(
        Access $access,
        AssessmentDataSource $assessmentDataSource,
        UnitImplementationMapper $unitImplementationMapper,
        QuizMapper $quizMapper
    ) {
        $this->access                   = $access;
        $this->assessmentDataSource     = $assessmentDataSource;
        $this->unitImplementationMapper = $unitImplementationMapper;
        $this->quizMapper                   = $quizMapper;
    }

    /**
     * Get the form for exporting a single assessment.
     *
     * * Only assessments within a unit specification context may be exported
     * * Assessments may only be exported to unit implementations which are:
     * ** based on the same specification
     * ** not closed yet
     * ** not already containing a copy of this assessment
     *
     * GET vars:
     * * returnUrl: a URL to return to once done
     *
     * @api GET /learning/export/assessment/:assessmentId
     * @level 165
     * @param IAssessable $assessment
     */
    public function getExportForm(IAssessable $assessment)
    {
        $data = [
            'assessment'      => $assessment,
            'returnUrl'       => $this->getRequest()->query->get(Redirect::RETURN_URL, ''),
            'returnUrlName'   => Redirect::RETURN_URL,
            'implementations' => $this->retrieveUnitImplementationsForSpecification($assessment),
        ];

        $template = "@learningAssessmentExporter/export.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    /**
     * Processes the export assessment request.
     *
     * @api POST /learning/export/assessment/:assessmentId
     * @level 165
     * @param IAssessable $assessment
     * @var string $_GET['returnUrl']            a URL to return to once done
     * @var int[] $_POST['unitImplementation']   unit implementations to push this into.
     *                                           key:   unit implementation id
     *                                           value: true/false
     */
    public function postExport(IAssessable $assessment)
    {
        $importedImplementations = $this->getRequest()->post->get('unitImplementation', []);
        $newImplementations      = $this->assessmentDataSource->createCollection();

        foreach ($this->retrieveUnitImplementationsForSpecification($assessment) as $unitImplData) {
            /** @var UnitImplementation $unitImpl */
            $unitImpl = $unitImplData['unit'];

            if (!empty($importedImplementations[$unitImpl->getId()])) {

                if ($unitImplData['imported']) {
                    // This has already been exported between now and when the request was made.
                    // That is probably fine to ignore
                    continue;
                }

                if (!$unitImplData['allowed']) {
                    // banhammer
                    $this->sendError('You do not have permission to write to the selected class', [], 403);
                    return;
                }

                // clone $assessment for this unitImplData
                $newImplementation = $assessment->cloneForCopy();
                $newImplementation
                    ->setContext($unitImpl)
                    ->setSpecificationAssessment($assessment)
                    // Set sensible defaults for dates
                    ->setPublishAt($unitImpl->getOpenDate())
                    ->setOpenAt($unitImpl->getOpenDate())
                    ->setDueAt($unitImpl->getDueDate())
                    ->setCloseAt($unitImpl->getDueDate())
                ;
                // TODO - test if project, and if so need to set dates on its Assessables too
                $newImplementations->add($newImplementation);
            }
        }

        // Save the newly created implementations (at the end of the unit)
        foreach ($newImplementations as $newImplementation) {
            $this->persistAssessmentImplementation($newImplementation);
        }

        // Decode the return URL, if it was set
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL, '');
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            $returnUrl = $this->getRouter()->generate(
                'course.modifyCourse',
                ['id' => $assessment->getContext()->getId(),]
            );
        }

        // Redir back to the return URL (if set) or the course page if not
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([ Redirect::RETURN_URL => $returnUrl ]));
        } else {
            $this->getResponse()->redirect->afterPost($returnUrl);
        }
    }

    /**
     * @param IAssessable $assessmentImpl
     * @return bool
     */
    protected function persistAssessmentImplementation(IAssessable $assessmentImpl)
    {
        // Is this an UPDATE rather than INSERT?
        $exists = $assessmentImpl->getId();

        $this->assessmentDataSource->save($assessmentImpl);

        if($exists) {
            return true;
        }

        // If Assessable was a Quiz, we have one Quiz.
        // If Assessable was a Project, we may have many Quizzes
        /** @var IAssessable[] $quizImpls */
        $quizImpls = new GenericCollection(QuizAssessable::class);

        if($assessmentImpl instanceof QuizAssessable){
            $quizImpls->add($assessmentImpl);
        } elseif($assessmentImpl instanceof Project){
            /** @var Project $assessmentImpl */
            foreach($assessmentImpl->getAssessables() as $assessable){
                if($assessable instanceof QuizAssessable){
                    $quizImpls->add($assessable);
                }
            }
        }

        // Copy Quiz Sections/Questions/Solutions
        foreach($quizImpls as $quizImpl){
            $specId = $quizImpl->getSpecificationAssessment()->getId();
            $implId = $quizImpl->getId();
            $this->quizMapper->copyQuizConstituents($specId, $implId);
        }
        return true;
    }

    /**
     * Helper method: retrieves unit implementations for a spec assessment
     *
     * @param IAssessable $assessment
     * @return array[] unit:     The unit implementation that this assessment's spec uses
     *                 allowed:  Is the current user allowed to import this?
     *                 imported: Is this assessment already imported?
     */
    protected function retrieveUnitImplementationsForSpecification(IAssessable $assessment)
    {
        $result              = [];
        $unitImplementations = $this->unitImplementationMapper->findAllByUnitSpecificationId(
            $assessment->getContext()->getId()
        );

        foreach ($unitImplementations as $unitImplementation) {

            // Closed already? don't allow export
            if ($unitImplementation->getDueDate()->isPastDate()) {
                continue;
            }

            // Load assessments into unit implementation
            $unitImplementation->setAssessables(
                $this->assessmentDataSource->findAllByUnitImplementationId($unitImplementation->getId())
            );

            // Can I even import into $unitImplementation's folder?
            $accessLevel = $this->access->getFolderAccess(
                $this->getUser(),
                [$unitImplementation->getCourse()->getFolder()->getId()]
            );
            $isAllowed = $accessLevel >= UserAccess::ACCESS_WRITE;

            // Is $assessable imported into this unit yet?
            $isAlreadyImported = false;
            foreach ($unitImplementation->getAssessables() as $implementationAssessment) {
                // It's not guaranteed that an assessment in a implementation will have a spec.
                // An assessment added to an implementation post-spec won't have one.
                $spec = $implementationAssessment->getSpecificationAssessment();
                if ($spec instanceof IAssessable && $spec->getId() == $assessment->getId()) {
                    $isAlreadyImported = true;
                    break;
                }
            }

            $result[] = [
                'unit'     => $unitImplementation,
                'allowed'  => $isAllowed && !$isAlreadyImported,
                'imported' => $isAlreadyImported,
            ];
        }

        return $result;
    }
}
