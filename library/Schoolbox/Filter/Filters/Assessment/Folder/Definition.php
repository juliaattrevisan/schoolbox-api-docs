<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\GenericCollection;

/**
 * Display a list of folders (either classes, groups or both)
 * Whether classes, groups or both are available for selection is configured by the admin
 * The list of folders is generated at run-time for the current user and page
 */
class Definition extends AbstractFilterDefinition
{

    const KEY_CLASSES = 'classes';
    const KEY_GROUPS = 'groups';


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // noop - abstract options are hard-coded, concrete options are run-time
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // noop - abstract options are hard-coded, concrete options are run-time
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // Nothing is selected, meaning filter is not applied
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_CLASSES,
            self::KEY_GROUPS,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        return $options->sortByCallback(function (Option\Model $left, Option\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if ($leftKey === $rightKey) {
                return 0;
            }

            return self::KEY_CLASSES === $leftKey ? -1 : +1;
        });
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // Initial config is both classes and groups are available for all pages
        return true;
    }

    public static function getQueryKey()
    {
        return 'f';
    }

    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Option\Model::PAGE_PENDING_ASSESSMENTS,
            Option\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getAbstractKeys()
    {
        return $this->getCustomKeys();
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        switch ($key) {
            case self::KEY_CLASSES:
                return 'Classes';
            case self::KEY_GROUPS:
                return 'Groups';
        }
        throw new \InvalidArgumentException("Unknown FolderFilter key: $key");
    }
}
