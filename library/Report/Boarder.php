<?php
/**
 * Report_Boarder
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */
class Report_Boarder extends Report_Parent {

	/**
	 * Constructor
	 *
	 * @param $page
	 */
	public function __construct($page) {
		$this->setGroupHandler(new Group());

		if (!$this->checkPermissions()) {
			error(19);
		}
		else {
			$this->setPage($page);

			$this->setExternalUserHandler(new External_User());

			$this->setup();

			$this->setAllRecords($this->getExternalUserHandler()->getStudentBoarderContactListExtended());
		}
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, 'modules/report/boarders.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'student', 'student_block');
		$this->getPage()->setTitle('Parent Contact info');
		$this->getPage()->setBlock('student', 'contact', 'contact_block');
		$this->getPage()->setBlock('contact', 'spouse', 'spouse_block');
		$this->getPage()->setBlock('contact', 'primary', 'primary_block');

        $this->getPage()->setBlock('student', 'public', 'public_block');
        $this->getPage()->setBlock('student', 'private', 'private_block');

		$this->getPage()->setVar(array(
			'student_block' => '',
			'contact_block' => '',
            'public_block' => '',
            'private_block' => '',
		));
	}

    /**
     * Check permissions. By default only parents and superusers can view this page
     *
     * @return bool
     */
    protected function checkPermissions(): bool {
        if ($this->getGroupHandler()->isParent() || $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff()) {
            return true;
        }

        return false;
    }

    public function export(Report_Exporter $exporter, $format = 'csv') {
        // function stub to appease interpreter
        // can be implemented if export required.
    }
}



