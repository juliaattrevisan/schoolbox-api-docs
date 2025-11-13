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
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;
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

        $hasDifferentiationProfileService = new Rule\All(
            $isStaff,
            new Rule\IsConfigOn(ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED, $this->getConfig()),
            new Rule\HasServicePermission(
                UserAccess::SERVICE_DIFFERENTIATION_PROFILE,
                UserAccess::ACCESS_WRITE
            ),
        );

        /* This /openai/ route is used by Little Robot Dude from the ckeditor
         * directly, to produce its output
         */
        $this->register('POST', '', 'postAiAssistant', $isStaff);

        /* Lesson Plan field generators
         */
        $this->register('POST', '/lesson-plan', 'postLessonPlan', $isStaff);

        /* Image generation: for OpenAI only
         */
        $this->register('POST', '/image', 'postImage', $isStaff);

        /* Differentiation Profile generation
         */
        $this->register('POST', '/differentiation-profile', 'postDifferentiation', $hasDifferentiationProfileService);
    }


    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Curriculum\Node\Mapper::class),
            $this->injector->get('ai.clientFactory'),
        );
    }
}
