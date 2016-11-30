<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\ImplementationMapperProxy as CourseImplementationDataSource;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplementationDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

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
     * @var CourseImplementationDataSource
     */
    protected $courseImplementationDataSource;

    /**
     * @var UnitImplementationDataSource
     */
    protected $unitImplementationDataSource;

    /**
     * Constructor
     *
     * @param Access                         $access
     * @param AssessmentDataSource           $assessmentDataSource
     * @param CourseImplementationDataSource $courseImplementationDataSource
     * @param UnitImplementationDataSource   $unitImplementationDataSource
     */
    public function __construct(
        Access $access,
        AssessmentDataSource $assessmentDataSource,
        CourseImplementationDataSource $courseImplementationDataSource,
        UnitImplementationDataSource $unitImplementationDataSource
    ) {
        $this->access                         = $access;
        $this->assessmentDataSource           = $assessmentDataSource;
        $this->courseImplementationDataSource = $courseImplementationDataSource;
        $this->unitImplementationDataSource   = $unitImplementationDataSource;
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
     * @api /learning/assessments/:assessmentId/export
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
     * @param IAssessable $assessment
     */
    public function postExport(IAssessable $assessment)
    {
        // do stuff
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
        $unitImplementations = $this->unitImplementationDataSource->findAllByUnitSpecificationId(
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
