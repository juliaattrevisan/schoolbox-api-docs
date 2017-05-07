<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\IMapper;

/**
 * Logic common to all IdFinders
 */
abstract class AbstractIdFinder implements IdFinder
{
    /** @var static[] $instance */
    private static $instance = [];

    /** @var IMapper */
    private $mapper;

    /**
     * A list of filter definitions this class knows how to apply
     *
     * Indexed by IFilterDefinition->getSlug()
     * @var string[]
     */
    private $registry;


    /**
     * @inheritdoc
     */
    public static function getInstance(IMapper $mapper)
    {
        $className = get_called_class();
        if (!isset(self::$instance[$className])) {
            self::$instance[$className] = new static($mapper);
            self::$instance[$className]->setRegistry(self::$instance[$className]->buildRegistry());
        }
        return self::$instance[$className];
    }


    /**
     * @param IMapper $mapper
     */
    private function __construct(IMapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * Build a registry of filters known to this class
     * @return string[]
     */
    protected abstract function buildRegistry();


    /**
     * @inheritdoc
     * @throws BrokenFilterException If a injected filter is unable to be processed
     */
    public function findIdsByFilters(array $filters = [], $deleted = false)
    {
        // No filters ?
        if (0 === count($filters)) {
            return $this->getMapper()->findAllIds(['deleted' => $deleted]);
        }

        // Filters supplied. Ensure they are recognisable.
        $this->checkValidFilterKeys($filters);

        // Ensure all filter keys so we don't need to perform repeated isset() checks
        $completeFilters = $this->normaliseFilters($filters);

        // Can we conclude filters will never permit any assessments to match? ie always an empty set?
        if ($this->doFiltersGuaranteeEmptySet($completeFilters)) {
            return [];
        }

        list($query, $params) = $this->buildQuery($completeFilters, $deleted);
        $idStatement = $this->getMapper()->getDatabase()->query($query, $params);
        $ids = array_column($idStatement->fetchAll(), $this->getMapper()->getIdentifierColumn());

        // Need to deduplicate some forms of sub-query combination
        // (eg, if nullable assessment context filter has both (none) + 1 or more concrete items checked)
        $uniqueIds = array_unique($ids);
        sort($uniqueIds);
        return $uniqueIds;
    }


    /**
     * @inheritdoc
     */
    public function findIdsByTransformer(Transformer $transformer, $deleted = false)
    {
        $filters = [];
        foreach($this->registry as $slug => $class){
            try{
                $filters[$slug] = $transformer->getSelectedItems($slug);
            } catch (\InvalidArgumentException $e){
                // Silently discard it. It just means this component is not in use in the UI.
                // This can happen when the admin has configured it not to appear, or because it never appears
                // and is used for manual-filter-building only
            }
        }
        return $this->findIdsByFilters($filters, $deleted);
    }

    /**
     * Ensures that any filters passed in are known to this class
     *
     * @param Collection[]|null[]|mixed[][] $filters
     * @throws \InvalidArgumentException If filter key not recognised
     */
    protected function checkValidFilterKeys(array $filters)
    {
        foreach($filters as $key => $val){
            if(!array_key_exists($key, $this->registry)){
                throw new \InvalidArgumentException("Unknown filter: $key");
            }
        }
    }


    /**
     * Inserts null values for missing filters and ensures any scalars are wrapped in Collections
     *
     * This enables us to rely on filters being present, even if set explicitly to NULL
     *
     * @param array $sparseFilters
     * @return Collection[]|null[]|mixed[][]
     */
    protected function normaliseFilters(array $sparseFilters)
    {
        $completeFilters = [];
        foreach($this->registry as $filterKey => $filterClass){
            // Not in the passed filters? Set it explicitly to null.
            if(!array_key_exists($filterKey, $sparseFilters)){
                $completeFilters[$filterKey] = null;
                continue;
            }
            // Ensure it is either null, or a (potentially empty) Collection
            if(null === $sparseFilters[$filterKey] || $sparseFilters[$filterKey] instanceof Collection){
                $completeFilters[$filterKey] = $sparseFilters[$filterKey];
            } else {
                // Enables caller to specify a inject a single entity, or an array, and have it normalised here.
                $completeFilters[$filterKey] = new Collection((array)$sparseFilters[$filterKey]);
            }
        }
        return $completeFilters;
    }

    /**
     * Can examination of the filters using set-theory indicate that no entities could ever match?
     *
     * @param int[][]|null[] $completeFilters
     * @return bool
     */
    protected abstract function doFiltersGuaranteeEmptySet(array $completeFilters);

    /**
     * Protected so we can call from child unit-testing class
     *
     * @param Collection[]|null[]|mixed[][] $completeFilters
     * @param bool                          $deleted          Whether to include soft-deleted entities
     * @return mixed[]
     * @throws BrokenFilterException If a filter SQL fragment cannot be built
     */
    protected abstract function buildQuery(array $completeFilters, $deleted);


    /* Getters
    */


    /**
     * @return IMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }


    /**
     * @return string[]
     */
    protected function getRegistry()
    {
        return $this->registry;
    }


    /**
     * @param string[] $registry
     * @return $this
     */
    private function setRegistry(array $registry)
    {
        $this->registry = $registry;
        return $this;
    }
}
