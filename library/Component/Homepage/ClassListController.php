<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ClassListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    protected $groupHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'classList')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'person', 'person_block');
        $this->setBlock($templateHandle, 'student_email', 'student_email_block');
        $this->setBlock($templateHandle, 'parent', 'parent_block');
        $this->setBlock($templateHandle, 'parent_email', 'parent_email_block');

        $this->setBlock('person', 'has_portrait', 'has_portrait_block');
        $this->setBlock('person', 'has_group', 'has_group_block');

        $this->setVar('displayportraits_block', '');
        $this->setVar('has_portrait_block', '');
        $this->setVar('has_group_block', '');
        $this->setVar('person_block', '');
        $this->setVar('student_email_block', '');
        $this->setVar('parent_email_block', '');


        /* Display options
         */
        $displayOptions = array(
            'grid' => array(
                'name' => 'Grid',
                'value' => 'grid',
                'css' => 'thumb',
            ),
            'list' => array(
                'name' => 'List',
                'value' => 'list',
                'css' => 'bar',
            ),
        );

        /* Get all the students belonging to this category code
         */
        $externalClassHandler = new External_Class();
        $allStudents = $externalClassHandler->getStudents($this->getContext('code'));

        $userHandler = new User();
        $emailHandler = new Email();

        $allStudentEmails = array();

        if (is_array($allStudents)) {
            foreach ($allStudents as $currentStudent) {
                $user = $userHandler->getBySynergy($currentStudent['synergy_id']);

                /* Get the schoolbox user and process it if it exists
                   */
                if (is_array($user)) {
                    $name = $userHandler->formatName($user);
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
                            'PERSON_UID' => $user['uid'],
                            'PERSON_EXTERNAL_ID' => $user['synergy_id'],
                            'PERSON_NAME' => $name,
                            'PERSON_GROUP' => $user['gname'],
                            'LIST_STYLE' => $displayOptions[$this->getConfig('displayPortraits')]['css'],
                        )
                    );

                    /* Don't display images in list view
                        */
                    if ($this->getConfig('displayPortraits') == 'grid') {
                        $this->parse('has_portrait_block', 'has_portrait');
                        $this->parse('has_group_block', 'has_group');
                    } else {
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

            $emailAllStudents = implode(',', $allStudentEmails);

            /* Student bulk email
              */
            if ($this->groupHandler->isStaff()) {
                $this->setVar(
                    array(
                        'REPLY_TO' => $emailHandler->formatEmailFromArray($_SESSION),
                        'EMAIL_ALL_STUDENTS' => $emailAllStudents,
                    )
                );

                $this->parse('student_email_block', 'student_email');
            } else {
                $this->setVar('student_email_block', '');
            }

            /* Parent bulk email
              */
            if (count($synergyIds) && $this->groupHandler->isStaff() && Cfg::get('email_all_parents') == 1) {
                $allParentEmails = array();
                $allSynergyIds = implode(',', $synergyIds);

                $externalHandler = new External_User();
                $allParents = $externalHandler->getParentsOf($allSynergyIds);

                if (is_array($allParents) && count($allParents)) {
                    foreach ($allParents as $currentParent) {
                        $email = trim($currentParent['StudentContactDefaultEmail']);

                        if (strlen($email)) {
                            $allParentEmails[] = $email;
                        }
                    }

                    /* Uniquify emails
                        */
                    $emailAllParents = implode(', ', array_filter(array_unique($allParentEmails)));
                }

                $this->setVar(
                    array(
                        'REPLY_TO' => $emailHandler->formatEmailFromArray($_SESSION),
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
