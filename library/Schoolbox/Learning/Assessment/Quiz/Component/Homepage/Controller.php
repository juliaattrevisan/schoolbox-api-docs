<?php
/**
 * Quiz homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $componentRenderer = new \Schoolbox\Learning\Assessment\Component\Homepage\Controller($this);
        $result = $componentRenderer->render();

        return $this->renderTwigTemplate(
            $templateHandle,
            $result['template'],
            array_merge(['user' => $this->getUser()], $result['data']),
            true
        );
    }
}
