<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Criteria;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Twig_Error_Loader;

/**
 * Module for the rubric criteria management form.
 */
class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'rubricCriteria';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // Criteria form actions
        $permission = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            function (array $params = []) {
                return $params['folderId'];
            },
            Access::getInstance()
        );
        $preload    = function ($params) {
            return [
                'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
            ];
        };
        $this->register('GET', '/{folderId}', 'getForm', $permission, $preload);
        $this->register('POST', '/{folderId}', 'postForm', $permission, $preload);
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'nodeMapper'   => $this->getMapperFactory()->getLazy(NodeMapper::class),
                    'rubricMapper' => $this->getMapperFactory()->getLazy(RubricMapper::class),
                    'rosettaStone' => $injector->lazyGet('l10n'),
                ]
            )
        );
    }
}
