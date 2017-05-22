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

    /**
     * The mapper for the entity being filtered
     *
     * eg, the 'assessment' mapper if this instance is fetching 'assessment' IDs
     *
     * @var IMapper
     */
    private $mapper;

    /**
     * A list of filter definitions this class knows how to apply
     *
     * Indexed by filterDefinition.class
     *
     * @var string[]
     */
    private $registry;


    /**
     * @inheritdoc
     */
    public static function getInstance(Mapper $filterMapper, IMapper $mapper)
    {
        $className = get_called_class();
        if (!isset(self::$instance[$className])) {
            self::$instance[$className] = new static($mapper);
            self::$instance[$className]->buildRegistry($filterMapper, $mapper);
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
     * @inheritdoc
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
        $ids         = array_column($idStatement->fetchAll(), $this->getMapper()->getIdentifierColumn());

        // Need to de-duplicate some forms of sub-query combination
        // (eg, if nullable assessment context filter has both (none) + 1 or more concrete items checked)
        $uniqueIds = array_unique($ids);
        sort($uniqueIds);
        return $uniqueIds;
    }


    /**
     * Build a registry of filters known to this class
     *
     * @param Mapper  $filterMapper The filter instance mapper
     * @param IMapper $entityMapper A mapper for the entity being filtered, eg 'assessments'
     */
    protected function buildRegistry(Mapper $filterMapper, IMapper $entityMapper)
    {
        $filterInstances = $filterMapper->findForEntity(get_class($entityMapper));
        $filterClasses   = $filterInstances->getColumnValues('class');
        $this->registry  = array_combine($filterClasses, $filterClasses);
    }


    /**
     * Ensures that any filters passed in are known to this class
     *
     * @param Collection[]|null[]|mixed[][] $filters
     * @throws \InvalidArgumentException If filter definition class not recognised
     */
    protected function checkValidFilterKeys(array $filters)
    {
        foreach ($filters as $filterClass => $val) {
            if (!array_key_exists($filterClass, $this->registry)) {
                throw new \InvalidArgumentException("Unknown filter: $filterClass");
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
     * @throws BrokenFilterException If unable to normalise a filter value
     */
    protected function normaliseFilters(array $sparseFilters)
    {
        $completeFilters = [];
        foreach ($this->registry as $filterClass) {
            // Not in the passed filters? Set it explicitly to null.
            if (!array_key_exists($filterClass, $sparseFilters)) {
                $completeFilters[$filterClass] = null;
                continue;
            }
            $value = $sparseFilters[$filterClass];

            // Ensure it is either null, or a (potentially empty) Collection
            if (is_array($value)) {
                $completeFilters[$filterClass] = new Collection($value);
            } elseif (null === $value || $value instanceof Collection) {
                $completeFilters[$filterClass] = $value;
            } elseif (is_object($value) || is_scalar($value)) {
                // Enables caller to specify a inject a single object or scalar, and have it normalised here
                $completeFilters[$filterClass] = new Collection([$value]);
            } else {
                throw new BrokenFilterException('Unrecognised filter value: ' . gettype($value));
            }
        }

        return $completeFilters;
    }


    /**
     * Can examination of the filters using set-theory indicate that no entities could ever match?
     *
     * @param Collection[]|null[]|mixed[][] $completeFilters
     * @return bool
     */
    protected abstract function doFiltersGuaranteeEmptySet(array $completeFilters);


    /**
     * Protected so we can call from child unit-testing class
     *
     * @param Collection[]|null[]|mixed[][] $completeFilters
     * @param bool                          $deleted Whether to include soft-deleted entities
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
     * @param string[] $registry
     * @return $this
     */
    protected function setRegistry(array $registry)
    {
        $this->registry = $registry;
        return $this;
    }
}
