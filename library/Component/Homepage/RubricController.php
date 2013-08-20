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
     * {@inheritdoc}
     */
    public function execute() {
        $this->group_handler = new Group();
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

        return true;
    }

}
