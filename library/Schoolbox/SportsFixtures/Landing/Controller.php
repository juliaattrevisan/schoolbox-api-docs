<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Landing;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{
    /**
     * Admin landing page
     */
    public function getLanding()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixtures/landing.twig', []));
    }
}
