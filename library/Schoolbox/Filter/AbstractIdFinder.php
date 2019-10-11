<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use function array_map;
use InvalidArgumentException;
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
    public static function getInstance(Mapper $filterMapper, IMapper $mapper): IdFinder
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
    public function findIdsByFilters(array $filters = [], bool $deleted = false): array
    {
        // No filters ?
        if (0 === count($filters)) {
            return $this->getMapper()->findAllIds(['deleted' => $deleted]);
        }

        // Ensure all filter keys so we don't need to perform repeated isset() checks
        $completeFilters = $this->normaliseFilters($filters);

        // Can we conclude filters will never permit any assessments to match? ie always an empty set?
        if ($this->doFiltersGuaranteeEmptySet($completeFilters)) {
            return [];
        }

        // Can we simplify filters using set theory?
        $completeFilters = $this->simplifyFilters($completeFilters);

        list($query, $params) = $this->buildQuery($completeFilters, $deleted);
        $idStatement = $this->getMapper()->getDatabase()->query($query, $params);
        $ids         = array_column($idStatement->fetchAll(), $this->getMapper()->getIdentifierColumn());

        // Need to de-duplicate some forms of sub-query combination
        // (eg, if nullable assessment context filter has both (none) + 1 or more concrete items checked)
        $uniqueIds = array_unique($ids);
        sort($uniqueIds);

        // Respect id data type
        $hydrator          = $this->getMapper()->getColumnScalarHydrator($this->getMapper()->getIdentifierColumn());
        $hydratedUniqueIds = array_map(static function ($id) use ($hydrator) {
            return $hydrator->hydrate($id);
        }, $uniqueIds);

        return $hydratedUniqueIds;
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

        // Filter out any passed-in filters this ID finder deals not with
        // (Previously this errored out if unrecognized filters were found.
        //  Now that multiple ID finders may operate on the same query string,
        //  just skip)
        foreach ($sparseFilters as $filterClass => $val) {
            if (array_key_exists($filterClass, $this->registry)) {
                $completeFilters[$filterClass] = $val;
            }
        }

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
                throw new BrokenFilterException($filterClass, 'Unrecognised filter value: ' . gettype($value));
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
    abstract protected function doFiltersGuaranteeEmptySet(array $completeFilters): bool;


    /**
     * Can we NULL out some filters to make the query faster?
     *
     * Filters are combined using logical AND. If one filter is a subset of another, we can NULL out the superset filter.
     * ie
     *   A ⊆ B, A ∩ B => A
     *
     * @precondition Any filter combinations that guarantee empty assessment sets are not passed to this method
     * @param Collection[]|null[]|mixed[][] $completeFilters
     * @return Collection[]|null[]|mixed[][]
     */
    abstract protected function simplifyFilters(array $completeFilters): array;


    /**
     * Protected so we can call from child unit-testing class
     *
     * @param Collection[]|null[]|mixed[][] $completeFilters
     * @param bool                          $deleted Whether to include soft-deleted entities
     * @return mixed[]                      [$query, $params]
     * @throws BrokenFilterException If a filter SQL fragment cannot be built
     */
    abstract protected function buildQuery(array $completeFilters, bool $deleted): array;


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
