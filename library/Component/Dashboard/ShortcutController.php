<?php
/**
 * A bunch of shortcut buttons to various locations, as displayed on the Dashboard.
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Component_Dashboard_ShortcutController extends Component_Dashboard_ControllerAbstract {
    protected $group;

	/**
	 * Constructor
	 *
	 * @param Component_Instance $instance
	 * @param User $user
	 * @param SitePage $sitepage
	 * @param Group $group
	 */
    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage, Group $group) {
        parent::__construct($instance, $user, $sitepage);

        $this->setGroup($group);
    }

	/**
	 * @return bool
	 */
    public function execute () {
        return true;
    }

	/**
	 * Renders shortcuts template
	 *
	 * @param $templateHandle
	 * @return bool
	 */
    public function renderTemplate ($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'shortcut')) {
            return false;
        }

        $this->setBlock($templateHandle, 'student_only', 'student_only_block');
        $this->setBlock($templateHandle, 'staff_only', 'staff_only_block');
		$this->setBlock($templateHandle, 'administrator_only', 'administrator_only_block');

        $this->setVar('staff_only_block', '');
        $this->setVar('student_only_block', '');
		$this->setVar('administrator_only_block', '');

		/* Parse administrator blocks
		 */
		if ($this->getGroup()->isSuperuser()) {
			$this->parse('administrator_only_block', 'administrator_only');
		}
		/* Parse staff blocks
		 */
        else if ($this->getGroup()->isStaff()) {
            $this->parse('staff_only_block', 'staff_only');
        }
		/* Parse student blocks
		 */
		else if ($this->getGroup()->isStudent()) {
            $this->parse('student_only_block', 'student_only');
        }

        return true;
    }

	/**
	 * Set group
	 *
	 * @param $group
	 */
	private function setGroup($group) {
		$this->group = $group;
	}

	/**
	 * Get group
	 *
	 * @return Group
	 */
	private function getGroup() {
		return $this->group;
	}
}
