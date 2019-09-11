<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Term;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Term;
use function array_map;
use function array_merge;


/**
 * Available Term options:
 * □ all
 * □ future
 * □ current
 * □ previous, and
 * □ all terms existing in DB
 *
 * Term options are keyed by 'current', 'previous' and the term IDs
 */
abstract class Definition extends Filter\AbstractDefinition
{

    const KEY_ALL = 'all';        // All terms
    const KEY_FUTURE = 'future';     // All terms in future
    const KEY_CURRENT = 'current';    // current term
    const KEY_PREVIOUS = 'previous';   // one before current term
    const KEY_PAST = 'past';       // All terms in past

    /**
     * @var GenericCollection|Term\Model[]
     */
    private $terms;

    /** @var null|Term\Model */
    private $currentTerm;

    /** @var null|Term\Model */
    private $previousTerm;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection                  $terms
     * @param Term\Model|null                    $currentTerm
     * @param Term\Model|null                    $previousTerm
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $terms,
        Term\Model $currentTerm = null,
        Term\Model $previousTerm = null
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->terms        = $terms;
        $this->currentTerm  = $currentTerm;
        $this->previousTerm = $previousTerm;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_ALL,
            self::KEY_FUTURE,
            self::KEY_CURRENT,
            self::KEY_PREVIOUS,
            self::KEY_PAST,
        ];
    }


    /**
     * If the admin has not configured this option, should it be available in filter panels?
     *
     * @param string            $customAbstractKey
     * @param Filter\Page\Model $pageFilter
     * @return bool
     */
    public function getInitialAvailability(string $customAbstractKey, Filter\Page\Model $pageFilter): bool
    {
        $isAvailable = false;
        $page        = $pageFilter->getPage();
        switch ($customAbstractKey) {
            case self::KEY_ALL:
                return Filter\Page\Model::PAGE_PENDING_ASSESSMENTS !== $page;
            case self::KEY_FUTURE:
                return Filter\Page\Model::PAGE_PENDING_ASSESSMENTS === $page;
            case self::KEY_CURRENT:
                return Filter\Page\Model::PAGE_PENDING_ASSESSMENTS === $page;
            case self::KEY_PREVIOUS:
                return Filter\Page\Model::PAGE_PENDING_ASSESSMENTS === $page;
            case self::KEY_PAST:
                return false;
        }
        return $isAvailable;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        if (Filter\Page\Model::PAGE_PENDING_ASSESSMENTS === $page) {
            return in_array($key, [self::KEY_FUTURE, self::KEY_CURRENT], true);
        }
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all Term instances
        foreach ($this->terms as $term) {
            if (!$options->offsetExists($term->getId())) {
                $option = new Filter\Page\Option\Model();
                $key    = (string)$term->getId();
                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($key)
                    ->setAvailable($this->getInitialAvailability($key, $pageFilter))
                    ->setDefaults(new GenericCollection(Filter\Page\Option\Defaults\Model::class));
                $options->setOffset($option->getKey(), $option);
            }
        }
        return $options;
    }


    /**
     * @param GenericCollection|\Schoolbox\Filter\Page\Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options): GenericCollection
    {
        return $options->sortByCallback(function (Filter\Page\Option\Model $left, Filter\Page\Option\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if ($leftKey === $rightKey) {
                return 0;
            }
            if (is_numeric($leftKey) && !is_numeric($rightKey)) {
                return 1;
            }
            if (!is_numeric($leftKey) && is_numeric($rightKey)) {
                return -1;
            }
            if (self::KEY_ALL === $leftKey) {
                return -1;
            }
            if (self::KEY_FUTURE === $leftKey) {
                return self::KEY_ALL === $right->getKey() ? +1 : -1;
            }
            if (self::KEY_CURRENT === $leftKey) {
                return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE], true) ? +1 : -1;
            }
            if (self::KEY_PREVIOUS === $leftKey) {
                return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT], true) ? +1 : -1;
            }
            if (self::KEY_PAST === $leftKey) {
                return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT, self::KEY_PREVIOUS], true) ? +1 : -1;
            }
            if (in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT, self::KEY_PREVIOUS, self::KEY_PAST], true)) {
                return -1;
            }
            return $this->terms[$rightKey]->compare($this->terms[$leftKey]);
        }, true);
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {
        // Ensure numeric ids are cast to string keys
        return array_merge($this->getCustomKeys(), array_map(static function (int $termId) {
            return (string)$termId;
        }, $this->terms->keys()));
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_ALL === $key) {
            return 'All terms';
        }
        if (self::KEY_FUTURE === $key) {
            return 'Future terms';
        }
        if (self::KEY_CURRENT === $key) {
            return $this->currentTerm ? "Current ({$this->currentTerm->getCode()})" : '';
        }
        if (self::KEY_PREVIOUS === $key) {
            return $this->previousTerm ? "Previous ({$this->previousTerm->getCode()})" : '';
        }
        if (self::KEY_PAST === $key) {
            return 'Past terms';
        }
        return $this->terms[$key]->getCode() . ($this->terms[$key]->getArchived() ? ' [archived]' : '');
    }


    /* Getters
     */

    /**
     * @return Term\Model[]|GenericCollection
     */
    public function getTerms()
    {
        return $this->terms;
    }


    /**
     * @return Term\Model
     */
    public function getCurrentTerm()
    {
        return $this->currentTerm;
    }


    /**
     * @return Term\Model
     */
    public function getPreviousTerm()
    {
        return $this->previousTerm;
    }
}
