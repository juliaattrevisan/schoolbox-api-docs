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
use Schoolbox\Admin\OpenAI\Authorizer\Rule\IsAIFeatureEnabled;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as DifferentiationAdminMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\DifferentiationProfile\FileParser\ParserFactory;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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

        $dpEnabled = new Rule\All(
            $isStaff,
            new Rule\IsConfigOn(ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED, $this->getConfig())
        );

        $hasDifferentiationProfileService = new Rule\All(
            $dpEnabled,
            new Rule\HasServicePermission(
                UserAccess::SERVICE_DIFFERENTIATION,
                UserAccess::ACCESS_WRITE
            ),
        );

        /* This /openai/ route is used by Little Robot Dude from the ckeditor
         * directly, to produce its output
         */
        $this->register('POST', '', 'postAiAssistant', new Rule\All(
            $isStaff,
            new IsAIFeatureEnabled(ConfigMapper::NAME_AI_TEXT_CHAT, $this->getConfig()),
        ));

        /* Lesson Plan field generators
         */
        $this->register('POST', '/lesson-plan', 'postLessonPlan', new Rule\All(
            $isStaff,
            new IsAIFeatureEnabled(ConfigMapper::NAME_AI_LESSONPLAN, $this->getConfig()),
        ));

        /* Image generation: for OpenAI only
         */
        $this->register('POST', '/image', 'postImage', new Rule\All(
            $isStaff,
            new IsAIFeatureEnabled(ConfigMapper::NAME_AI_IMAGE_GENERATION, $this->getConfig()),
        ));

        /* Differentiation Profile generation
         */
        $this->register('POST', '/differentiation-profile', 'postDifferentiation', new Rule\All(
            $hasDifferentiationProfileService,
            new IsAIFeatureEnabled(ConfigMapper::NAME_AI_DIFFERENTIATION, $this->getConfig()),
        ));
        /* Learning Activity Customisation generation
         */
        $this->register('POST', '/learning-activity-customisation', 'postLearningActivityCustomisation', new Rule\All(
            $dpEnabled,
            new IsAIFeatureEnabled(ConfigMapper::NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION, $this->getConfig()),
        ));
    }


    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Curriculum\Node\Mapper::class),
            $this->getMapperFactory()->get(PastoralMapper::class),
            $this->injector->get('filesystem.storageProxy'),
            new ParserFactory(),
            $this->injector->get('ai.clientFactory'),
            new \Schoolbox\Presidio\ClientFactory($this->injector->get('config'), $this->injector->get('httpClient'))->build(),
            $this->getMapperFactory()->get(DifferentiationProfileMapper::class),
            $this->getMapperFactory()->get(DifferentiationAdminMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->injector->get('ai.response.cache'),
        );
    }
}
