<?php

/**
 * Widgets
 *
 * A place to put shared floating functions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;

class Widgets
{
    /**
     * Outputs the visibility dropdown for this page.
     *
     * @param \SitePage $page
     * @param string $value The value of the dropdown to show.
     */
    public function outputVisibilityDropdown(\SitePage $page, $value)
    {
        $page->dropDown(
            'mark_visibility_drop',
            self::getAvailableOptions(),
            $value,
            'marktype',
            array(
                'name' => 'MARK_VISIBILITY_TEXT',
                'id'   => 'MARK_VISIBILITY_VALUE'
            )
        );
    }

    /**
     * Gets the available options for the visibility dropdown.
     *
     * @return array
     */
    public static function getAvailableOptions(): array
    {
        $configLoader  = ConfigLoader::getInstance();
        $allowStudents = (bool)$configLoader->get(ConfigMapper::NAME_MARK_VISIBILITY_ALLOW_STUDENTS);
        $allowParents  = (bool)$configLoader->get(ConfigMapper::NAME_MARK_VISIBILITY_ALLOW_PARENTS);
        $available     = \Schoolbox\Learning\Assessment\Feedback\Visibility::getAvailableVisibilities($allowStudents, $allowParents);
        return array_intersect_key(self::getOptions(), array_flip($available));
    }

    /**
     * Gets all options for the visibility dropdown.
     *
     * @return array
     */
    public static function getOptions(): array
    {
        return [
            IAssessable::MARK_VISIBILITY_PRIVATE => [
                'id'   => IAssessable::MARK_VISIBILITY_PRIVATE,
                'name' => html_entity_decode('&#xe66b;&nbsp;&nbsp;Staff Only'),
                'css'  => 'icon-locked',
            ],
            IAssessable::MARK_VISIBILITY_STUDENT => [
                'id'   => IAssessable::MARK_VISIBILITY_STUDENT,
                'name' => html_entity_decode('&#xe667;&nbsp;&nbsp;Staff and Students'),
                'css'  => 'icon-staff-students',
            ],
            IAssessable::MARK_VISIBILITY_PARENT => [
                'id'   => IAssessable::MARK_VISIBILITY_PARENT,
                'name' => html_entity_decode('&#xe666;&nbsp;&nbsp;Staff and Parents'),
                'css'  => 'icon-staff',
            ],
            IAssessable::MARK_VISIBILITY_PARENT_STUDENT => [
                'id'   => IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
                'name' => html_entity_decode('&#xe630;&nbsp;&nbsp;Staff, Students and their Parents'),
                'css'  => 'icon-group',
            ],
        ];
    }

    /**
     * Gets css style for a particular visibility
     *
     * @param int $id
     * @return mixed
     */
    public static function getCss($id)
    {
        $options = self::getOptions();

        foreach ($options as $vis) {
            if ($vis['id'] == $id) {
                return $vis['css'];
            }
        }
    }

    public static function getName($id)
    {
        $options = self::getOptions();

        foreach ($options as $vis) {
            if ($vis['id'] == $id) {
                return $vis['name'];
            }
        }
    }
}
