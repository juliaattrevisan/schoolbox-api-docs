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

        /* This /openai/ route is used by Little Robot Dude from the ckeditor
         * directly, to produce its output
         */
        $this->register('POST', '', 'postAiAssistant', $isStaff);

        /* Lesson Plan field generators
         */
        $this->register('POST', '/lesson-plan', 'postLessonPlan', $isStaff);

        /* Image generation
         */
        $this->register('POST', '/image', 'postImage', $isStaff);
    }

    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('httpClient'),
            $this->getMapperFactory()->get(\Schoolbox\Error\Mapper::class),
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Curriculum\Node\Mapper::class),
        );
    }
}
