<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Twitter\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use RuntimeException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use UnexpectedValueException;

/**
 * Dashboard component for Twitter feeds
 */
class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function execute()
    {
    }


    /**
     * @param String $templateHandle
     * @return bool
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@twitter/component.twig'
        );
    }
}
