<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Filter\AbstractFilterComponent;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Filter\Option;

/**
 * @method Definition getDefinition()
 */
class Component extends AbstractFilterComponent
{

    /**
     * @return string
     */
    protected function getTemplateFile()
    {
        return 'term.twig';
    }


    /**
     * @return \Schoolbox\Filter\Selection\Model[]|GenericCollection
     * @todo break this into smaller private methods
     * @todo move common bits into abstract parent
     */
    private function getAvailableSelections()
    {
        // Get default options for role
        /** @var GenericCollection|Option\Model[] $optionDefaults */
        $optionDefaults = $this->getDefinition()->getOptionDefaults($this->getPage())->getOffset($this->getPage());

        $availableSelections = $this->getFilterSelectionMapper()->createCollection();

        // Is this term available?

        foreach ($this->getDefinition()->getTerms() as $term) {
            $termFinish = $term->getActualFinish();
            $available  = false;
            if ($optionDefaults->offsetExists(Definition::KEY_ALL)) {
                $available = true;
            } elseif ($optionDefaults->offsetExists(Definition::KEY_FUTURE) && $term->getStart()->isFuture()) {
                $available = true;
            } elseif ($optionDefaults->offsetExists(Definition::KEY_CURRENT) && $this->getDefinition()->getCurrentTerm() && $this->getDefinition()->getCurrentTerm()->getId() === $term->getId()) {
                $available = true;
            } elseif ($optionDefaults->offsetExists(Definition::KEY_PREVIOUS) && $this->getDefinition()->getPreviousTerm() && $this->getDefinition()->getPreviousTerm()->getId() === $term->getId()) {
                $available = true;
            } elseif (!$termFinish->isNull() && $optionDefaults->offsetExists(Definition::KEY_PAST) && $term->getActualFinish()->isPast()) {
                $available = true;
            } elseif ($optionDefaults->offsetExists($term->getId())) {
                $available = true;
            }

            if ($available) {
                $selection = $this->getFilterSelectionMapper()->createModel();
                $selection
                    ->setFilter($this->getDefinition()->getFilter())
                    ->setUser($this->getUser())
                    ->setPage($this->getPage())
                    ->setKey((string)$term->getId())
                    ->setSelected(false);
                $availableSelections[$selection->getKey()] = $selection;
            }
        }

        $defaultsPerKey = $this->getDefinition()->getFilterDefaultMapper()->createCollection();
        foreach ($optionDefaults as $key => $option) {
            foreach ($option->getDefaults() as $roleType => $default) {
                $defaultsPerKey[$key] = $default;
            }
        }


        // Overlay role-specific settings
        foreach ($availableSelections as $selection) {

            $term       = $this->getDefinition()->getTerms()[$selection->getKey()];
            $termStart  = $term->getStart();
            $termFinish = $term->getActualFinish();

            foreach ($defaultsPerKey as $key => $default) {
                if (!$default->isSelected()) {
                    continue;
                }
                if (Definition::KEY_ALL === $key) {
                    $selection->setSelected(true);
                } elseif (Definition::KEY_FUTURE === $key && $termStart->isFuture()) {
                    $selection->setSelected(true);
                } elseif (Definition::KEY_PAST === $key && !$termFinish->isNull() && $termFinish->isPast()) {
                    $selection->setSelected(true);
                } elseif (Definition::KEY_CURRENT === $key && $this->getDefinition()->getCurrentTerm() && $term->getId() === $this->getDefinition()->getCurrentTerm()->getId()) {
                    $selection->setSelected(true);
                } elseif (Definition::KEY_PREVIOUS === $key && $this->getDefinition()->getPreviousTerm() && $term->getId() === $this->getDefinition()->getPreviousTerm()->getId()) {
                    $selection->setSelected(true);
                } elseif ((string)$key === (string)$term->getId()) {
                    $selection->setSelected(true);
                }
            }
        }

        // Overlay user-specific settings
        foreach ($availableSelections as $key => $savedSelection) {
            if ($this->getSavedSelections()->offsetExists($key)) {
                $availableSelections[$key]
                    ->setId($savedSelection->getId())
                    ->setSelected($savedSelection->getSelected());
            }
        }

        return $availableSelections;
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function toContext()
    {
        return [
            'definition' => $this->getDefinition(),
            'selections' => $this->getAvailableSelections(),
        ];
    }
}
