<?php
/**
 * Widgets
 *
 * A place to put shared floating functions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;
use Schoolbox\Assessment\Component\SubmissionBox;

class Widgets {

    /**
     * Outputs the visibility dropdown for this page.
     *
     * @param \SitePage $page
     * @param int $value The value of the dropdown to show.
     */
    public function outputVisibilityDropdown(\SitePage $page, $value) {
        $page->dropDown(
            'mark_visibility_drop',
            $this->getAvailableOptions(),
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
    public static function getAvailableOptions() {
        $options = self::getOptions();
        switch (\Cfg::get('submission_visibility_max')) {
            case SubmissionBox::MARK_VISIBILITY_PRIVATE:
                // drop MARK_VISIBILITY_PARENT_STUDENT, MARK_VISIBILITY_STUDENT
                array_pop($options);
                array_pop($options);
                break;
            case SubmissionBox::MARK_VISIBILITY_STUDENT:
                // drop MARK_VISIBILITY_PARENT_STUDENT
                array_pop($options);
                break;
            case SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT:
            default:
                // drop nothing
                break;
        }
        return $options;
    }

    /**
     * Gets all options for the visibility dropdown.
     *
     * @return array
     */
    static public function getOptions() {
        $options = array(
            array(
                'id'   => SubmissionBox::MARK_VISIBILITY_PRIVATE,
                'name' => html_entity_decode('&#xe66b;&nbsp;&nbsp;Staff Only'),
                'css' => 'icon-locked',
            ),
            array(
                'id'   => SubmissionBox::MARK_VISIBILITY_STUDENT,
                'name' => html_entity_decode('&#xe667;&nbsp;&nbsp;Staff and Students'),
                'css' => 'icon-staff-students',
            ),
            array(
                'id'   => SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
                'name' => html_entity_decode('&#xe666;&nbsp;&nbsp;Staff, Students and their Parents'),
                'css' => 'icon-staff',
            ),
        );

        return $options;
    }

    /**
     * Gets css style for a particular visiblity
     *
     * @param $id
     * @return mixed
     */
    static public function getCss($id) {
        $options = self::getOptions();

        foreach ($options as $vis) {
            if ($vis['id'] == $id) {
                return $vis['css'];
            }
        }
    }

    static public function getName($id) {
        $options = self::getOptions();

        foreach($options as $vis) {
            if($vis['id'] == $id) {
                return $vis['name'];
            }
        }
    }
}