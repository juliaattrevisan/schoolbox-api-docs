<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\GenericCollection;

/**
 * TODO write this better
 * A NullableContext filter is an assessment-context filter that shows a '(none)' option
 *
 * They have a coupling to the 'Folder' filter, as the 'Folder' is the ultimate context.
 *
 * We need to treat them specially because by default they do not show a full list
 *
 * If no options are selected, we can set the filter to NULL, knowing that the folder filer will then ensure no irrelevant
 * assessments are returned.
 *
 * If just the (none) option is selected, we return an empty array to indicate only assessments that do NOT live in this context
 * should be returned.
 *
 * If both the (none) and some other options are selected, then the idFinder needs to treat it as empty for other filters, but not empty for this filter.
 * This will ensure other sub-queries will run, but we will UNION those with the concrete items in this filter
 */
abstract class Definition extends AbstractFilterDefinition
{

    const KEY_NONE = 'none';  // match assessments that do NOT reside in this context

    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_NONE,
        ];
    }


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
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // All available by default
        return true;
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
     * Return the templates directory for the concrete filter definition's twig templates
     *
     * @return string
     * @throws \ReflectionException if this class does not exist. (kinda impossible... !)
     */
    protected function getTemplatesDir()
    {
        $class_info = new \ReflectionClass($this);
        $dir1       = basename(dirname($class_info->getFileName(), 3)) . DIRECTORY_SEPARATOR;
        $dir2       = basename(dirname($class_info->getFileName(), 2)) . DIRECTORY_SEPARATOR;
        $dir3       = basename(dirname($class_info->getFileName(), 1)) . DIRECTORY_SEPARATOR;
        return '@filterDefinitions' . DIRECTORY_SEPARATOR . $dir1 . $dir2 . $dir3 . 'templates';
    }
}
