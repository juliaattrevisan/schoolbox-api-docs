<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term;

use Schoolbox\GenericCollection;
use Schoolbox\Filter;
use Schoolbox\Assessment\Term;


/**
 * @method Definition getDefinition()
 */
class Component extends Filter\BaseComponent
{

    /**
     * A concrete Term model may match one or more availability configs. Eg, the admin may have selected 'all' terms.
     * We need to examine each concrete Term model to see if it matches an availability filter
     *
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions $optionDefaults
     * @return GenericCollection|Filter\Selection\Model[]
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();
        $isUserConfigurable  = $this->getPageFilter()->getUserConfigurable();

        // A term will be available if it matches any abstract key.
        // A term will be selected if any matching abstract term descriptions were selected

        foreach ($this->getDefinition()->getTerms() as $term) {

            if (!$isUserConfigurable || $this->isAvailable($term, $abstractOptions)) {
                $key                       = (string)$term->getId();
                $availableSelections[$key] = $this->createSelection($key, $this->isSelected($term, $abstractOptions, $roleType));
            }
        }
        return $availableSelections;
    }


    /**
     * Should the specified Term be displayed in list of available options for the user?
     *
     * @param Term\Model                                   $term
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @return bool
     */
    private function isAvailable(Term\Model $term, GenericCollection $abstractOptions)
    {
        $termFinish   = $term->getActualFinish();
        $currentTerm  = $this->getDefinition()->getCurrentTerm();
        $previousTerm = $this->getDefinition()->getPreviousTerm();

        if ($abstractOptions[Definition::KEY_ALL]->getAvailable()) {
            return true;
        }
        if ($abstractOptions[Definition::KEY_FUTURE]->getAvailable() && $term->getStart()->isFuture()) {
            return true;
        }
        if ($currentTerm && $abstractOptions[Definition::KEY_CURRENT]->getAvailable() && $currentTerm->getId() === $term->getId()) {
            return true;
        }
        if ($previousTerm && $abstractOptions[Definition::KEY_PREVIOUS]->getAvailable() && $previousTerm->getId() === $term->getId()) {
            return true;
        }
        if (!$termFinish->isNull() && $abstractOptions[Definition::KEY_PAST]->getAvailable() && $term->getActualFinish()->isPast()) {
            return true;
        }
        if ($abstractOptions[$term->getId()] && $abstractOptions[$term->getId()]->getAvailable()) {
            return true;
        }
        return false;
    }


    /**
     * Should the specified Term be selected in list of available options for the user's role?
     *
     * @param Term\Model                                              $term
     * @param GenericCollection|\Schoolbox\Filter\Page\Option\Model[] $abstractOptions
     * @param string                                                  $roleType
     * @return bool
     */
    private function isSelected(Term\Model $term, GenericCollection $abstractOptions, $roleType)
    {
        $termFinish   = $term->getActualFinish();
        $currentTerm  = $this->getDefinition()->getCurrentTerm();
        $previousTerm = $this->getDefinition()->getPreviousTerm();

        if ($abstractOptions->offsetExists(Definition::KEY_ALL)
            && $abstractOptions[Definition::KEY_ALL]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($abstractOptions->offsetExists(Definition::KEY_FUTURE) && $term->getStart()->isFuture()
            && $abstractOptions[Definition::KEY_FUTURE]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($currentTerm && $abstractOptions->offsetExists(Definition::KEY_CURRENT) && $currentTerm->getId() === $term->getId()
            && $abstractOptions[Definition::KEY_CURRENT]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($previousTerm && $abstractOptions->offsetExists(Definition::KEY_PREVIOUS) && $previousTerm->getId() === $term->getId()
            && $abstractOptions[Definition::KEY_PREVIOUS]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if (!$termFinish->isNull() && $abstractOptions->offsetExists(Definition::KEY_PAST) && $term->getActualFinish()->isPast()
            && $abstractOptions[Definition::KEY_PAST]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        if ($abstractOptions->offsetExists($term->getId())
            && $abstractOptions[$term->getId()]->getDefaults()[$roleType]->getSelected()
        ) {
            return true;
        }
        return false;
    }

}
