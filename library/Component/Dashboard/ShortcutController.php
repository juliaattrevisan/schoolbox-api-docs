<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_ShortcutController extends Component_ControllerAbstract {

    protected $group;

    public function __construct (Component_Instance $instance, User $user, Template $template, Group $group) {
        parent::__construct($instance, $user, $template);

        $this->group = $group;
    }

    public function execute () {
        return true;
    }

    public function render ($templateHandle) {
        $this->setBlock($templateHandle, 'student_only', 'student_only_block');
        $this->setBlock($templateHandle, 'staff_only', 'staff_only_block');
        
        $this->setVar('staff_only_block', '');
        $this->setVar('student_only_block', '');

        if ( $this->group->isStaff() ) {
            $this->parse('staff_only_block', 'staff_only');
        }
        else if ( $this->group->isStudent() ) {
            $this->parse('student_only_block', 'student_only');
        }

        return true;
    }

}