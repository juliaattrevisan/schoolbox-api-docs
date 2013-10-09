<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_ParentHelpdeskController extends Component_Dashboard_ControllerAbstract {

    protected $helpdesk;

    public function __construct (Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);

        require_once( 'modules/helpdesk/helpdesk.php' );

        $this->helpdesk = new Helpdesk;
    }

    public function execute () {
        if ( is_array($_SESSION['children']) ) {
            $this->children = $_SESSION['children'];

            return true;
        }
        else {
            return false;
        }
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'parentHelpdesk') ) {
            return false;
        }

        $this->setBlock($templateHandle, "student_helpdesk_block", "student_helpdesk");
        $this->setBlock("student_helpdesk_block", "student_job_block", "student_job");

            foreach ( $this->children as $child ) {
                $query = "SELECT title, firstname, lastname, year FROM `" . SQL_db . "`.user WHERE id = {$child['id']}";
                $user = DeprecatedDatabase::getInstance()->getRow($query);
                $this->setVar("STUDENT_ID", $child['id']);
                $this->setVar("STUDENT_NAME", $this->user->formatName($user, '{firstname} {lastname}'));
                $this->setVar("STUDENT_YEAR", $user['year']);

                $jobs = $this->helpdesk->openHelpdeskJobs($child['id']);
                if ( is_array($jobs) ) {
                    foreach ( $jobs as $job ) {
                        $this->setVar("PROBLEM", ucfirst($job['problem']));
                        $this->setVar("CREATED", $job['createdDate']);
                        $this->setVar("STATUS", $job['status']);
                        $this->parse("student_job", "student_job_block", true);
                    }
                }

                $this->parse("student_helpdesk", "student_helpdesk_block", true);
                $this->setVar("student_job", '');
            }

            return true;
    }

}
