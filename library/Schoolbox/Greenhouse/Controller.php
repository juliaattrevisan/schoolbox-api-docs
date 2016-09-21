<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;

class Controller extends Base
{

    /**
     * Load $templateName
     *
     * @param $templateName
     */
    public function getGreenhouse($templateName)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@greenhouse/'. $templateName . '.twig', []));
    }
}
