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
            $userMapper           = \Schoolbox\UserMapper::getInstance();

            $listSyns    = $externalClassHandler->getTeacherName($this->getContext()->getCodeString());

            if (is_array($listSyns)) {
                $j = 0;
                foreach ($listSyns as $synid) {

                    try {
                        $teacher = $userMapper->findByExternalId($synid);

                        if($teacher->getRole()->isStaff()) {
                            $this->setVar(
                                array(
                                    'PERSON_UID'         => $teacher->getId(),
                                    'PERSON_NAME'        => Scrub::text($teacher->getFullName()),
                                    'PERSON_EXTERNAL_ID' => $synid,
                                    'PERSON_GROUP'       => Scrub::text($teacher->getRole()->getName()),
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
                    } catch  (\Schoolbox\Mapper\Exception $e) {
                        //noop
                    }
                }
            }
        }

        return true; // everything went according to plan
    }
}
