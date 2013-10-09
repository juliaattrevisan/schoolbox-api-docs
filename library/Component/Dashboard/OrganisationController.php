<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_OrganisationController extends Component_Dashboard_ControllerAbstract {

    protected $group;

    public function __construct (Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage, Group $group) {
        parent::__construct($instance, $user, $sitepage);

        $this->group = $group;
    }

    public function execute () {

        return true;
    }

    public function renderTemplate ($templateHandle) {
		if ( !$this->loadTemplate($templateHandle, 'organisation') ) {
			return false;
		}

        $this->setBlock($templateHandle, 'extras_staff', 'extras_staff_block');

        if ( $this->group->isStaff() ) {
            $this->parse('extras_staff_block', 'extras_staff');
        }
        else {
            $this->setVar('extras_staff_block', '');
        }

        return true;
    }

}
