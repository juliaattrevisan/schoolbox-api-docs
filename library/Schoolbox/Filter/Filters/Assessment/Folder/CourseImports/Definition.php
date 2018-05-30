<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\CourseImports;

use Schoolbox\Filter\Page;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;

/**
 * Displays a list of folders where any units specified in GET params have been imported to
 *
 * The list of folders is generated at run-time for the units specified in the GET params
 *
 * Assessments will match this filter if their ultimate/indirect folder matches that selected.
 *
 * eg, Assessment_A lives in project_B which lives in folder_C, if folder_C is selected, Assessment_A will be fetched.
 */
class Definition extends FolderFilter\Definition
{

    // The name to expose in the Admin UI
    protected const NAME = 'Folders';

    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Page\Model::PAGE_COURSE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/Folder/CourseImports/templates';
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        throw new \InvalidArgumentException("Unknown FolderFilter key: $key");
    }
}
