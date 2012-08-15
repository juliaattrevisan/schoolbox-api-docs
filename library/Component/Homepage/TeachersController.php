<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_TeachersController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'teachers')) {
            return false;
        }
        $this->parseConfig();

        // gets teachers involved in subject, outputs links to their addressbook page
        $this->setBlock($templateHandle, "staff_teachers", "staff_teacher_block");
        $this->setVar('staff_teacher_block', '');

        if (($this->getContext('code') != '') && Cfg::get('external_enable')) {

            $externalClassHandler = new External_Class();
            $userHandler = new User();
            $groupHandler = new Group();

            $listSyns = $externalClassHandler->getTeacherName($this->getContext('code'));
            $staffGroups = $groupHandler->getStaffGroupTypeIds();

            if (is_array($listSyns)) {
                $j = 0;
                foreach($listSyns as $synid) {
                    $teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);

                    if (is_array($teacher)) {
                        $name = $userHandler->formatName($teacher);

                        $this->setVar('MEMBER_NAME', "<a href=\"/search/user/{$teacher['uid']}\">{$name}</a>");
                        $this->parse("staff_teacher_block", "staff_teachers", true);
                    }

                    $this->setVar('ON_OFF', ++$j % 2 ? '' : 'dark');
                }
            }
        }

        return true; // everything went according to plan

    }

}
