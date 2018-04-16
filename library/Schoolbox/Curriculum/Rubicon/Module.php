<?php
/**
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Rubicon;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Rubicon Atlas controller.
 *
 * So far, refers only to a test page.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'curriculumRubicon';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $permission = new Rule\IsLoggedIn();
        $this->register('GET', '/test', 'getTestPage', $permission, null);
        $this->register('GET', '/proxy/{endpoint}', 'getProxy', $permission, null);
        $this->register('GET', '/curriculum/{curriculumId}', 'getCurriculum', $permission, null);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }
}
