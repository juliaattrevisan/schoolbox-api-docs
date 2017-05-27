<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Web\Controller\Base as BaseController;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\GenericCollection;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Role;
use Schoolbox\Learning\Assessment\DataModel as Assessment;


class Controller extends BaseController
{

    /** @var Mapper $filterMapper */
    private $filterMapper;

    /** @var Option\Mapper $optionsMapper */
    private $optionsMapper;

    /** @var  Option\Defaults\Mapper $defaultsMapper */
    private $defaultsMapper;


    /**
     * @param Mapper          $filterMapper
     * @param Option\Mapper   $optionsMapper
     * @param Defaults\Mapper $defaultsMapper
     */
    public function __construct(
        Mapper $filterMapper,
        Option\Mapper $optionsMapper,
        Option\Defaults\Mapper $defaultsMapper
    ) {
        $this->filterMapper   = $filterMapper;
        $this->optionsMapper  = $optionsMapper;
        $this->defaultsMapper = $defaultsMapper;
    }


    /**
     * Display a summary of filter configurations for Admins
     *
     * @api   GET /adminv2/filters
     * @level 17.0
     * @throws ServiceNotFound
     */
    public function getIndex()
    {
        $filtersPerPage = $this->findVisibleFiltersPerPage();

        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/index.twig', [
            'filtersPerPage'           => $filtersPerPage,

            // Constants to enable presentation language delegation to TWIG
            'PAGE_PENDING_ASSESSMENTS' => Option\Model::PAGE_PENDING_ASSESSMENTS,
            'PAGE_GRADES'              => Option\Model::PAGE_GRADES,
            'PAGE_MARK_BOOK'           => Option\Model::PAGE_MARK_BOOK,
            'ENTITY_ASSESSMENTS'       => Assessment\Mapper::class,
        ]));
    }


    /**
     * Display a page where admins can alter default filter selections
     *
     * @api   GET /adminv2/filters/{pageKey}
     * @level 17.0
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws \ReflectionException If a definition class does not exist
     */
    public function getPageSettings(IFactoryFactory $factoryFactory, $page)
    {
        $pageFilters = $this->findVisibleFiltersPerPage()[$page];

        /** @var GenericCollection|IDefinition[] $definitions */
        $definitions = new GenericCollection(IDefinition::class);

        foreach($pageFilters as $name => $instance){
            $filterFactory      = $factoryFactory->buildFilterFactoryForFilter($instance->getClass());
            $definitions[$name] = $filterFactory->buildDefinition($instance);
        }


        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/settings.twig', [
            'pageKey'                  => $page,
            'definitions'              => $definitions,
            'roleTypes'                => Role::getRoleTypes(),

            // Constants to enable presentation language delegation to TWIG
            'PAGE_PENDING_ASSESSMENTS' => Option\Model::PAGE_PENDING_ASSESSMENTS,
            'PAGE_GRADES'              => Option\Model::PAGE_GRADES,
            'PAGE_MARK_BOOK'           => Option\Model::PAGE_MARK_BOOK,
            'ENTITY_ASSESSMENTS'       => Assessment\Mapper::class,
        ]));
    }


    /**
     * Load up all settings for this page, overlay new settings, and save
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string $page
     * @throws \InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws \ReflectionException If a definition class does not exist
     */
    public function postSavePageSettings(IFactoryFactory $factoryFactory, $page)
    {
        // Clean up any invalid roleType defaults
        $this->deleteDefaultsForInvalidRoleTypes();

        $optionsToSave  = $this->optionsMapper->createCollection();
        $defaultsToSave = $this->defaultsMapper->createCollection();

        $pageFilters = $this->findVisibleFiltersPerPage()[$page];


        // Load current system options and defaults, and overlay with posted settings
        /** @var string[][] $postOptions */
        $postOptions  = $this->getRequest()->post->get('filter_options');
        /** @var string[][][] $postDefaults */
        $postDefaults = $this->getRequest()->post->get('filter_defaults');
        foreach($pageFilters as $name => $instance){
            $filterFactory = $factoryFactory->buildFilterFactoryForFilter($instance->getClass());
            $definition    = $filterFactory->buildDefinition($instance);
            $options       = $definition->getOptionsWithDefaults($page);

            foreach($options as $option){
                $option->setAvailable(isset($postOptions[$instance->getId()][$option->getKey()]));

                // Must add() instead of offsetSet($id) as $id will be null if not yet in DB
                $optionsToSave->add($option);

                foreach($option->getDefaults() as $default){
                    $default->setSelected((bool)$postDefaults[$instance->getId()][$option->getKey()][$default->getRoleType()]);
                    $defaultsToSave->add($default);
                }
            }

            // Delete any invalid options. This can occur when:
            // * a filter is configured to no longer be available for a page, or
            // * a lookup list has an item removed
            $optionsBuilder = $this->optionsMapper->getPredicateBuilder();
            $keys = $options->getColumnValues('key');
            $optionsBuilder
                ->equals('filter_id', $instance->getId())
                ->equals('page', $page)
                ->notEquals('key', $options->getColumnValues('key'));
            $count  = $this->optionsMapper->deleteByPredicates($optionsBuilder);
            $todo = 1;
        }


        // Save new and updated options and defaults
        $this->optionsMapper->save($optionsToSave);
        $this->defaultsMapper->save($defaultsToSave);

        $this->response->content->set($this->buildJsonOutput(['status' => 200]));
    }


    /**
     * Hard-delete any roleType default selections if the roleType is no longer valid
     *
     * This would occur if a system roleType is removed.
     *
     * Returns the number of rows deleted
     *
     * @return bool
     */
    private function deleteDefaultsForInvalidRoleTypes()
    {
        $defaultsBuilder = $this->defaultsMapper->getPredicateBuilder();
        $defaultsBuilder->notEquals('role_type', Role::getRoleTypes());
        return $this->defaultsMapper->deleteByPredicates($defaultsBuilder);
    }


    /**
     * @return GenericCollection[]|Model[][] $visibleFilters Indexed by page, then filterDefinition.class
     */
    private function findVisibleFiltersPerPage()
    {
        /** @var GenericCollection|Model[] $visibleFilters */
        $visibleFilters = $this->filterMapper->findAll()->filter(function (Model $instance) {
            $definitionClass = $instance->getClass();
            return count($definitionClass::getPages()) > 0;
        });

        // Group filters by which pages they appear on
        $filtersPerPage = [];
        foreach($visibleFilters as $instance){
            $class = $instance->getClass();
            foreach($class::getPages() as $page){
                $filtersPerPage[$page][$class::getName()] = $instance;
            }
        }
        return $filtersPerPage;
    }
}
