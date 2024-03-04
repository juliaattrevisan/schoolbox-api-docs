<?php

namespace Schoolbox\Learning\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;

class CurrentLessonPlanController extends Component_Dashboard_ControllerAbstract
{

    /**
     * Output the data we collected in exec()
     *
     * @param  String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@paperlyDashboard/component.twig',
            []
        );
    }
}
