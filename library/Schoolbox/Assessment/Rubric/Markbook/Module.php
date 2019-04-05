<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
declare(strict_types=1);

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
use Aura\Di\Container;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Permission\Access\UserAccessMapper;

class Module extends BaseModule
{

    protected $basename = 'rubricMarkbook';


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    public function registerRoutes(): void
    {
        $preloadFolder = function (array $params) {
            try {
                return ['folder' => $this->getMapperFactory()->get(Folder\Mapper::class)->findById($params['folderId'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['folderId']}");
            }
        };

        // Page should be visible to:
        // - Superusers
        // - Staff
        // - Instructors of the class
        $canViewPage = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                // Allow staff regardless of access level to the folder
                new Rule\HasRole(Role::STAFF),
                // Allow instructors (anyone with write+ access to folder)
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, function (array $params): int {
                    return (int)$params['folderId'];
                }, Access::getInstance())
            )
        );

        $this->register('GET', '/{folderId:\d+}', 'getMarkbook', $canViewPage, $preloadFolder);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        $injector->params[Controller::class] = [
            'internalDb'       => $injector->lazyGet('databaseInternal'),
            'gradeGroupMapper' => $this->getMapperFactory()->getLazy(GradeGroup\Mapper::class),
            'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
        ];
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
