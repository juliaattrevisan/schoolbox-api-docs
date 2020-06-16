<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;

class Component_Homepage_TeachersController extends Component_Homepage_ControllerAbstract
{
    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        $user = $this->getUser();

        if (!parent::loadTemplate($templateHandle, 'teachers')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_teachers', 'has_teachers_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $this->setVar('FOLDER_ID', $this->getSource()->getFolder()->getId());

        $listSyns = null;

        if (($this->getContext()->getCodeString() != '') && $this->getConfigLoader()->get('external_enable')) {
            $externalClassHandler = new External_Class();
            $listSyns = $externalClassHandler->getTeachers($this->getContext()->getCodeString());
        }

        if (empty($listSyns)) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_is_group', 'empty_is_group_block');
            $this->setBlock('empty_state', 'empty_code', 'empty_code_block');
            $this->setBlock('empty_state', 'empty_code_admin', 'empty_code_admin_block');
            $this->setBlock('empty_state', 'empty_students', 'empty_students_block');
            $this->setBlock('empty_state', 'empty_students_admin', 'empty_students_admin_block');

            $this->setVar([
                'has_teachers_block' => '',
                'empty_read_block' => '',
                'empty_is_group_block' => '',
                'empty_code_block' => '',
                'empty_code_admin_block' => '',
                'empty_students_block' => '',
                'empty_students_admin_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } elseif ($this->getSource()->getFolder()->getCodes()->count() == 0 and !$this->getSource()->getFolder()->isGroup()) {

                if ($this->getUser()->isSuperuser()) {
                    $this->parse('empty_code_admin_block', 'empty_code_admin');
                } else {
                    $this->parse('empty_code_block', 'empty_code');
                }

            } elseif ($this->getSource()->getFolder()->getCodes()->count() == 0 and $this->getSource()->getFolder()->isGroup()) {
                $this->parse('empty_is_group_block', 'empty_is_group');
            } else {

                if ($this->getUser()->isSuperuser()) {
                    $this->parse('empty_students_admin_block', 'empty_students_admin');
                } else {
                    $this->parse('empty_students_block', 'empty_students');
                }
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            // gets teachers involved in subject, outputs links to their addressbook page
            $this->setBlock('has_teachers', "person", "person_block");
            $this->setBlock('person', 'photo', 'photo_block');

            $this->setVar([
                'empty_state_block' => '',
                'has_thumbnail_block' => '',
                'photo_block' => '',
                'person_block' => '',
            ]);

            $displayOptions = array(
                'grid' => array(
                    'name' => 'Grid',
                    'value' => 'grid',
                    'css' => 'grid',
                ),
                'list' => array(
                    'name' => 'List',
                    'value' => 'list',
                    'css' => '',
                ),
            );

            $display = $this->getConfig('displayPortraits') ? $this->getConfig('displayPortraits') : 'grid';

            $this->setVar([
                'LIST_STYLE' => $displayOptions[$display]['css'],
            ]);

            if (($this->getContext()->getCodeString() != '') && $this->getConfigLoader()->get('external_enable')) {
                $externalClassHandler = new External_Class();
                $userMapper = \Schoolbox\UserMapper::getInstance();
                $staffPhotoVisibility = $this->getConfigLoader()->get('protect_teacher_photo');

                // If the config flag is enabled:
                // restrict students in this class to only seeing the teacher(s)
                // of the class they are actually enrolled in.
                $classCodeString    = $this->getContext()->getCodeString();
                $classCodesRetained = [];
                if ($user->getRole()->isStudent()
                    && $this->getConfigLoader()->get('student_teacher_class_visibility')
                ) {

                    $classCodesRetained = [];
                    $classCodesComplete = explode(",", $classCodeString);

                    foreach ($user->getSubjectCollection() as $subjectCurrent) {
                        if (in_array($subjectCurrent['code'], $classCodesComplete)) {
                            $classCodesRetained[] = $subjectCurrent['code'];
                        }
                    }
                }

                $listSyns = $externalClassHandler->getTeachers(
                    !empty($classCodesRetained)
                        ? implode(',', $classCodesRetained)
                        : $classCodeString
                );

                if (is_array($listSyns)) {
                    foreach ($listSyns as $synid) {
                        try {
                            $teacher = $userMapper->findByExternalId($synid, Role::STAFF);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            // Ok to silently ignore. Teacher exists in SIS by not in SB
                            continue;
                        }

                        $this->setVar([
                            'PERSON_UID'         => $teacher->getId(),
                            'PERSON_NAME'        => Scrub::text($teacher->getFullName()),
                            'PERSON_EXTERNAL_ID' => $synid,
                            'PERSON_GROUP'       => Scrub::text($teacher->getPreferredTitle()),
                        ]);

                        /* Don't display images in list view
                         */
                        if ($this->getConfig('displayPortraits') == 'grid') {
                            $this->setVar([
                                'GRID_VIEW_CLASSES' => 'medium-block-grid-2'
                            ]);
                        } else {
                            $this->setVar([
                                'GRID_VIEW_CLASSES' => ''
                            ]);
                        }

                        if ($teacher->getRole()->isStaff()) {
                            if ($staffPhotoVisibility == 2 && $this->getUser()->getRole()->isStudent()) {
                                $this->setVar('photo_block', '');
                            } elseif ($staffPhotoVisibility == 1 && !$this->getUser()->getRole()->isStaff()) {
                                $this->setVar('photo_block', '');
                            } else {
                                $this->parse('photo_block', 'photo');
                            }
                        }
                        else {
                            $this->parse('photo_block', 'photo');
                        }

                        $this->parse('person_block', 'person', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->setVar('photo_block', '');
                    }
                }
            }

            $this->parse('has_teachers_block', 'has_teachers');
        }

        return true; // everything went according to plan
    }
}
