<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;

/**
 * Dashboard calendar component.
 */
class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * @param String $templateHandle
     *
     * @return bool
     * @throws \RuntimeException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \JsonException
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@calendar/component/dashboard.twig',
            [
                'component' => $this,
                'user' => $this->getUser(),
            ],
            false
        );
    }
}
