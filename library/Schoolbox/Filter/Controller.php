<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Web\Controller\Base as BaseController;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Filter\Definition\IFilterDefinition;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Filter\Model as Filter;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\Filter\Option\Mapper as FilterOptionMapper;
use Schoolbox\Filter\Option\Defaults\Model as FilterDefaultOption;
use Schoolbox\Filter\Option\Defaults\Mapper as FilterDefaultMapper;
use Schoolbox\Role;
use Schoolbox\Filter\Panel as FilterPanel;

class Controller extends BaseController
{

    /**
     * Display a summary of filter configurations for Admins
     *
     * @api   GET /adminv2/filters
     * @level 17.0
     * @throws ServiceNotFound
     */
    public function getIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/admin.twig', [

        ]));
    }

    /**
     * Display a page where admins can alter default filter selections
     *
     * @api   GET /adminv2/filters/{$filter}/options
     * @level 17.0
     * @param Filter $filter
     * @throws ServiceNotFound
     */
    public function getAdminOptions(Filter $filter)
    {
        $definition = $this->getDefinition($filter);

        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/options.twig', [
            'filter'                 => $filter,
            'definition'             => $definition,
            'availableFilterOptions' => $definition->getFilterOptionsByKeyThenPage(),
        ]));
    }

    /**
     * Display a page where admins can alter default filter selections
     *
     * @api   GET /adminv2/filters/{$filter}/defaults
     * @level 17.0
     * @param Filter $filter
     * @throws ServiceNotFound
     */
    public function getAdminDefaults(Filter $filter)
    {
        $definition = $this->getDefinition($filter);

        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/defaults.twig', [
            'filter'                 => $filter,
            'definition'             => $definition,
            'pageRoleTypes'          => FilterOption::getPageRoleTypes(),
            'optionsPerPage'         => $definition->buildFilterDefaults(),
            'roleTypes'              => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
                Role::GUEST,
            ]
        ]));
    }





    /**
     * Saves which options should be available for each filter on each page
     *
     * @param Model $filter
     * @var string[][] $_POST['filter_option'] indexed by filterOption.key then page
     * @throws MapperException
     */
    public function postSaveOptions(Filter $filter)
    {
        /** @var string[][] $postFilterOptions */
        $postFilterOptions = $this->getRequest()->post->get('filter_option');
        $filterOptionMapper = FilterOptionMapper::getInstance();

        $definition = $this->getDefinition($filter);

        // Delete any existing options that belong to invalid pages or keys
        $optionsToDelete = $filterOptionMapper->createCollection();
        $existingOptions = $filterOptionMapper->findByColumn('filter_id', $filter->getId());

        foreach($existingOptions as $option){
            if(!in_array($option->getPage(), $definition->getPages(), true) || !in_array($option->getKey(), $definition->getKeys(), true)){
                $optionsToDelete->add($option);
            }
        }
        $filterOptionMapper->delete($optionsToDelete);


        // Save selections. We *do* save unchecked items. These are explicitly set to not-selected
        $optionsToSave   = $filterOptionMapper->createCollection();
        $existingOptionsByKeyThenPage = $existingOptions->groupBy('key');
        foreach($existingOptionsByKeyThenPage as $key => $pageFilterOptions){
            $pageFilterOptions->indexBy('page');
        }

        // Examine all possible options
        foreach($definition->getKeys() as $key){
            foreach($definition->getPages() as $page){

                // Does this option exist ?
                $filterOption = $existingOptionsByKeyThenPage[$key][$page];

                if(!$filterOption){
                    $filterOption = $filterOptionMapper->createModel();
                    $filterOption
                        ->setFilter($filter)
                        ->setPage($page)
                        ->setKey($key);
                }
                // Did user check this option?
                $filterOption->setSelectable(isset($postFilterOptions[$key][$page]));
                $optionsToSave->add($filterOption);
            }
        }
        $filterOptionMapper->save($optionsToSave);

        $this->getResponse()->content->set($this->buildJsonOutput(['status' => 'OK']));
    }


    /**
     * Saves which filter options should be selected by default per role and page
     *
     * @param Model $filter
     * @var string[][][] $_POST['filter_defaults'] indexed by option.page, option.key, the default.roleType
     * @throws MapperException
     */
    public function postSaveDefaults(Filter $filter)
    {
        /** @var string[][][] $postFilterDefaults */
        $postFilterDefaults = $this->getRequest()->post->get('filter_defaults');
        $filterOptionMapper = FilterOptionMapper::getInstance();
        $filterDefaultMapper = FilterDefaultMapper::getInstance();
        $definition = $this->getDefinition($filter);


        // Delete all role defaults for this filter
        $existingOptions = $filterOptionMapper->findByColumn('filter_id', $filter->getId())->indexBy('id');
        $filterDefaultMapper->deleteByColumn('filter_option_id', $existingOptions->keys());


        // Upgrade implicitly selectable options to explicitly selectable
        $optionsToSave = $filterOptionMapper->createCollection();
        $defaultsToSave = $filterDefaultMapper->createCollection();

        $optionsPerPageAndKey = $definition->getFilterOptionsByPageThenKey();
        foreach($postFilterDefaults as $page => $pageDefaults){
            foreach($pageDefaults as $key => $keyDefaults){
                /** @var FilterOption $option */
                $option = $optionsPerPageAndKey[$page][$key];
                // Does this option exist in DB yet?
                if(null === $option->getId()){
                    $optionsToSave->add($option);
                }

                foreach($keyDefaults as $roleType => $on){
                    // Sanitise role type
                    if($definition->isValidRoleType($roleType, $option)){
                        $default = $filterDefaultMapper->createModel();
                        $default
                            ->setFilterOption($option)
                            ->setRoleType($roleType)
                            ->setSelected(true);
                        $defaultsToSave->add($default);
                    }
                }
            }
        }

        $filterOptionMapper->save($optionsToSave);
        $filterDefaultMapper->save($defaultsToSave);

        $this->getResponse()->content->set($this->buildJsonOutput(['status' => 'OK']));
    }

    /**
     * Returns the filter definition, with its possible filterOptions all built
     *
     * @param Model $filter
     * @return IFilterDefinition
     */
    protected function getDefinition(Filter $filter)
    {
        $definitionClass = $filter->getClass();
        /** @var IFilterDefinition $definition */
        $definition = new $definitionClass($filter);
        $definition->buildFilterOptions();
        return $definition;
    }


}
