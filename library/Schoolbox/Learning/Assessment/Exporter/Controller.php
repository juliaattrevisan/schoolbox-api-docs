<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for assessment export actions.
 */
class Controller extends BaseController
{
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
//        $data = $this->getAssessmentAsTwigArray($assessment->getContext(), $assessment);

//        $data['returnUrlName'] = Redirect::RETURN_URL;
//        $data['returnUrl'] = $this->getRequest()->query->get(Redirect::RETURN_URL);

        $data     = ['assessment' => $assessment,];
        $template = "@learningAssessmentExporter/export.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    public function postExport(IAssessable $assessment)
    {
        // do stuff
    }
}
