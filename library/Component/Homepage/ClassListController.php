<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Mapper\Exception as MapperException;

class Component_Homepage_ClassListController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'classList')) {
            return false;
        }
        $this->parseConfig();

        /* Get all the students belonging to this category code
         */
        $externalClassHandler = new External_Class();
        $allStudents          = $externalClassHandler->getStudents($this->getContext()->getCodeString());

        if (!is_array($allStudents)) {

            $this->setBlock($templateHandle, 'class_list', 'class_list_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

            $this->setVar('class_list_block', '');

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setBlock($templateHandle, 'person', 'person_block');
            $this->setBlock($templateHandle, 'student_email', 'student_email_block');
            $this->setBlock($templateHandle, 'parent', 'parent_block');
            $this->setBlock($templateHandle, 'parent_email', 'parent_email_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

            $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
            $this->setBlock('person', 'has_icon', 'has_icon_block');

            $this->setVar('empty_state_block', '');
            $this->setVar('has_thumbnail_block', '');
            $this->setVar('has_icon_block', '');
            $this->setVar('person_block', '');
            $this->setVar('student_email_block', '');
            $this->setVar('parent_email_block', '');

            /* Display options
             */
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

            $this->setVar(
                array(
                    'LIST_STYLE' => $displayOptions[$this->getConfig('displayPortraits')]['css'],
                )
            );

        /* Get all the students belonging to this category code
         */
        $externalClassHandler = new External_Class();
        $allStudents          = $externalClassHandler->getStudents($this->getContext()->getCodeString());

        $userMapper           = \Schoolbox\UserMapper::getInstance();

            $allStudentEmails = array();

        if (is_array($allStudents)) {
            $j = 0;
            foreach ($allStudents as $currentStudent) {
                try {
                    $user = $userMapper->findByExternalId($currentStudent['synergy_id']);

                    $email = $user->getEmail();

                        /* Accumulate this for a giant parent-based bcc
                            */
                        $synergyIds[] = $currentStudent['synergy_id'];

                        /* Agglutinate a giant bcc
                            */
                        if ($email) {
                            $allStudentEmails[] = $email;
                        }

                    $this->setVar(
                        array(
                            'PERSON_UID'         => $user->getId(),
                            'PERSON_EXTERNAL_ID' => $user->getExternalId(),
                            'PERSON_NAME'        => Scrub::text($user->getFullName()),
                            'PERSON_GROUP'       => Scrub::text(empty($user->getPositionTitle()) ? $user->getRole()->getName() : $user->getPositionTitle()),
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
                catch (MapperException $e) {
                    //noop
                }
            }


                $emailAllStudents = implode(',', $allStudentEmails);

            /* Student bulk email
              */
            if ($this->getContentPermission() > UserAccess::ACCESS_READ && \Cfg::get('mail_enable')) {
                $this->setVar(
                    array(
                        'REPLY_TO'           => $this->getUser()->getEmail(),
                        'EMAIL_ALL_STUDENTS' => $emailAllStudents,
                    )
                );

                    $this->parse('student_email_block', 'student_email');
                } else {
                    $this->setVar('student_email_block', '');
                }

                /* Parent bulk email
                  */
                if (count($synergyIds)
                    && $this->getContentPermission() > UserAccess::ACCESS_READ
                    && (Cfg::get('email_all_parents') == 1)
                    && \Cfg::get('mail_enable')
                ) {
                    $externalHandler = new External_User();
                    $allParents = $externalHandler->getParentEmails($synergyIds);

                    /* Uniquify emails
                    */
                    $emailAllParents = implode(', ', array_filter(array_unique($allParents)));

                $this->setVar(
                    array(
                        'REPLY_TO'          => $this->getUser()->getEmail(),
                        'EMAIL_ALL_PARENTS' => $emailAllParents,
                    )
                );

                    $this->parse('parent_email_block', 'parent_email');
                } else {
                    $this->setVar('parent_email_block', '');
                }
            } else {
                $this->setVar('student_block', '');
                $this->setVar('student_email_block', '');
            }
        }

        return true;
    }
}
