<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term;

use Schoolbox\Assessment\Term;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\GenericCollection;
use Schoolbox\Filter;

/**
 * A factory for building 'Term' filter objects
 */
abstract class Factory extends AbstractFactory
{

    /**
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Term\Mapper                        $termMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Term\Mapper $termMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->termMapper = $termMapper;
    }


    /**
     * @return GenericCollection|Term\Model[]
     */
    protected function getSortedTerms(): GenericCollection
    {
        /** @var GenericCollection|Term\Model[] $terms */
        $terms = $this->termMapper->findAll(['order' => ['start' => 'DESC']])->indexBy('id');
        return $terms;
    }


    /**
     * @param GenericCollection|Term\Model[] $terms
     * @return null|Term\Model
     */
    protected function getCurrentTerm(GenericCollection $terms): ?Term\Model
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
    protected function getPreviousTerm(GenericCollection $terms): ?Term\Model
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
