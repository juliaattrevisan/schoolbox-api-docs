<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access;

class Module extends BaseModule
{
    protected $basename = 'rubricMarkbook';

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws NotFound If a class was specified but it does not exist
     */
    public function registerRoutes()
    {
        $preloadFolder = function ($params) {
            if(null !== $params['folderId']){
                try{
                    return ['folder' => $this->getFolderMapper()->findById($params['folderId'])];
                } catch (Mapper\Exception $e){
                    throw new NotFound("No folder found for id: {$params['folderId']}");
                }
            }
            return [];
        };

        // Page should be visible to:
        // - Superusers
        // - Staff with read+ perms
        // - Instructors of the class
        $canViewPage = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                // Allow staff with read+ access to the folder
                new Rule\All(
                    new Rule\HasRole(Role::STAFF),
                    new Rule\HasHomepageAccess(UserAccess::ACCESS_READ, function ($params) {
                        return $params['folderId'];
                    }, Access::getInstance())
                ),
                // Allow instructors (anyone with write+ access to folder)
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, function ($params) {
                    return $params['folderId'];
                }, Access::getInstance())
            )
        );

        $this->register('GET',  '{/folderId:\d+}', 'getMarkbook', $canViewPage, $preloadFolder);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return Mapper|Folder\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }
}
