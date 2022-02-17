<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\Access;
use InvalidArgumentException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Filter;

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
            new Rule\IsConfigOn(ConfigMapper::NAME_RUBRIC_MARKBOOK_ENABLED, $this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(Access::getInstance())
            )
        );

        $this->register('GET', '/{folderId:\d+}', 'getMarkbook', $canViewPage, $preloadFolder);
        $this->register('POST', '/{folderId:\d+}', 'postMarkbook', $canViewPage, $preloadFolder);
        $this->register('GET', '/export/{folderId:\d+}', 'getMarkbookExport', $canViewPage, $preloadFolder);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('databaseInternal'),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(Filter\Selection\Mapper::class),
            $this->injector->get('filters.assessmentIdFinder'),
            $this->getMapperFactory()->get(Filter\Mapper::class),
            $this->injector->get('l10n'),
        );
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
