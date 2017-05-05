<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\TermDue;

use Schoolbox\Assessment\Term;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;

/**
 * A factory for building 'Term' filter objects
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * @param Filter\Mapper    $filterMapper
     * @param Option\Mapper    $optionMapper
     * @param Defaults\Mapper  $defaultMapper
     * @param Selection\Mapper $selectionMapper
     * @param Term\Mapper      $termMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Term\Mapper $termMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->termMapper = $termMapper;
    }


    /**
     * @inheritdoc
     */
    protected function buildDefinitionArgs(Filter\Model $filter)
    {
        $sortedTerms = $this->getSortedTerms();

        return array_merge(parent::buildDefinitionArgs($filter), [
            'terms'        => $sortedTerms,
            'currentTerm'  => $this->getCurrentTerm($sortedTerms),
            'previousTerm' => $this->getPreviousTerm($sortedTerms),
        ]);
    }


    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(User $user, $page, Filter\IFilterDefinition $definition, User $viewee = null)
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildKeyConverterArgs($filter), [
            'terms' => $this->getSortedTerms(),
        ]);
    }


    /**
     * @return GenericCollection|Term\Model[]
     */
    private function getSortedTerms()
    {
        /** @var GenericCollection|Term\Model[] $terms */
        $terms = $this->termMapper->findAll(['order' => ['start' => 'DESC']])->indexBy('id');
        return $terms;
    }


    /**
     * @param GenericCollection|Term\Model[] $terms
     * @return Term\Model|null
     */
    private function getCurrentTerm(GenericCollection $terms)
    {
        $now = \Alaress_Date_Format::now();
        foreach ($terms as $term) {
            if ($term->contains($now)) {
                return $term;
            }
        }
        return null;
    }


    /**
     * Gets the term before the current one. If we are not currently in a term, returns the most recent term.
     *
     * Returns null if there are no previous terms at all.
     *
     * @param GenericCollection|Term\Model[] $terms
     * @return null|Term\Model
     */
    private function getPreviousTerm(GenericCollection $terms)
    {
        /** @var GenericCollection|Term\Model[] $sortedTerms */
        $currentTerm = $this->getCurrentTerm($terms);

        if (!$currentTerm) {
            // Get the most recent term that finished in the past
            foreach ($terms as $term) {
                $termFinish = $term->getActualFinish();
                if (!$termFinish->isNull() && $termFinish->isPast()) {
                    return $term;
                }
            }
            return null;
        }

        // Loop a single index back past the current term
        $currentFound = false;
        foreach ($terms as $term) {
            if ($currentFound) {
                return $term;
            }
            if ($term->getId() === $currentTerm->getId()) {
                $currentFound = true;
            }
        }
        return null;
    }
}
