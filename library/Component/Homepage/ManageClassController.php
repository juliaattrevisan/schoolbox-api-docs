<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Permission\Access\UserAccessMapper;


class Component_Homepage_ManageClassController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var UserAccessMapper
     */
    var $access;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        $this->access = \Schoolbox\Permission\Access\UserAccessMapper::getInstance();
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        // All units on this page - get their component and related course
        $componentList = [];
        $units         = \Schoolbox\Assessment\Course\UnitInstanceMapper::getInstance()->findByColumn(
            'category_id',
            $this->getContext()->getId()
        );
        foreach ($units as $unit) {
            // Only one unit per course
            $componentList[$unit->getComponent()->getId()] = $unit;
        }

        if (!parent::loadTemplate($templateHandle, 'manageClass')) {
            return false;
        }

        if ($this->getContext()->getCodeString() == '') {
            return false;
        }

//        $this->parseConfig();

        $this->setBlock($templateHandle, "class_list", "class_list_block");
        $this->setBlock($templateHandle, "class_mark", "class_mark_block");
        $this->setBlock($templateHandle, "student_grades", "student_grades_block");


        // Determine the term for the grades link
        $this->setVar('WORK_TERM', Schoolbox\Assessment\Term\Mapper::getInstance()->findForToday()->getCode());

        /* Output student grades link to students of this class */
        if ($this->contentPermission == READ_ACCESS && (
            $this->access->involvedInGroup($_SESSION['user'], $this->getContext()->getId()) ||
                $this->access->involvedInSubject($_SESSION['user'], $this->getContext()->getCodeString()))
        ) {
            $this->parse('student_grades_block', 'student_grades');
        } else {
            $this->setVar('student_grades_block', '');
        }


        if (Cfg::get('external_enable') == false || $this->contentPermission < WRITE_ACCESS || $this->getContext()->getCodeString() == '') {
            $this->setVar('class_list_block', '');
        } else {
            $this->parse('class_list_block', 'class_list');
        }
        
        if ($this->contentPermission >= WRITE_ACCESS && $_SESSION['user']->getRole()->isStaff()) {
            $this->parse('class_mark_block', 'class_mark');
        } else {
            $this->setVar('class_mark_block', '');
        }

        return true; // everything went according to plan
    }
}
