<?php

/**
 * @author      James Leckie
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ImmersiveReader;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'immersiveReader';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        $isLoggedIn = new Rule\All(
            new Rule\IsConfigOn('office365_immersive_reader_name', $this->getConfig()),
            new Rule\IsLoggedIn()
        );

        $this->register('GET', '/getAuth', 'getTokenAndSubdomain', $isLoggedIn, null);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }
}
