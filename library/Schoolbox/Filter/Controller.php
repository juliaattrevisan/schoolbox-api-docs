<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Web\Controller\Base as BaseController;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Filter\Model as Filter;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\Filter\Option\Mapper as FilterOptionMapper;
use Schoolbox\Filter\Option\Defaults\Mapper as FilterDefaultMapper;
use Schoolbox\Role;


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
     * @param IFilterDefinition $definition
     * @throws ServiceNotFound
     */
    public function getAdminOptions(IFilterDefinition $definition)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput($definition->getOptionsTemplate(), [
            'filter'           => $definition->getFilter(),
            'definition'       => $definition,
            'options'          => $definition->getOptions(),
        ]));
    }

    /**
     * Display a page where admins can alter default filter selections
     *
     * @api   GET /adminv2/filters/{$filter}/defaults
     * @level 17.0
     * @param IFilterDefinition $definition
     * @throws ServiceNotFound
     */
    public function getAdminDefaults(IFilterDefinition $definition)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput($definition->getDefaultsTemplate(), [
            'filter'                 => $definition->getFilter(),
            'definition'             => $definition,
            'optionsPerPage'         => $definition->getOptionDefaults(),
            'roleTypes'              => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
                Role::GUEST,
            ],
        ]));
    }


    /**
     * Saves which options should be available for each filter on each page
     *
     * @param IFilterDefinition $definition
     * @var string[][]          $_POST['filter_option'] indexed by filterOption.key then page
     * @throws MapperException
     */
    public function postSaveOptions(IFilterDefinition $definition)
    {
        /** @var string[][] $postFilterOptions */
        $postFilterOptions = $this->getRequest()->post->get('filter_option');
        $filterOptionMapper = FilterOptionMapper::getInstance();


        // Delete any existing options that belong to invalid pages or keys
        $optionsToDelete = $filterOptionMapper->createCollection();
        $existingOptions = $filterOptionMapper->findByColumn('filter_id', $definition->getFilter()->getId());

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
                        ->setFilter($definition->getFilter())
                        ->setPage($page)
                        ->setKey($key);
                }
                // Did user check this option?
                $filterOption->setAvailable(isset($postFilterOptions[$key][$page]));
                $optionsToSave->add($filterOption);
            }
        }
        $filterOptionMapper->save($optionsToSave);

        $this->getResponse()->content->set($this->buildJsonOutput(['status' => 'OK']));
    }


    /**
     * Saves which filter options should be selected by default per role and page
     *
     * @param IFilterDefinition $definition
     * @var string[][][]        $_POST['filter_defaults'] indexed by option.page, option.key, the default.roleType
     * @throws MapperException
     */
    public function postSaveDefaults(IFilterDefinition $definition)
    {
        /** @var string[][][] $postFilterDefaults */
        $postFilterDefaults = $this->getRequest()->post->get('filter_defaults');
        $filterOptionMapper = FilterOptionMapper::getInstance();
        $filterDefaultMapper = FilterDefaultMapper::getInstance();



        // Get all existing defaults
//        $existingOptions = $filterOptionMapper->findByColumn('filter_id', $definition->getFilter()->getId());
//        $existingDefaults = $filterDefaultMapper->findByColumn('filter_option_id', $existingOptions->getColumnValues('id'))->groupBy('page');
//        foreach($existingDefaults as $page => $existingPageDefaults){
//            $existingPageDefaults->groupBy('key');
//        }


        // Upgrade implicitly selectable options to explicitly selectable
        $optionsToSave = $filterOptionMapper->createCollection();
        $defaultsToSave = $filterDefaultMapper->createCollection();

        $optionsPerPageAndKey = $definition->getOptionDefaults();

        foreach($optionsPerPageAndKey as $page => $pageOptions) {
            /** @var FilterOption[] $pageOptions */
            foreach ($pageOptions as $key => $options) {
                $optionsToSave->add($options);
                foreach ($options->getDefaults() as $roleType => $default) {
                    // Was this default selected?
                    $defaultsToSave->add($default);
                    $default->setSelected(isset($postFilterDefaults[$page][$key][$roleType]));
                }
            }
        }
//
//            foreach($pageDefaults as $key => $keyDefaults){
//                /** @var FilterOption $option */
//                $option = $optionsPerPageAndKey[$page][$key];
//                // Does this option exist in DB yet?
//                if(null === $option->getId()){
//                    $optionsToSave->add($option);
//                }
//
//                foreach($keyDefaults as $roleType => $on){
//                    // Sanitise role type
//                    if($definition->isValidRoleType($roleType, $option)){
//                        $default = $filterDefaultMapper->createModel();
//                        $default
//                            ->setFilterOption($option)
//                            ->setRoleType($roleType)
//                            ->setSelected(true);
//                        $defaultsToSave->add($default);
//                    }
//                }
//            }
//        }

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
//        $definition->buildFilterOptions();
        return $definition;
    }


}
