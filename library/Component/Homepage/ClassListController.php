<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ClassListController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Group
     */
    protected $groupHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();
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

        $this->setBlock($templateHandle, 'person', 'person_block');
        $this->setBlock($templateHandle, 'student_email', 'student_email_block');
        $this->setBlock($templateHandle, 'parent', 'parent_block');
        $this->setBlock($templateHandle, 'parent_email', 'parent_email_block');

        $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
        $this->setBlock('person', 'has_icon', 'has_icon_block');

        $this->setVar('has_thumbnail_block', '');
        $this->setVar('has_icon_block', '');
        $this->setVar('person_block', '');
        $this->setVar('student_email_block', '');
        $this->setVar('parent_email_block', '');


        /* Display options
         */
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

        $this->setVar(
            array(
                'LIST_STYLE' => $displayOptions[$this->getConfig('displayPortraits')]['css'],
            )
        );

        /* Get all the students belonging to this category code
         */
        $externalClassHandler = new External_Class();
        $allStudents          = $externalClassHandler->getStudents($this->getContext()->getCodeString());

        $userHandler  = new User();
        $emailHandler = new Email();

        $allStudentEmails = array();

        if (is_array($allStudents)) {
            $j = 0;
            foreach ($allStudents as $currentStudent) {
                $user = $userHandler->getBySynergy($currentStudent['synergy_id']);

                /* Get the schoolbox user and process it if it exists
                   */
                if (is_array($user)) {
                    $name  = $userHandler->formatName($user);
                    $email = $emailHandler->formatEmailFromArray($user);

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
                            'ON_OFF'             => ++$j % 2 ? '' : 'dark',
                            'PERSON_UID'         => $user['uid'],
                            'PERSON_EXTERNAL_ID' => $user['synergy_id'],
                            'PERSON_NAME'        => $name,
                            'PERSON_GROUP'       => Scrub::text($user['gname']),
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

            $emailAllStudents = implode(',', $allStudentEmails);

            /* Student bulk email
              */
            if ($this->permission > READ_ACCESS && \Cfg::get('mail_enable')) {
                $this->setVar(
                    array(
                        'REPLY_TO'           => $emailHandler->formatEmailFromArray($_SESSION),
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
                && $this->permission > READ_ACCESS
                && (Cfg::get('email_all_parents') == 1)
                && \Cfg::get('mail_enable')
            ) {
                $externalHandler = new External_User();
                $allParents      = $externalHandler->getParentEmails($synergyIds);

                /* Uniquify emails
				*/
                $emailAllParents = implode(', ', array_filter(array_unique($allParents)));

                $this->setVar(
                    array(
                        'REPLY_TO'          => $emailHandler->formatEmailFromArray($_SESSION),
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

        return true;
    }
}
