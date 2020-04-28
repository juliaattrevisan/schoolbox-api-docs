<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Twitter\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use RuntimeException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use UnexpectedValueException;

/**
 * Homepage component for Twitter feeds
 */
class Controller extends Component_Homepage_ControllerAbstract
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
