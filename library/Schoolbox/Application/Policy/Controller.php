<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Policy;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Schoolbox policy page, can be accessed with and without a session
     * @api   GET /policy
     * @since 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getPolicy(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@policy/policy.twig', []));
    }
}
