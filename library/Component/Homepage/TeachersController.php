<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_TeachersController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'teachers')) {
            return false;
        }
        $this->parseConfig();

        // gets teachers involved in subject, outputs links to their addressbook page
        $this->setBlock($templateHandle, "person", "person_block");
        $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
        $this->setBlock('person', 'has_icon', 'has_icon_block');

        $this->setVar('has_thumbnail_block', '');
        $this->setVar('has_icon_block', '');
        $this->setVar('person_block', '');

        $displayOptions = array(
            'grid' => array(
                'name'  => 'Grid',
                'value' => 'grid',
                'css'   => 'grid',
            ),
            'list' => array(
                'name'  => 'List',
                'value' => 'list',
                'css'   => '',
            ),
        );
        $display        = $this->getConfig('displayPortraits') ? $this->getConfig('displayPortraits') : 'grid';
        $this->setVar(
            array(
                'LIST_STYLE' => $displayOptions[$display]['css'],
            )
        );

        if (($this->getContext()->getCodeString() != '') && Cfg::get('external_enable')) {
            $externalClassHandler = new External_Class();
            $userHandler          = new User();
            $groupHandler         = new Group();

            $listSyns    = $externalClassHandler->getTeacherName($this->getContext()->getCodeString());
            $staffGroups = $groupHandler->getStaffGroupTypeIds();

            if (is_array($listSyns)) {
                $j = 0;
                foreach ($listSyns as $synid) {
                    $teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
                    $meta    = $userHandler->getByID($teacher['uid']);

                    if (is_array($teacher)) {
                        $this->setVar(
                            array(
                                'ON_OFF'             => ++$j % 2 ? '' : 'dark',
                                'PERSON_UID'         => $teacher['uid'],
                                'PERSON_NAME'        => $teacher['firstname'] . ' ' . $teacher['lastname'],
                                'PERSON_EXTERNAL_ID' => $synid,
                                'PERSON_GROUP'       => Scrub::text($meta['gname']),
                            )
                        );

                        /* Don't display images in list view
                         */
                        if ($this->getConfig('displayPortraits') == 'grid') {
                            $this->setVar(array(
                                'GRID_VIEW_CLASSES' => 'medium-block-grid-2'
                            ));

                            $this->parse('has_thumbnail_block', 'has_thumbnail');
                            $this->setVar('has_icon_block', '');
                        } else {
                            $this->setVar(array(
                                'GRID_VIEW_CLASSES' => ''
                            ));

                            $this->setVar('has_thumbnail_block', '');
                            $this->parse('has_icon_block', 'has_icon');
                        }

                        $this->parse('person_block', 'person', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->setVar('has_thumbnail_block', '');
                        $this->setVar('has_icon_block', '');
                    }
                }
            }
        }

        return true; // everything went according to plan
    }
}
