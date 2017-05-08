<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Filter\AbstractFilterComponent;
use Schoolbox\GenericCollection;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Selection;

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
            $termFinish = $term->getActualFinish();
            $available  = $selected = false;
            if ($optionDefaults->offsetExists(Definition::KEY_ALL)) {
                $available = true;
                $selected  = $selected || $optionDefaults[Definition::KEY_ALL]->getDefaults()[$roleType]->getSelected();
            } elseif ($optionDefaults->offsetExists(Definition::KEY_FUTURE) && $term->getStart()->isFuture()) {
                $available = true;
                $selected  = $selected || $optionDefaults[Definition::KEY_FUTURE]->getDefaults()[$roleType]->getSelected();
            } elseif ($optionDefaults->offsetExists(Definition::KEY_CURRENT) && $this->getDefinition()->getCurrentTerm()
                && $this->getDefinition()->getCurrentTerm()->getId() === $term->getId()
            ) {
                $available = true;
                $selected  = $selected || $optionDefaults[Definition::KEY_CURRENT]->getDefaults()[$roleType]->getSelected();
            } elseif ($optionDefaults->offsetExists(Definition::KEY_PREVIOUS) && $this->getDefinition()->getPreviousTerm()
                && $this->getDefinition()->getPreviousTerm()->getId() === $term->getId()
            ) {
                $available = true;
                $selected  = $selected || $optionDefaults[Definition::KEY_PREVIOUS]->getDefaults()[$roleType]->getSelected();
            } elseif (!$termFinish->isNull() && $optionDefaults->offsetExists(Definition::KEY_PAST) && $term->getActualFinish()->isPast()) {
                $available = true;
                $selected  = $selected || $optionDefaults[Definition::KEY_PAST]->getDefaults()[$roleType]->getSelected();
            } elseif ($optionDefaults->offsetExists($term->getId())) {
                $available = true;
                $selected  = $selected || $optionDefaults[$term->getId()]->getDefaults()[$roleType]->getSelected();
            }

            if ($available) {
                $key                       = (string)$term->getId();
                $availableSelections[$key] = $this->createSelection($key, $selected);
            }
        }
        return $availableSelections;
    }

}
