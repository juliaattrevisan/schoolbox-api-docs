<?php

/**
 * Component for outputting a student's subject list.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Component_Dashboard_SubjectController extends Component_Dashboard_ControllerAbstract
{

    /**
     * A list of subjects to show
     *
     * @var array
     */
    protected $subject_list;

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'My Subjects';

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->subject_list = $_SESSION['subjects'];
        return !empty($this->subject_list);
    }

    /**
     * Output the data we collected in exec()
     *
     * @param  string $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'subject')) {
            return false;
        }

        // Stupid blocks
        $this->setBlock($templateHandle, "subject", "subject_block");
        $this->setVar('subject_block', '');
        foreach ($this->subject_list as $subject) {
            $this->setVar([
                'SUBJECT_CODE' => Scrub::text($subject['code']),
                'SUBJECT_CODE_URL' => urlencode(urlencode($subject['code'])),
                'SUBJECT_NAME' => $subject['name']
            ]);
            $this->parse('subject_block', 'subject', true);
        }

        $this->parseConfig();
        return true;
    }
}
