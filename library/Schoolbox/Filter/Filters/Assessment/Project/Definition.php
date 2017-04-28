<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Project;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\GenericCollection;

/**
 * Displays a list of projects.
 *
 * Admin can configure whether to only show projects:
 * * class folders,
 * * group folders,
 * * imported to classes,
 * * imported to groups,
 * * in unit-specs
 */
class Definition extends AbstractFilterDefinition
{
    // Where does the project reside?
    const KEY_NONE               = 'none';                // assessment is not inside a project
    const KEY_ON_CLASS_FOLDER    = 'on_class_folder';     // created directly on a class folder
    const KEY_ON_GROUP_FOLDER    = 'on_group_folder';     // created directly on a group folder
    const KEY_ON_CLASS_UNIT_IMPL = 'on_class_unit_impl';  // imported onto class folder
    const KEY_ON_GROUP_UNIT_IMPL = 'on_group_unit_impl';  // imported onto group folder
    const KEY_ON_UNIT_SPEC       = 'on_unit_spec';        // on unit spec

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
        foreach ($pageOptions as $idx => $option) {
            // For now, no pages can have projects that reside in unitSpecs
            if (self::KEY_ON_UNIT_SPEC === $option->getKey()) {
                $pageOptions->offsetUnset($idx);
            }
        }
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_NONE,
            self::KEY_ON_CLASS_FOLDER,
            self::KEY_ON_GROUP_FOLDER,
            self::KEY_ON_CLASS_UNIT_IMPL,
            self::KEY_ON_GROUP_UNIT_IMPL,
            self::KEY_ON_UNIT_SPEC,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // Don't render on any page by default
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // No options checked
        return false;
    }

    public static function getQueryKey()
    {
        return 'p';
    }

    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        // For now, not available on any pages - purely used for internal query building
        return [
//            Option\Model::PAGE_PENDING_ASSESSMENTS,
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
            case self::KEY_NONE:
                return 'Not inside a project';
            case self::KEY_ON_CLASS_FOLDER:
                return 'Created on class';
            case self::KEY_ON_GROUP_FOLDER:
                return 'Created on group';
            case self::KEY_ON_CLASS_UNIT_IMPL:
                return 'In unit imported to class';
            case self::KEY_ON_GROUP_UNIT_IMPL:
                return 'In unit imported to group';
            case self::KEY_ON_UNIT_SPEC:
                return 'In unit specification';
        }
        throw new \InvalidArgumentException("Unknown Project filter key: $key");
    }
}
