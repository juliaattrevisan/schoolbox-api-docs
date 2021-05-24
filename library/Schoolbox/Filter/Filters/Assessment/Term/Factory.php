<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Term;

use Alaress_Date_Format;
use Schoolbox\Assessment\Term;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\GenericCollection;
use Schoolbox\Filter;
use Schoolbox\User;

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
     * @inheritDoc
     * @return Filter\IComponent|Component
     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): Filter\IComponent {
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper()
        );
    }


    /**
     * Gets a list of all the Terms, both ordinary and extraordinary
     *
     * This is used in the Admin->Filters section to provide a list of all the Terms a super
     * can make available on certain pages. Extraordinary Terms, while they appear in this list,
     * should not be included in the Current Term and Previous Term derivation
     *
     * @return GenericCollection|Term\Model[]
     */
    protected function getAllSortedTerms(): GenericCollection
    {
        /** @var GenericCollection|Term\Model[] $terms */
        $terms = $this->termMapper->findAll(['order' => ['start' => 'DESC']])->indexBy('id');
        return $terms;
    }


    /**
     * Gets a list of all the ordinary Terms, sorted by start date desc
     *
     * This is used in the Admin->Filters section to calculate the Current and Previous Term
     * values programmatically in the list of available Terms to show on certain pages
     *
     * @return GenericCollection|Term\Model[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    protected function getNonExtraordinarySortedTerms(): GenericCollection
    {
        /* Only include the non-extraordinary Term instances
         * These are already sorted by start date desc
         */
        return $this->termMapper->findAllNonExtraordinary()->indexBy('id');
    }


    /**
     * @param GenericCollection|Term\Model[] $terms
     * @return null|Term\Model
     */
    protected function getCurrentTerm(GenericCollection $terms): ?Term\Model
    {
        $now = Alaress_Date_Format::now();

        /* Extraordinary Terms aren't part of the normal sequence of Terms
         * Disregard their presence in this list
         */
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

        /* Extraordinary Terms aren't part of the normal sequence of Terms
         * Disregard their presence in this list
         */
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
