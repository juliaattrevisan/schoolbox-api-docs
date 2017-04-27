<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term;

use Schoolbox\Filter\AbstractFilterComponent;
use Schoolbox\GenericCollection;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Selection;
use Schoolbox\Assessment\Term;


/**
 * @method Definition getDefinition()
 */
class Component extends AbstractFilterComponent
{

    /**
     * A concrete Term model may match one or more availability configs. Eg, the admin may have selected 'all' terms.
     * We need to examine each concrete Term model to see if it matches an availability filter
     *
     * @param GenericCollection|Option\Model[] $optionDefaults $optionDefaults
     * @return GenericCollection|Selection\Model[]
     */
    protected function getRoleSelections(GenericCollection $optionDefaults)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        // A term will be available if it matches any abstract key.
        // A term will be selected if any matching abstract term descriptions were selected

        foreach ($this->getDefinition()->getTerms() as $term) {


            if ($this->isAvailable($term, $optionDefaults)) {
                $key                       = (string)$term->getId();
                $availableSelections[$key] = $this->createSelection($key, $this->isSelected($term, $optionDefaults, $roleType));
            }
        }
        return $availableSelections;
    }


    /**
     * Should the specified Term be displayed in list of available options for the user?
     *
     * @param Term\Model        $term
     * @param GenericCollection|Option\Model[] $optionDefaults $optionDefaults
     * @return bool
     * @todo make this method redundant by moving the logic to factory so that only available terms are pumped into this component
     */
    private function isAvailable(Term\Model $term, GenericCollection $optionDefaults)
    {
        $termFinish   = $term->getActualFinish();
        $currentTerm  = $this->getDefinition()->getCurrentTerm();
        $previousTerm = $this->getDefinition()->getPreviousTerm();

        if ($optionDefaults->offsetExists(Definition::KEY_ALL)) {
            return true;
        }
        if ($optionDefaults->offsetExists(Definition::KEY_FUTURE) && $term->getStart()->isFuture()) {
            return true;
        }
        if ($currentTerm && $optionDefaults->offsetExists(Definition::KEY_CURRENT) && $currentTerm->getId() === $term->getId()) {
            return true;
        }
        if ($previousTerm && $optionDefaults->offsetExists(Definition::KEY_PREVIOUS) && $previousTerm->getId() === $term->getId()) {
            return true;
        }
        if (!$termFinish->isNull() && $optionDefaults->offsetExists(Definition::KEY_PAST) && $term->getActualFinish()->isPast()) {
            return true;
        }
        if ($optionDefaults->offsetExists($term->getId())) {
            return true;
        }
        return false;
    }

    /**
     * Should the specified Term be selected in list of available options for the user's role?
     *
     * @param Term\Model        $term
     * @param GenericCollection|Option\Model[] $optionDefaults $optionDefaults
     * @param string $roleType
     * @return bool
     */
    private function isSelected(Term\Model $term, GenericCollection $optionDefaults, $roleType)
    {
        $termFinish   = $term->getActualFinish();
        $currentTerm  = $this->getDefinition()->getCurrentTerm();
        $previousTerm = $this->getDefinition()->getPreviousTerm();

        if ($optionDefaults->offsetExists(Definition::KEY_ALL)
            && $optionDefaults[Definition::KEY_ALL]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($optionDefaults->offsetExists(Definition::KEY_FUTURE) && $term->getStart()->isFuture()
            && $optionDefaults[Definition::KEY_FUTURE]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($currentTerm && $optionDefaults->offsetExists(Definition::KEY_CURRENT) && $currentTerm->getId() === $term->getId()
            && $optionDefaults[Definition::KEY_CURRENT]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($previousTerm && $optionDefaults->offsetExists(Definition::KEY_PREVIOUS) && $previousTerm->getId() === $term->getId()
            && $optionDefaults[Definition::KEY_PREVIOUS]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if (!$termFinish->isNull() && $optionDefaults->offsetExists(Definition::KEY_PAST) && $term->getActualFinish()->isPast()
            && $optionDefaults[Definition::KEY_PAST]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($optionDefaults->offsetExists($term->getId())
            && $optionDefaults[$term->getId()]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        return false;
    }

}
