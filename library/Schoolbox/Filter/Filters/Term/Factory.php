<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Assessment\Term;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\GenericCollection;

/**
 * A factory for building TermFilters
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * @param Term\Mapper $termMapper
     */
    public function __construct(Term\Mapper $termMapper)
    {
        $this->termMapper = $termMapper;
    }


    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * @return IFilterDefinition
     */
    public function buildForAdminPages()
    {
        $definition = new Definition();
        $this->setBaseDependencies($definition);

        $sortedTerms = $this->getSortedTerms();

        $definition
            ->setTerms($sortedTerms)
            ->setKeys($this->getKeys($sortedTerms))
            ->setCurrentTerm($this->getCurrentTerm($sortedTerms))
            ->setPreviousTerm($this->getPreviousTerm($sortedTerms));
        return $definition;
    }


    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param null|IRunTimeOptions $runTimeOptions
     * @return IFilterDefinition
     */
    public function buildForFilterPanel(IRunTimeOptions $runTimeOptions = null)
    {
        $definition = new Definition();
        $this->setBaseDependencies($definition);

        $terms = $this->getSortedTerms();

        return $definition
            ->setTerms($terms)
            ->setKeys($this->getKeys($terms))
            ->setCurrentTerm($this->getCurrentTerm($terms))
            ->setPreviousTerm($this->getPreviousTerm($terms));
    }

    /**
     * Returns a key converter, able to convert between keys (term.id) and the entities they represent (terms)
     *
     * @return KeyConverter
     */
    public function buildKeyConverter()
    {
        $terms = $this->getSortedTerms();
        return new KeyConverter($terms);
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
     * @return string[]
     */
    private function getKeys(GenericCollection $terms)
    {
        return array_merge([
            Definition::KEY_ALL,
            Definition::KEY_FUTURE,
            Definition::KEY_CURRENT,
            Definition::KEY_PREVIOUS,
            Definition::KEY_PAST,
        ], $terms->getColumnValues('id'));
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
