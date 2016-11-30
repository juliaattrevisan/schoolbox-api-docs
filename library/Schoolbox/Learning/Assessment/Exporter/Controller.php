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
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for assessment export actions.
 */
class Controller extends BaseController
{
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
     * @param AssessmentDataSource           $assessmentDataSource
     * @param CourseImplementationDataSource $courseImplementationDataSource
     * @param UnitImplementationDataSource   $unitImplementationDataSource
     */
    public function __construct(
        AssessmentDataSource $assessmentDataSource,
        CourseImplementationDataSource $courseImplementationDataSource,
        UnitImplementationDataSource $unitImplementationDataSource
    ) {
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
            'assessment'    => $assessment,
            'returnUrl'     => $this->getRequest()->query->get(Redirect::RETURN_URL, ''),
            'returnUrlName' => Redirect::RETURN_URL,
        ];

        // Data required:
        // implementations, and their permissions
        // units which use this specification
        $context = $assessment->getContext();
        if ($context instanceof UnitSpecification) {
            $unitImplementations = $this->unitImplementationDataSource->findAllByUnitSpecificationId(
                $context->getId()
            );

            // Load assessments
            foreach ($unitImplementations as $unitImplementation) {
                $unitImplementation->setAssessables(
                    $this->assessmentDataSource->findAllByUnitImplementationId($unitImplementation->getId())
                );

                // Is this assessable within the ID or not?
            }
        }

        $template = "@learningAssessmentExporter/export.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    public function postExport(IAssessable $assessment)
    {
        // do stuff
    }
}
