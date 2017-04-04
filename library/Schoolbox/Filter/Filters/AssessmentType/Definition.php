<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option\Defaults as FilterDefault;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Task;


/**
 * Available options:
 * * Project
 * * Quiz
 * * DueWork
 * * Task
 *
 * Defaults to full set, but run-time options will enable controller to exclude individual items
 *
 */
class Definition extends AbstractFilterDefinition
{
    // TODO, consider indexing by string integers instead? '1', '2', '3', '4' ?
    const KEY_PROJECT = Project::class;
    const KEY_QUIZ = Quiz\Model::class;
    const KEY_DUE_WORK = DueWork\Model::class;
    const KEY_TASK = Task\Model::class;

    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_PROJECT,
            self::KEY_QUIZ,
            self::KEY_DUE_WORK,
            self::KEY_TASK
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getTemplateFile()
    {
        return 'assessmentType.twig';
    }


    /**
     * Options include Project, Quiz, etc
     *
     * Grouped by option.key, then indexed by option.page
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getOptions($pageFilter = null)
    {
        $validPages = $this->getPages();

        // Did we supply a page, and the page is not in the valid pages?
        if ($pageFilter && !in_array($pageFilter, $validPages, true)) {
            return new Collection();
        }

        // Get existing DB values, discarding any that belong to invalid pages
        /** @var Collection|GenericCollection[]|FilterOption\Model[][] $optionsPerPage */
        $optionsPerPage = $this->getFilterOptionMapper()->findByColumnList([
            'filter_id' => $this->getFilter()->getId(),
        ])->filter(function (FilterOption\Model $option) use ($pageFilter, $validPages) {

            // Are we filtering for page?
            if ($pageFilter && $option->getPage() !== $pageFilter) {
                return false;
            }

            return in_array($option->getPage(), $validPages, true);
        })->groupBy('page');


        // Ensure we have a set of options per valid page
        foreach ($validPages as $page) {

            // Are we filtering by page?
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }

            if (!$optionsPerPage->offsetExists($page)) {
                $optionsPerPage->setOffset($page, $this->getFilterOptionMapper()->createCollection());
            }
        }

        // Ensure all possible options present in graph
        /** @var GenericCollection|FilterOption\Model[] $pageOptions */
        foreach ($optionsPerPage as $page => $pageOptions) {
            $pageOptions->indexBy('key');

            // Ensure it contains 'all', 'current', etc
            foreach ($this->getCustomKeys() as $customAbstractKey) {
                if (!$pageOptions->offsetExists($customAbstractKey)) {
                    $option = $this->getFilterOptionMapper()->createModel();

                    $option
                        ->setFilter($this->getFilter())
                        ->setKey($customAbstractKey)
                        ->setPage($page)
                        ->setAvailable(true);
                    $pageOptions->setOffset($option->getKey(), $option);
                }
            }

            // Discard any options that refer to AssessmentTypes that no longer exist (eg if deleted or class name changed)
            foreach ($pageOptions as $filterOption) {
                // Does the option have a valid key ?
                if (!in_array($filterOption->getKey(), $this->getCustomKeys(), true)) {
                    $pageOptions->offsetUnset($filterOption->getKey());
                }
            }

            // Order the options.
            $this->sortFilterOptions($pageOptions)->indexBy('key');
        }

        // Convert to ordered by abstract key
        $optionsPerKey = new Collection();

        // Ensure page order is same as getPages()
        foreach ($validPages as $page) {
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }

            $pageOptions = $optionsPerPage[$page];
            foreach ($pageOptions as $abstractKey => $filterOption) {
                if (!$optionsPerKey->offsetExists($abstractKey)) {
                    $optionsPerKey->setOffset($abstractKey, $this->getFilterOptionMapper()->createCollection());
                }
                $optionsPerKey[$abstractKey][$page] = $filterOption;
            }
        }
        return $optionsPerKey;
    }


    /**
     * @param GenericCollection|FilterOption\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        return $options->sortByCallback(function (FilterOption\Model $left, FilterOption\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if (self::KEY_PROJECT === $leftKey) {
                return -1;
            }
            if (self::KEY_QUIZ === $leftKey) {
                return self::KEY_PROJECT === $right->getKey() ? +1 : -1;
            }
            if (self::KEY_DUE_WORK === $leftKey) {
                return in_array($rightKey, [self::KEY_PROJECT, self::KEY_QUIZ], true) ? +1 : -1;
            }
            return +1;
        });
    }


    /**
     * @inheritdoc
     */
    public function getOptionDefaults($pageFilter = null, $roleTypeFilter = null)
    {
        $optionsPerKey = $this->getOptions();

        // Get the ones which are available (regardless of whether they exist yet in DB)
        $availableOptions = $this->getFilterOptionMapper()->createCollection();
        foreach ($optionsPerKey as $key => $keyOptions) {
            /** @var FilterOption\Model[] $keyOptions */
            foreach ($keyOptions as $page => $option) {
                if ($option->getAvailable()) {
                    $availableOptions->add($option);
                }
            }
        }

        // Find any options that already exist in the DB
        /** @var FilterOption\Model[]|GenericCollection $existingOptions */
        $existingOptions = $availableOptions->filter(function (FilterOption\Model $option) {
            return null !== $option->getId();
        })->indexBy('id');

        // Load existing defaults for existing options, and group by option
        $defaultsPerExistingOption = $this->getFilterDefaultMapper()
                                          ->findByColumn('filter_option_id', $existingOptions->keys())
                                          ->groupByCallback(function (FilterDefault\Model $default) {
                                              return $default->getFilterOption()->getId();
                                          });

        // Add defaults to the options, and index defaults by roleType
        foreach ($defaultsPerExistingOption as $optionId => $defaults) {
            $existingOptions[$optionId]->setDefaults($defaults->indexBy('roleType'));
        }

        // Group the options by page, then index by key

        /** @var Collection|GenericCollection[]|FilterOption\Model[][] $optionsPerPage */
        $optionsPerPage = $availableOptions->groupBy('page');
        foreach ($optionsPerPage as $page => $options) {
            $options->indexBy('key');
        }

        // Ensure the pages are sorted
        $sortedOptionsPerPage = new Collection();
        foreach ($this->getPages() as $page) {
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }
            $sortedOptionsPerPage[$page] = $optionsPerPage[$page];
        }

        // Ensure all defaults are available for configuration
        foreach ($sortedOptionsPerPage as $page => $options) {
            /** @var GenericCollection|FilterOption\Model[] $options */
            foreach ($options as $key => $option) {
                $defaults = $option->getDefaults();
                // Does it contain a Default for all relevant roleTypes?
                foreach (FilterOption\Model::getRoleTypesForPage($page) as $roleType) {
                    if (!$defaults->offsetExists($roleType)) {
                        $default = $this->getFilterDefaultMapper()->createModel();
                        $default
                            ->setFilterOption($option)
                            ->setRoleType($roleType)
                            ->setSelected(false);
                        $defaults[$roleType] = $default;
                    }
                }
            }
        }

        return $sortedOptionsPerPage;
    }


    /**
     * Return the set of pages where this filter is displayed
     *
     * @return string[]
     */
    public function getPages()
    {
        return [
            FilterOption\Model::PAGE_PENDING_ASSESSMENTS,
            FilterOption\Model::PAGE_GRADES,
            FilterOption\Model::PAGE_MARK_BOOK,
            FilterOption\Model::PAGE_DUE_WORK_CALENDAR,
        ];
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys()
    {
        return self::$customKeys;
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        if (Definition::KEY_PROJECT === $key) {
            return 'Project';
        }
        if (Definition::KEY_QUIZ === $key) {
            return 'Quiz';
        }
        if (Definition::KEY_DUE_WORK === $key) {
            return 'Due Work';
        }
        if (Definition::KEY_TASK) {
            return 'Task';
        }
        return '';
    }

}
