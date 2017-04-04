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
        $validPages = $this->getPages();

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
        /** @var GenericCollection|Option\Model[] $pageOptions */
        foreach ($optionsPerPage as $page => $pageOptions) {
            $pageOptions->indexBy('key');

            // Ensure it contains 'all', 'current', etc
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
     * @param string $page
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
     * @return string
     */
    protected abstract function getTemplateFile();


    /**
     * Return the hard-codes list of custom keys for this filter.
     * These may represent abstract concepts, eg 'all', 'past' etc, or concrete items, like '1' => Project, '2' => Quiz
     *
     * @return string[]
     */
    protected abstract function getCustomKeys();


    /**
     * Each filter definition must know how to sort options
     *
     * @param GenericCollection|Option\Model[] $options
     * @return GenericCollection
     */
    protected abstract function sortFilterOptions(GenericCollection $options);


    /**
     * If the admin has not configured this option, should it be available in filter panels?
     *
     * By default, all possible options will be available on all pages.
     *
     * @param string $customAbstractKey
     * @param string $page
     * @return bool
     * @todo figure out how to get rid of grey squiggles. Put on interface? yuk!
     */
    protected function getInitialAvailability($customAbstractKey, $page)
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public function getAdminTemplate()
    {
        return "@filters/admin/{$this->getTemplateFile()}";
    }


    /**
     * @inheritdoc
     */
    public function getOptionsTemplate()
    {
        return "@filters/options/{$this->getTemplateFile()}";
    }


    /**
     * @inheritdoc
     */
    public function getDefaultsTemplate()
    {
        return "@filters/defaults/{$this->getTemplateFile()}";
    }


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
