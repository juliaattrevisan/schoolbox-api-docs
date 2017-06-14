<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;

/**
 * Provides common filter definition behaviour
 */
abstract class AbstractDefinition implements IDefinition
{

    // The name to expose in the UI
    const NAME = '';

    /** @var Model */
    private $filter;

    /** @var Page\Option\Mapper */
    private $filterOptionMapper;

    /** @var Page\Option\Defaults\Mapper */
    private $filterDefaultMapper;


    /**
     * @param Model                       $filter
     * @param Page\Option\Mapper          $filterOptionMapper
     * @param Page\Option\Defaults\Mapper $filterDefaultMapper
     */
    public function __construct(Model $filter, Page\Option\Mapper $filterOptionMapper, Page\Option\Defaults\Mapper $filterDefaultMapper)
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
        if (!$name) {
            throw new \DomainException('Concrete classes must implement the NAME constant');
        }
        return $name;
    }


    /**
     * @inheritdoc
     */
    public static function isMandatory($page)
    {
        // Default to not being mandatory. The admin can disable the filter from any page.
        return false;
    }


    /**
     * @inheritdoc
     */
    public static function getEnabledInitialState($page)
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function getUserConfigurableInitialState($page)
    {
        return true;
    }


    /**
     * If the admin has not configured this option, should it be available in filter panels?
     *
     * Normally, all possible options will be available on all pages. (ie just return true)
     *
     * @param string     $customAbstractKey
     * @param Page\Model $pageFilter
     * @return bool
     */
    protected abstract function getInitialAvailability($customAbstractKey, Page\Model $pageFilter);


    /**
     * Ensure this pageFilterOptions contains all custom abstract options.
     *
     * eg 'All' 'Past' 'Future' etc
     *
     * @param Page\Model $pageFilter
     * @return GenericCollection|Page\Option\Model[]
     */
    protected function insertAbstractOptions(Page\Model $pageFilter)
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all abstract custom options
        foreach ($this->getCustomKeys() as $customAbstractKey) {
            if (!$options->offsetExists($customAbstractKey)) {
                $option = $this->filterOptionMapper->createModel();

                // If not yet configured by admin, should this option be available?
                $isAvailable = $this->getInitialAvailability($customAbstractKey, $pageFilter);

                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($customAbstractKey)
                    ->setAvailable($isAvailable);
                $options->setOffset($option->getKey(), $option);
            }
        }
        return $options;
    }


    /**
     * Insert dynamic options into list. Eg each WorkType or Term that exists in the system right now.
     *
     * @param Page\Model $pageFilter
     * @return GenericCollection|Page\Option\Model[]
     */
    protected function insertDynamicOptions(Page\Model $pageFilter)
    {
        return $pageFilter->getOptions();
    }


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
     * @param Page\Model $pageFilter
     * @return GenericCollection|Page\Option\Model[]
     */
    protected function unsetInvalidOptions(Page\Model $pageFilter)
    {
        // Note: You must override this is you wish to remove some options for specific pages
        $validKeys = $this->getAbstractKeys();
        $validKeys = array_combine($validKeys, $validKeys);

        $options = $pageFilter->getOptions();

        foreach ($options as $idx => $option) {
            if (!array_key_exists($option->getKey(), $validKeys)) {
                $options->offsetUnset($idx);
            }
        }
        return $options;
    }


    /**
     * Removes any defaults that should not exist.
     *
     * This can occur when we change which roleTypes can view a page
     *
     * If filtering to a specific roleType, remove anything that doesn't match
     *
     * @param Page\Model  $pageFilter
     * @param null|string $roleTypeFilter
     * @return GenericCollection|Page\Option\Model[]
     */
    protected function unsetInvalidDefaults(Page\Model $pageFilter, $roleTypeFilter = null)
    {
        $validRoleTypes = $roleTypeFilter ? [$roleTypeFilter] : Page\Model::getPageRoleTypes()[$pageFilter->getPage()];
        $validRoleTypes = array_combine($validRoleTypes, $validRoleTypes);

        $options = $pageFilter->getOptions();
        foreach ($options as $option) {
            $defaults = $option->getDefaults();
            foreach ($defaults as $idx => $default) {
                if (!array_key_exists($default->getRoleType(), $validRoleTypes)) {
                    $defaults->offsetUnset($idx);
                }
            }
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    public function normalisePageOptions(Page\Model $pageFilter, $roleTypeFilter = null)
    {
        $this->insertAbstractOptions($pageFilter);
        $this->insertDynamicOptions($pageFilter);
        $this->unsetInvalidOptions($pageFilter);
        $this->unsetInvalidDefaults($pageFilter, $roleTypeFilter);
        $this->insertRoleDefaults($pageFilter, $roleTypeFilter);

        return $this->sortFilterOptions($pageFilter->getOptions());
    }


    /**
     * @param Page\Model  $pageFilter
     * @param null|string $roleTypeFilter
     */
    private function insertRoleDefaults(Page\Model $pageFilter, $roleTypeFilter = null)
    {
        // Get the role types that can access this page
        $roleTypes = Page\Model::getRoleTypesForPage($pageFilter->getPage());

        /* Ensure all defaults are present in our model graph
         * If saved in DB, use that, else use system-initial values
         */
        foreach ($pageFilter->getOptions() as $option) {

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
                        ->setSelected($this->getInitialSelectedState($option->getKey(), $pageFilter->getPage(), $roleType));
                    $option->getDefaults()[$roleType] = $default;
                }
            }
        }
    }


    /**
     * If not yet configured by the admin, should this filter option be selected by default
     *
     * By default, return false (never setting available option to be pre-selected)
     *
     * @param string $key The option's abstract key
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
     * @param GenericCollection|Page\Option\Model[] $options
     * @return GenericCollection|Page\Option\Model[]
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        return $options->sortByCallback(function (Page\Option\Model $left, Page\Option\Model $right) {

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
    protected static function getTemplatesDir()
    {
        // undefined on abstract. Concrete class must implement
        return '';
    }


    /**
     * @inheritdoc
     * @throws \DomainException If this definition has not defined a template directory
     */
    public static function getPanelComponentTemplate()
    {
        $templateDir = static::getTemplatesDir();

        if (!$templateDir) {
            throw new \DomainException('Template directory not defined for filter definition: ' . get_called_class());
        }

        return $templateDir . DIRECTORY_SEPARATOR . 'component.twig';
    }

    /* Getters
     */


    /**
     * @return Model
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @return Page\Option\Mapper
     */
    protected function getFilterOptionMapper()
    {
        return $this->filterOptionMapper;
    }


    /**
     * @return Page\Option\Defaults\Mapper
     */
    public function getFilterDefaultMapper()
    {
        return $this->filterDefaultMapper;
    }
}
