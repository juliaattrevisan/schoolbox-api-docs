<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\GenericCollection;

/**
 * Display a list of folders (either classes, groups or both) where the student has submitted IResponses or received IFeedback.
 *
 * In contrast to the TeacherFolder filter, this filter does not permit insertion of arbitrary folders.
 *
 * Assessments will match this filter if their ultimate/indirect folder matches that selected.
 *
 * eg, Assessment_A lives in project_B which lives in folder_C, if folder_C is selected, Assessment_A will be fetched.
 */
abstract class Definition extends AbstractDefinition
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
        // No options checked
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
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // Initial config is both classes and groups are available for all pages
        return true;
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
