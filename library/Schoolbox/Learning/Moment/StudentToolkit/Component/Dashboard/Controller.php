<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\StudentToolkit\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Learning\Assessment\Redirect;

/**
 * Student Toolkit dashboard component
 */
class Controller extends Component_Dashboard_ControllerAbstract
{

    /**
     * @inheritDoc
     */
    public function execute(): bool
    {
        /* Only students have access to the Student Toolkit
         * All other roletypes have no utilisation of it
         */
        return $this->getUser()->getRole()->isStudent();
    }


    /**
     * @inheritDoc
     */
    public function renderTemplate($templateHandle): bool
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningMomentStudentToolkit/component.twig',
            [
                'user'      => $this->getUser(),
                'component' => $this,
                'returnUrl' => Redirect::encode('/search/user/' . $this->getUser()->getId()),
            ],
            false
        );
    }
}
