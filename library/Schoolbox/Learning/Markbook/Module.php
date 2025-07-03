<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;

class Module extends BaseModule
{
    protected $basename = 'learningMarkbook';

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws NotFound If a class was specified but it does not exist
     */
    public function registerRoutes()
    {
        $preload = function ($params) {
            $args = [];

            // Must preload folder
            try {
                $args['folder'] = $this->getFolderMapper()->findById($params['folderId']);
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['folderId']}");
            }

            // Preload term if it's there
            if (isset($params['termCode'])) {
                try {
                    $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                } catch (Mapper\Exception $e) {
                    throw new NotFound("No term found for code: {$params['termCode']}");
                }
            }
            return $args;
        };

        $permissions = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn(ConfigMapper::NAME_CLASS_MARKBOOK_ENABLED, $this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(Access::getInstance())
            )
        );

        // TODO - add 'getMarkbookForUnitImportSpec' to be used in 'outline/unit.twig' to jump to specific unit import
        $this->register('GET', '/class/{folderId:\d+}', 'getMarkbookForClass', $permissions, $preload);
        $this->register('POST', '/class/{folderId:\d+}', 'postMarkbookForClass', $permissions, $preload);
        $this->register('GET', '/class/{folderId:\d+}/term/{termCode}', 'getMarkbookForClassAndTerm', $permissions, $preload);
        $this->register('GET', '/class/{folderId:\d+}/export', 'getMarkbookExportForClass', $permissions, $preload);
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(Term\Mapper::class),
            $this->getMapperFactory()->get(Access::class),
            $this->injector->get('filters.assessmentIdFinder'),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(StateMapper::class),
            new ScrubAdapter(),
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|Folder\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Mapper|Term\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }
}
