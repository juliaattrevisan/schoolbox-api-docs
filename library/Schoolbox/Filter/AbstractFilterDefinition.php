<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;

/**
 * Provides common filter definition behaviour
 */
abstract class AbstractFilterDefinition implements IFilterDefinition
{

    /** @var Filter\Model */
    private $filter;

    /** @var Option\Mapper */
    private $filterOptionMapper;

    /** @var Defaults\Mapper */
    private $filterDefaultMapper;


    /**
     * @param Model           $filter
     * @param Option\Mapper   $filterOptionMapper
     * @param Defaults\Mapper $filterDefaultMapper
     */
    public function __construct(Filter\Model $filter, Option\Mapper $filterOptionMapper, Defaults\Mapper $filterDefaultMapper)
    {
        $this->filter              = $filter;
        $this->filterOptionMapper  = $filterOptionMapper;
        $this->filterDefaultMapper = $filterDefaultMapper;
    }


    /**
     * Grouped by option.key (abstract key), then indexed by option.page
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|Option\Model[][]
     */
    public function getOptions($pageFilter = null)
    {
        $validPages = static::getPages();

        // Did we specify a page, and the page is not in the valid pages?
        if ($pageFilter && !in_array($pageFilter, $validPages, true)) {
            // Bug out
            return new Collection();
        }

        // Get existing DB values, discarding any that belong to invalid pages
        $columnList = [
            'filter_id' => $this->getFilter()->getId(),
            'page'      => $pageFilter ?: $validPages,
        ];

        /** @var Collection|GenericCollection[]|Option\Model[][] $optionsPerPage */
        $optionsPerPage = $this->getFilterOptionMapper()->findByColumnList($columnList)->groupBy('page');


        // Ensure we have a set of options per valid page (ie, they may not yet exist in DB)
        foreach ($validPages as $page) {

            // Are we filtering by page?
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }

            if (!$optionsPerPage->offsetExists($page)) {
                $optionsPerPage->setOffset($page, $this->getFilterOptionMapper()->createCollection());
            }
        }

        // Ensure all possible options are present in graph
        $customKeys = $this->getCustomKeys();
        /** @var GenericCollection|Option\Model[] $pageOptions */
        foreach ($optionsPerPage as $page => $pageOptions) {
            $pageOptions->indexBy('key');

            // Ensure it contains 'all', 'current', etc
            foreach ($customKeys as $customAbstractKey) {
                if (!$pageOptions->offsetExists($customAbstractKey)) {
                    $option = $this->getFilterOptionMapper()->createModel();

                    // If not yet configured by admin, should this option be available?
                    $isAvailable = $this->getInitialAvailability($customAbstractKey, $page);

                    $option
                        ->setFilter($this->getFilter())
                        ->setKey($customAbstractKey)
                        ->setPage($page)
                        ->setAvailable($isAvailable);
                    $pageOptions->setOffset($option->getKey(), $option);
                }
            }

            $pageOptions = $this->insertDynamicOptions($pageOptions, $page);
            $pageOptions = $this->unsetInvalidOptions($pageOptions);

            // Order the options. 'All', then 'Current', then 'Previous', then from most recent term back
            $this->sortFilterOptions($pageOptions)->indexBy('key');
        }

        // Convert to ordered by abstract key
        $optionsPerKey = new Collection();

        // Ensure page order is same as getPages()
        foreach ($validPages as $page) {
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }

            $pageOptions = $optionsPerPage[$page];
            foreach ($pageOptions as $abstractKey => $filterOption) {
                if (!$optionsPerKey->offsetExists($abstractKey)) {
                    $optionsPerKey->setOffset($abstractKey, $this->getFilterOptionMapper()->createCollection());
                }
                $optionsPerKey[$abstractKey][$page] = $filterOption;
            }
        }
        return $optionsPerKey;
    }


    /**
     * Insert dynamic options into list. Eg each WorkType or Term that exists in the system right now.
     *
     * @param GenericCollection|Option\Model[] $pageOptions
     * @param string                           $page
     * @return GenericCollection|Option\Model[]
     */
    abstract protected function insertDynamicOptions(GenericCollection $pageOptions, $page);


    /**
     * There may be existing availability settings saved to the DB that refer
     * to (most likely dynamic) items that no longer exist.
     *
     * Eg the admin may have configured that the Term 2017 should be available, but
     * it has subsequently been deleted from the system.
     *
     * Concrete classes can unset their relevant options here for similar reasons.
     *
     * @param GenericCollection|Option\Model[] $pageOptions
     * @return GenericCollection|Option\Model[]
     */
    abstract protected function unsetInvalidOptions(GenericCollection $pageOptions);


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function getAvailableOptions($pageFilter = null, $roleTypeFilter = null)
    {
        // Get all possible options, filtering by page.
        $optionsPerKey = $this->getOptions($pageFilter);

        // Get the options which are available (regardless of whether they exist yet in DB)
        $availableOptions = $this->getFilterOptionMapper()->createCollection();
        foreach ($optionsPerKey as $key => $keyOptions) {
            /** @var Option\Model[] $keyOptions */
            foreach ($keyOptions as $page => $option) {
                if ($option->getAvailable()) {
                    $availableOptions->add($option);
                }
            }
        }

        // Find any options that already exist in the DB
        /** @var Option\Model[]|GenericCollection $existingOptions */
        $existingOptions = $availableOptions->filter(function (Option\Model $option) {
            return null !== $option->getId();
        })->indexBy('id');

        // Load existing defaults for existing options, and group by option
        $columnList = ['filter_option_id' => $existingOptions->keys()];
        if ($roleTypeFilter) {
            $columnList['role_type'] = $roleTypeFilter;
        }
        $existingDefaults          = $this->getFilterDefaultMapper()->findByColumnList($columnList);
        $defaultsPerExistingOption = $existingDefaults->groupByCallback(function (Defaults\Model $default) {
            return $default->getFilterOption()->getId();
        });

        // Add defaults to the options, and index defaults by roleType
        foreach ($defaultsPerExistingOption as $optionId => $defaults) {
            $existingOptions[$optionId]->setDefaults($defaults->indexBy('roleType'));
        }

        // Group the options by page, then index by key
        /** @var Collection|GenericCollection[]|Option\Model[][] $optionsPerPage */
        $optionsPerPage = $availableOptions->groupBy('page');
        foreach ($optionsPerPage as $page => $options) {
            $options->indexBy('key');
        }

        // Ensure the pages are sorted as defined by the filter definition
        $sortedOptionsPerPage = new Collection();
        foreach (static::getPages() as $page) {
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }
            if ($optionsPerPage[$page]) {
                $sortedOptionsPerPage[$page] = $optionsPerPage[$page];
            } else {
                $sortedOptionsPerPage[$page] = $this->getFilterOptionMapper()->createCollection();
            }

        }

        // Ensure all defaults are present in our model graph
        foreach ($sortedOptionsPerPage as $page => $options) {
            /** @var GenericCollection|Option\Model[] $options */
            foreach ($options as $key => $option) {
                $defaults = $option->getDefaults();
                // Does it contain a Default for all relevant roleTypes?
                foreach (Option\Model::getRoleTypesForPage($page) as $roleType) {
                    // Are we filtering results ?
                    if ($roleTypeFilter && $roleTypeFilter !== $roleType) {
                        continue;
                    }
                    if (!$defaults->offsetExists($roleType)) {
                        $default = $this->getFilterDefaultMapper()->createModel();
                        $default
                            ->setFilterOption($option)
                            ->setRoleType($roleType)
                            ->setSelected($this->getInitialSelectedState($key, $page, $roleType));
                        $defaults[$roleType] = $default;
                    }
                }
            }
        }

        return $sortedOptionsPerPage;
    }


    /**
     * If not yet configured by the admin, should this filter option be selected by default
     *
     * By default, return false (never setting available option to be pre-selected)
     *
     * @param string $key
     * @param string $page
     * @param string $roleType
     * @return bool
     */
    abstract protected function getInitialSelectedState($key, $page, $roleType);


    /**
     * Return the ordered, hard-codes list of custom keys for this filter.
     *
     * These may represent abstract concepts, eg 'all', 'past', '1' => Project, '2' => Quiz
     * They do not include those abstract keys that relate to concrete items, eg '1' => Term(2017)
     *
     * @return string[]
     */
    abstract protected function getCustomKeys();


    /**
     * Each filter definition must know how to sort options
     *
     * @param GenericCollection|Option\Model[] $options
     * @return GenericCollection
     */
    abstract protected function sortFilterOptions(GenericCollection $options);


    /**
     * Return the templates directory for the concrete filter definition's twig templates
     *
     * @return string
     */
    protected function getTemplatesDir()
    {
        $class_info          = new \ReflectionClass($this);
        $filterDefinitionDir = basename(dirname($class_info->getFileName())) . DIRECTORY_SEPARATOR;
        return '@filterDefinitions' . DIRECTORY_SEPARATOR . $filterDefinitionDir . DIRECTORY_SEPARATOR . 'templates';
    }


    /**
     * @inheritdoc
     */
    public function getOptionsTemplate()
    {
        return $this->getTemplatesDir() . DIRECTORY_SEPARATOR . 'options.twig';
    }


    /**
     * @inheritdoc
     */
    public function getDefaultsTemplate()
    {
        return $this->getTemplatesDir() . DIRECTORY_SEPARATOR . 'defaults.twig';
    }


    /**
     * @inheritdoc
     */
    public function getPanelComponentTemplate()
    {
        return $this->getTemplatesDir() . DIRECTORY_SEPARATOR . 'component.twig';
    }

    /* Getters
     */


    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @return Option\Mapper
     */
    protected function getFilterOptionMapper()
    {
        return $this->filterOptionMapper;
    }


    /**
     * @return Defaults\Mapper
     */
    public function getFilterDefaultMapper()
    {
        return $this->filterDefaultMapper;
    }
}
