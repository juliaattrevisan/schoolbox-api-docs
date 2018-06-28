<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Twig_Error_Loader;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'rubric';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $permission  = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );
        $filePreload = function ($params) {
            return [
                'file' => (new CollectorFactory())->build([$params['storageTuple']])->getFirst(),
            ];
        };

        // Download/upload rubric actions
        $this->register('POST', '', 'create', $permission);
        $this->register('POST', '/csv', 'download', $permission);
        $this->register('POST', '/import/{storageTuple}', 'import', $permission, $filePreload);

        // Test actions
        // TODO consider removing
        $this->register('GET', '/test', 'testGeneric', $permission);
        $this->register('GET', '/test/empty', 'testEmpty', $permission);
        $this->register('GET', '/test/box/{boxId}', 'testBox', $permission);
        $this->register('GET', '/test/csv/{boxId}', 'testCSV', $permission);
        $this->register('GET', '/test/folder/{folderId}', 'testFolder', $permission);

        // Criteria form actions
        $criteriaPermission = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            'folderId',
            Access::getInstance()
        );
        $criteriaPreload = function ($params) {
            return [
                'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
            ];
        };
        $this->register('GET', '/criteria/{folderId}', 'getCriteriaForm', $criteriaPermission, $criteriaPreload);
        $this->register('POST', '/criteria/{folderId}', 'postCriteriaForm', $criteriaPermission, $criteriaPreload);
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
            $injector->lazyNew(Controller::class)
        );

        // dependencies
        $injector->setters[Controller::class]['setFactory'] = $injector->lazyNew(Factory::class);
        $injector->setters[Controller::class]['setMapper']  = $this->getMapperFactory()->getLazy(RubricMapper::class);

    }
}
