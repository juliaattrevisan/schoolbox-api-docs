<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use Schoolbox\Filter\Option;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;

/**
 * Display a list of folders (either classes, groups or both)
 * Whether classes, groups or both are available for selection is configured by the admin
 * The list of folders is generated at run-time for the current user and page
 *
 * Assessments will match this filter if their ultimate/indirect folder matches that selected.
 *
 * eg, Assessment_A lives in project_B which lives in folder_C, if folder_C is selected, Assessment_A will be fetched.
 */
class Definition extends FolderFilter\Definition
{

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
    protected function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/Folder/TeacherInvolved/templates';
    }
}
