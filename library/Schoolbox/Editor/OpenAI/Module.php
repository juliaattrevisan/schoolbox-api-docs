<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Editor\OpenAI\Log\Mapper;
use Schoolbox\Role;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'openai';

    protected function registerRoutes(): void
    {
        $isStaff = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF),
        );

        $this->register('POST', '', 'postForward', $isStaff, null);
    }

    public function buildController(): Base
    {
        return new Controller($this->injector->get('httpClient'), $this->getMapperFactory()->get(Mapper::class));
    }
}
