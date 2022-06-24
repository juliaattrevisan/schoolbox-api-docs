<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Mail\Mailer;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Component_Homepage_ClassListController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute(): bool
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable()) {
            return $this->hasContextWriteAccess();
        }

        return true;
    }


    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return boolean
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function renderTemplate($templateHandle): bool
    {
        if (!parent::loadTemplate($templateHandle, 'classList')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_students', 'has_students_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $this->setVar('FOLDER_ID', $this->getSource()->getFolder()->getId());

        /* Get all the students belonging to this category code
         */
        $externalClassHandler = new External_Class();
        $allStudents          = $externalClassHandler->getStudents($this->getContext()->getCodeString());

        if (!count($allStudents)) {
            $this->setBlock('empty_state', 'empty_is_group', 'empty_is_group_block');
            $this->setBlock('empty_state', 'empty_code', 'empty_code_block');
            $this->setBlock('empty_state', 'empty_code_admin', 'empty_code_admin_block');
            $this->setBlock('empty_state', 'empty_students', 'empty_students_block');
            $this->setBlock('empty_state', 'empty_students_admin', 'empty_students_admin_block');

            $this->setVar(
                [
                    'has_students_block'         => '',
                    'empty_is_group_block'       => '',
                    'empty_code_block'           => '',
                    'empty_code_admin_block'     => '',
                    'empty_students_block'       => '',
                    'empty_students_admin_block' => '',
                ]
            );

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            } elseif ($this->getSource()->getFolder()->getCodes()->count() == 0 && !$this->getSource()->getFolder()->isGroup()) {
                if ($this->getUser()->isSuperuser()) {
                    $this->parse('empty_code_admin_block', 'empty_code_admin');
                } else {
                    $this->parse('empty_code_block', 'empty_code');
                }
            } elseif ($this->getSource()->getFolder()->getCodes()->count() == 0 && $this->getSource()->getFolder()->isGroup()) {
                $this->parse('empty_is_group_block', 'empty_is_group');
            } elseif ($this->getUser()->isSuperuser()) {
                $this->parse('empty_students_admin_block', 'empty_students_admin');
            } else {
                $this->parse('empty_students_block', 'empty_students');
            }

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_students', 'person', 'person_block');
            $this->setBlock('has_students', 'student_email', 'student_email_block');
            $this->setBlock('has_students', 'parent', 'parent_block');
            $this->setBlock('has_students', 'parent_email', 'parent_email_block');
            $this->setBlock('person', 'photo', 'photo_block');

            $this->setVar(
                [
                    'empty_state_block'   => '',
                    'person_block'        => '',
                    'student_email_block' => '',
                    'parent_email_block'  => '',
                ]
            );

            /* Display options
             */
            $displayOptions = [
                'grid' => [
                    'name'  => 'Grid',
                    'value' => 'grid',
                    'css'   => 'grid',
                ],
                'list' => [
                    'name'  => 'List',
                    'value' => 'list',
                    'css'   => '',
                ],
            ];

            $this->setVar(
                [
                    'student_block'       => '',
                    'student_email_block' => '',
                    'LIST_STYLE'          => $displayOptions[$this->getConfig('displayPortraits')]['css'],
                ]
            );

            /* Get all the students belonging to this category code
             */
            $userMapper       = UserMapper::getInstance();
            $allStudentEmails = [];
            $synergyIds       = [];

            $studentPhotoHidden   = $this->getConfigLoader()->get('protect_student_photo');
            $staffPhotoVisibility = $this->getConfigLoader()->get('protect_teacher_photo');

            $viewer = $this->getUser();

            foreach ($allStudents as $currentStudent) {
                try {
                    $user = $userMapper->findByExternalId($currentStudent['synergy_id'], Role::STUDENT);
                } catch (MapperException $e) {
                    // Ok to silently ignore. This just means the SIS has students who are not in SB
                    continue;
                }

                /* Accumulate this for a giant parent-based bcc
                 */
                $synergyIds[] = $currentStudent['synergy_id'];

                /* Agglutinate a giant bcc
                 */
                if ($user->getEmail()) {
                    $allStudentEmails[] = Scrub::text($user->getFormattedEmailDisplay());
                }

                $this->setVar(
                    [
                        'PERSON_UID'         => $user->getId(),
                        'PERSON_EXTERNAL_ID' => $user->getExternalId(),
                        'PERSON_NAME'        => Scrub::text($user->getFullName()),
                        'PERSON_GROUP'       => Scrub::text($user->getPreferredTitle()),
                    ]
                );

                /* Don't display images in list view
                 */
                if ($this->getConfig('displayPortraits') === 'grid') {
                    $this->setVar('GRID_VIEW_CLASSES', 'medium-block-grid-2');
                } else {
                    $this->setVar('GRID_VIEW_CLASSES', '');
                }

                if ($user->getRole()->isStudent()) {
                    //Hide Student Photo
                    if ($viewer->getRole()->isStudent() && $studentPhotoHidden) {
                        $this->setVar('photo_block', '');
                    } elseif ($viewer->getRole()->isParent() && !$viewer->isParentOf($user->loadParents())) {
                        $this->setVar('photo_block', '');
                    } else {
                        $this->parse('photo_block', 'photo');
                    }
                } elseif ($user->getRole()->isStaff()) {
                    //Hide Staff Photo
                    if ($staffPhotoVisibility == 2 && $viewer->getRole()->isStudent()) {
                        $this->setVar('photo_block', '');
                    } elseif ($staffPhotoVisibility == 1 && !$viewer->getRole()->isStaff()) {
                        $this->setVar('photo_block', '');
                    } else {
                        $this->parse('photo_block', 'photo');
                    }
                } else {
                    $this->parse('photo_block', 'photo');
                }

                $this->parse('person_block', 'person', true);
            }

            /* Student bulk email
             */
            if (
                $this->getContentPermission() > UserAccess::ACCESS_READ
                && $this->getConfigLoader()->get('mail_enable')
                && $this->getUser()->hasEmail()
            ) {
                $this->setVar(
                    [
                        'REPLY_TO'           => $this->getUser()->getEmail(),
                        'EMAIL_ALL_STUDENTS' => implode(Mailer::implodeGlue(), $allStudentEmails),
                    ]
                );

                $this->parse('student_email_block', 'student_email');
            } else {
                $this->setVar('student_email_block', '');
            }

            /* Parent bulk email
             */
            if (
                count($synergyIds)
                && $this->getContentPermission() > UserAccess::ACCESS_READ
                && $this->getConfigLoader()->get('email_all_parents')
                && $this->getConfigLoader()->get('mail_enable')
                && $this->getUser()->hasEmail()
            ) {
                $externalHandler = new External_User();
                $allParents      = $externalHandler->getParentEmails($synergyIds);
                $emailAllParents = '';

                if (\is_array($allParents) && \count($allParents) > 0) {
                    // Uniqueify, cleanup empty string emails and merge to string
                    $emailAllParents = implode(Mailer::implodeGlue(), $allParents);
                }

                $this->setVar(
                    [
                        'REPLY_TO'          => $this->getUser()->getEmail(),
                        'EMAIL_ALL_PARENTS' => $emailAllParents,
                    ]
                );

                $this->parse('parent_email_block', 'parent_email');
            } else {
                $this->setVar('parent_email_block', '');
            }

            $this->parse('has_students_block', 'has_students');
        }

        return true;
    }
}
