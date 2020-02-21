<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\L10n\RosettaStone;
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
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

class Module extends BaseModule
{

    protected $basename = 'rubricMarkbook';


    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function registerRoutes(): void
    {
        $preloadFolder = function (array $params) {
            try {
                if (($params['folder'] ?? null) instanceof Folder\Model) {
                    $folder = $params['folder'];
                } else {
                    $folder = $this->getMapperFactory()->get(Folder\Mapper::class)->findById($params['folderId']);
                }
                return ['folder' => $folder];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['folderId']}", 0, $e);
            }
        };

        // Page should be visible to:
        // - Superusers
        // - Staff
        // - Instructors of the class
        $canViewPage = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn('rubric_markbook_enable', $this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                // Allow staff regardless of access level to the folder
                new Rule\HasRole(Role::STAFF),
                // Allow instructors (anyone with write+ access to folder)
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, static function (array $params) {
                    return $params['folderId'];
                }, Access::getInstance())
            )
        );

        $this->register('GET', '/{folderId:\d+}', 'getMarkbook', $canViewPage, $preloadFolder);
        $this->register('POST', '/{folderId:\d+}', 'postMarkbook', $canViewPage, $preloadFolder);
        $this->register('GET',  '/export/{folderId:\d+}', 'getMarkbookExport', $canViewPage, $preloadFolder);
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
            'internalDb'            => $injector->lazyGet('databaseInternal'),
            'gradeGroupMapper'      => $this->getMapperFactory()->getLazy(GradeGroup\Mapper::class),
            'userAccessMapper'      => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
            'filterFactoryFactory'  => $injector->lazyGet('filters.factoryFactory'),
            'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
            'assessmentIdFinder'    => $injector->lazyGet('filters.assessmentIdFinder'),
            'filterMapper'          => $this->getMapperFactory()->getLazy(Filter\Mapper::class),
            'translator'            => $injector->lazyGet('l10n'),
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
