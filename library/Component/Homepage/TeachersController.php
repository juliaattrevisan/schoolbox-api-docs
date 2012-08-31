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
        $this->setBlock($templateHandle, "person", "person_block");
        $this->setBlock('person', 'has_portrait', 'has_portrait_block');
        $this->setBlock('person', 'has_group', 'has_group_block');

        $this->setVar('displayportraits_block', '');
        $this->setVar('has_portrait_block', '');
        $this->setVar('has_group_block', '');
        $this->setVar('person_block', '');

        $displayOptions = array(
            'grid' => array(
                'name'  => 'Grid',
                'value' => 'grid',
                'css'   => 'thumb',
            ),
            'list' => array(
                'name'  => 'List',
                'value' => 'list',
                'css'   => 'bar',
            ),
        );

        if (($this->getContext('code') != '') && Cfg::get('external_enable')) {

            $externalClassHandler = new External_Class();
            $userHandler = new User();
            $groupHandler = new Group();

            $listSyns = $externalClassHandler->getTeacherName($this->getContext('code'));
            $staffGroups = $groupHandler->getStaffGroupTypeIds();

            if (is_array($listSyns)) {
                foreach($listSyns as $synid) {
                    $teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
                    $meta = $userHandler->getByID($teacher['uid']);

                    if (is_array($teacher)) {
                        $this->setVar(array(
                            'PERSON_UID' => $teacher['uid'],
                            'PERSON_NAME' => $teacher['firstname'] . ' ' . $teacher['lastname'],
                            'PERSON_EXTERNAL_ID' => $synid,
                            'PERSON_GROUP' => $meta['gname'],
                            'LIST_STYLE' => $displayOptions[$this->getConfig('displayPortraits')]['css'],
                        ));

                        /* Don't display images in list view
                       */
                        if ($this->getConfig('displayPortraits') == 'grid') {
                            $this->parse('has_portrait_block', 'has_portrait');
                            $this->parse('has_group_block', 'has_group');
                        }
                        else {
                            $this->setVar('has_portrait_block', '');
                            $this->setVar('has_group_block', '');
                        }

                        $this->parse('person_block', 'person', true);

                        /* Clear children after parsing out the parent
                       */
                        $this->setVar('has_portrait_block', '');
                        $this->setVar('has_group_block', '');
                    }
                }
            }

        }

        return true; // everything went according to plan

    }

}
