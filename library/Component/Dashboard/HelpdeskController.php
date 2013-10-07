<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_HelpdeskController extends Component_Dashboard_ControllerAbstract {

    protected $helpdesk;

    protected $jobList;

    public function __construct (Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);

        /* TODO: Integrate this library in to the main library folder */
        require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/helpdesk/helpdesk.php';

        $this->helpdesk = new Helpdesk;
    }

    public function execute () {
        if ( HELPDESK_enable ) {
            $this->jobList = $this->helpdesk->openHelpdeskJobs($_SESSION['id']);

            return true;
        }
        else {
            return false;
        }
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'helpdesk') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'helpdesk_block', 'helpdesk_jobs');

        if ( is_array($this->jobList) ) {
            foreach ( $this->jobList as $job ) {
                $this->setVar('PROBLEM', ucfirst($job['problem']));
                $this->setVar('CREATED', $job['createdDate']);
                $this->setVar('STATUS', $job['status']);
                $this->setVar('ON_OFF', ++$j % 2 ? '' : 'dark');
                $this->parse('helpdesk_jobs', 'helpdesk_block', true);
            }

            return true;
        }
        else {
            return false;
        }
    }

}
