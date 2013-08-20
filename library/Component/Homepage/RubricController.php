<?php
/**
 * Rubric view and edit component for teachers.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Homepage_RubricController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    protected $group_handler;

    /**
     * @var DueWork_Rubric_Handler
     */
    protected $rubric_handler;

    /**
     * @var array
     */
    protected $criteria = array();

    /**
     * {@inheritdoc}
     */
    public function execute() {
        $this->group_handler  = new Group();
        $this->rubric_handler = new DueWork_Rubric_Handler();
        $this->criteria       = $this->rubric_handler->getAvailableCriteria($this->getContext('id'), 'category', true);
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'rubric')) {
            return false;
        }

        // Permission denied if user doesn't have write access to category
        if ($this->group_handler->getPermission($this->getContext('id')) < WRITE_ACCESS) {
            return false;
        }

        $this->parseConfig();

        // Dump out block stuff
        $this->setBlock($templateHandle, 'rubric_criterion', 'rubric_criterion_block');
        $this->setBlock('rubric_criterion', 'rubric_descriptor', 'rubric_descriptor_block');
        $this->setVar('rubric_criterion_block', '');
        foreach ($this->criteria as $criterion_name => $criterion_descriptors) {
            $this->setVar('CRITERION_NAME', Scrub::text($criterion_name));
            $this->setVar('rubric_descriptor_block', '');
            foreach ($criterion_descriptors as $descriptor) {
                $this->setVar('DESCRIPTOR_NAME', $descriptor['name']);
                $this->parse('rubric_descriptor_block', 'rubric_descriptor', true);
            }
            $this->parse('rubric_criterion_block', 'rubric_criterion', true);
        }

        return true;
    }

}
