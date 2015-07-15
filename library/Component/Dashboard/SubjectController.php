<?php
/**
 * Component for outputting a student's subject list.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Dashboard_SubjectController extends Component_Dashboard_ControllerAbstract {

    protected $subject_list;

    protected $componentName = 'My Subjects';

    /**
     * Business logic to collate data ready for output
     *
     * @return bool
     */
    public function execute() {
        $this->subject_list = $_SESSION['subjects'];
//        usort(
//            $this->subject_list,
//            function($a, $b) {
//                return strcmp($a['name'], $b['name']);
//            }
//        );
        return !empty($this->subject_list);
    }

    /**
     * Output the data we collected in exec()
     *
     * @param  string $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'subject')) {
            return false;
        }

        // Stupid blocks
        $this->setBlock($templateHandle, "subject", "subject_block");
        $this->setVar('subject_block', '');
        foreach ($this->subject_list as $subject) {
            $this->setVar('SUBJECT_CODE', $subject['code']);
            $this->setVar('SUBJECT_NAME', $subject['name']);
            $this->parse('subject_block', 'subject', true);
        }

        $this->parseConfig();
        return true;
    }
}
