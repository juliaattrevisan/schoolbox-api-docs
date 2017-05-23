<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;

/**
 * Display a list of folders (either classes, groups or both) where the student has submitted IResponses or received IFeedback.
 *
 * In contrast to the TeacherFolder filter, this filter does not permit insertion of arbitrary folders.
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
//            Option\Model::PAGE_GRADES,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/Folder/StudentInteractions/templates';
    }
}
