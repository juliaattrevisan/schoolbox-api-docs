<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Provides common filter definition behaviour
 */
abstract class AbstractDefinition implements IDefinition
{
    // The name to expose in the UI
    const NAME = '';

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
     * @inheritdoc
     * @throws \DomainException If NAME constant not defined
     */
    public static function getName()
    {
        $name = static::NAME;
        if(!$name){
            throw new \DomainException('Concrete classes must implement the NAME constant');
        }
        return $name;
    }


    /**
     * @inheritdoc
     */
    public function getOptions($page)
    {
        // Is specified page is not in the valid pages?
        if (!in_array($page, static::getPages(), true)) {
            // Bug out
            return $this->filterOptionMapper->createCollection();
        }

        // Get existing DB values, discarding any that belong to invalid pages
        $columnList = [
            'filter_id' => $this->getFilter()->getId(),
            'page'      => $page,
        ];

        /** @var GenericCollection|Option\Model[] $pageOptions */
        $pageOptions = $this->getFilterOptionMapper()->findByColumnList($columnList)->indexBy('key');

        // Ensure it contains all abstract custom options
        foreach ($this->getCustomKeys() as $customAbstractKey) {
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
        return $this->sortFilterOptions($pageOptions);
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
     * Filters that are based on lookup lists will need to remove items no longer in that lookup list
     *
     * @param GenericCollection|Option\Model[] $pageOptions
     * @return GenericCollection|Option\Model[]
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // Note: You must override this is you wish to remove some options for specific pages
        $validKeys = $this->getAbstractKeys();
        $validKeys = array_combine($validKeys, $validKeys);

        foreach ($pageOptions as $idx => $option) {
            if(!array_key_exists($option->getKey(), $validKeys)){
                $pageOptions->offsetUnset($idx);
            }
        }
        return $pageOptions;
    }


    /**
     * @inheritdoc
     * @throws MapperException
     */
    public function getOptionsWithDefaults($page, $roleTypeFilter = null)
    {
        // Get the role types that can access this page
        $roleTypes = Option\Model::getRoleTypesForPage($page);

        // Get all possible options for this page, indexed by option.key
        $pageOptions = $this->getOptions($page);

        // Load existing defaults for options, and group by option
        // We need to filter by $roleTypes to ensure if we have removed roles from accessing the page, they do not
        // continue to appear in the admin options
        $columnList = [
            'filter_option_id' => $pageOptions->getColumnValues('id'),
            'role_type'        => $roleTypeFilter ?: $roleTypes,
        ];

        $existingDefaults          = $this->getFilterDefaultMapper()->findByColumnList($columnList);
        $existingDefaultsPerOption = $existingDefaults->groupByCallback(function (Defaults\Model $default) {
            return $default->getFilterOption()->getKey();
        });

        // At this point, options are valid, but defaults may belong to invalid options. This
        // will occur if, eg an item is removed from a lookup list
        foreach ($pageOptions as $optionKey => $option){
            if($existingDefaultsPerOption->offsetExists($optionKey)){
                $option->setDefaults($existingDefaultsPerOption[$optionKey]->indexBy('roleType'));
            }
        }


        /* Ensure all defaults are present in our model graph
         * If saved in DB, use that, else use system-initial values
         */
        foreach ($pageOptions as $option) {

            foreach ($roleTypes as $roleType) {

                // Are we filtering results ?
                if ($roleTypeFilter && $roleTypeFilter !== $roleType) {
                    continue;
                }

                if (!$option->getDefaults()->offsetExists($roleType)) {
                    $default = $this->filterDefaultMapper->createModel();
                    $default
                        ->setFilterOption($option)
                        ->setRoleType($roleType)
                        ->setSelected($this->getInitialSelectedState($option->getKey(), $page, $roleType));
                    $option->getDefaults()[$roleType] = $default;
                }
            }
        }

        return $this->sortFilterOptions($pageOptions);
    }


    /**
     * If not yet configured by the admin, should this filter option be selected by default
     *
     * By default, return false (never setting available option to be pre-selected)
     *
     * @param string $key       The option's abstract key
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
     * @inheritdoc
     */
    public function getAbstractKeys()
    {
        // Default. You MUST override if the set of options displayed in admin includes dynamic options (eg Terms, WorkTypes etc).
        // Dynamic options depend on database contents, but do not change per viewee.
        return $this->getCustomKeys();
    }


    /**
     * Each filter definition must know how to sort options
     *
     * By default, sort them as defined in $this->getCustomKeys()
     *
     * @param GenericCollection|Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        return $options->sortByCallback(function (Option\Model $left, Option\Model $right) {

            $leftPos  = array_search($left->getKey(), $this->getAbstractKeys(), true);
            $rightPos = array_search($right->getKey(), $this->getAbstractKeys(), true);

            return $leftPos - $rightPos;
        }, true);
    }


    /**
     * Return the templates directory for the concrete filter definition's twig templates
     *
     * @return string
     */
    protected abstract function getTemplatesDir();


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
