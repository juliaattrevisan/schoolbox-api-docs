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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Role;


class Controller extends BaseController
{

    /**
     * Display a summary of filter configurations for Admins
     *
     * @api   GET /adminv2/filters
     * @level 17.0
     * @param GenericCollection|Model[] $filters
     * @throws ServiceNotFound
     */
    public function getIndex(GenericCollection $filters)
    {

        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/index.twig', [
            'filters' => $filters,
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
    public function getAvailableOptions(IFilterDefinition $definition)
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
    public function getDefaultsSelections(IFilterDefinition $definition)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput($definition->getDefaultsTemplate(), [
            'filter'                 => $definition->getFilter(),
            'definition'             => $definition,
            'optionsPerPage'         => $definition->getAvailableOptions(),
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
     * @param Option\Mapper $optionMapper
     * @var string[][]          $_POST['filter_option'] indexed by filterOption.key then page
     * @throws MapperException
     */
    public function postSaveOptions(IFilterDefinition $definition, Option\Mapper $optionMapper)
    {
        /** @var string[][] $postFilterOptions */
        $postFilterOptions = $this->getRequest()->post->get('filter_option');


        // Delete any existing options that belong to invalid pages or abstract keys
        $optionsToDelete = $optionMapper->createCollection();
        $existingOptions = $optionMapper->findByColumn('filter_id', $definition->getFilter()->getId());

        foreach($existingOptions as $option){
            if(!in_array($option->getPage(), $definition->getPages(), true) || !in_array($option->getKey(), $definition->getAbstractKeys(), true)){
                $optionsToDelete->add($option);
            }
        }
        $optionMapper->delete($optionsToDelete);


        // Save selections. We *do* save unchecked items. These are explicitly set to not-selected
        $optionsToSave   = $optionMapper->createCollection();
        $existingOptionsByKeyThenPage = $existingOptions->groupBy('key');
        foreach($existingOptionsByKeyThenPage as $key => $pageFilterOptions){
            $pageFilterOptions->indexBy('page');
        }

        // Examine all possible options
        foreach($definition->getAbstractKeys() as $key){
            foreach($definition->getPages() as $page){

                // Does this option exist ?
                $filterOption = $existingOptionsByKeyThenPage[$key][$page];

                if(!$filterOption){
                    $filterOption = $optionMapper->createModel();
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
        $optionMapper->save($optionsToSave);

        $this->getResponse()->content->set($this->buildJsonOutput(['status' => 'OK']));
    }


    /**
     * Saves which filter options should be selected by default per role and page
     *
     * @param IFilterDefinition $definition
     * @param Option\Mapper $optionMapper
     * @param Defaults\Mapper $defaultMapper
     * @var string[][][]        $_POST['filter_defaults'] indexed by option.page, option.key, default.roleType
     * @throws MapperException
     */
    public function postSaveDefaults(IFilterDefinition $definition, Option\Mapper $optionMapper, Defaults\Mapper $defaultMapper)
    {
        /** @var string[][][] $postFilterDefaults */
        $postFilterDefaults = $this->getRequest()->post->get('filter_defaults');

        // Upgrade implicitly selectable options to explicitly selectable
        $optionsToSave = $optionMapper->createCollection();
        $defaultsToSave = $defaultMapper->createCollection();

        $optionsPerPageAndKey = $definition->getAvailableOptions();

        foreach($optionsPerPageAndKey as $page => $pageOptions) {
            /** @var Option\Model[] $pageOptions */
            foreach ($pageOptions as $key => $options) {
                $optionsToSave->add($options);
                foreach ($options->getDefaults() as $roleType => $default) {
                    $defaultsToSave->add($default);
                    // Was this default selected?
                    $default->setSelected(isset($postFilterDefaults[$page][$key][$roleType]));
                }
            }
        }

        $optionMapper->save($optionsToSave);
        $defaultMapper->save($defaultsToSave);

        $this->getResponse()->content->set($this->buildJsonOutput(['status' => 'OK']));
    }
}
