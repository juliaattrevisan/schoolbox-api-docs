<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Landing;

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
