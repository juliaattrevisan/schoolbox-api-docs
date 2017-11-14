<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Assessment\Term;

class Component_Homepage_ManageClassController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /**
     * Is the external DB enabled in the admin config?
     *
     * @var bool
     */
    private $externalDbEnabled;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws InvalidArgumentException If unable to find a specified mapper class
     */
    public function execute()
    {
        $this->userAccessMapper  = $this->getMapperFactory()->get(UserAccessMapper::class);
        $this->termMapper        = $this->getMapperFactory()->get(Term\Mapper::class);
        $this->externalDbEnabled = (bool)Cfg::get('external_enable');

        // This component is always present on homepages (embedded in page tot menu)
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        $isGroupPage = $this->getContext()->isGroup();
        $isClassPage = $this->getContext()->isClass();
        $isUnitPage = $this->getContext()->hasUnitSpecification();

        $classActionsHeading = "Class Actions";
        if ($isUnitPage) {
            $classActionsHeading = "Unit Actions";
        } elseif ($isGroupPage) {
            $classActionsHeading = "Group Actions";
        }
        $this->setVar('CLASS_ACTIONS_HEADING', $classActionsHeading);

        /* This boolean is a hack to make the Class Actions heading
         * render only when needed.
         * It gets set to true whenever content that should fall under
         * this heading is parsed
         */
        $hasClassActions = false;

        // If context is not a group or class, render nothing
        if (!$isGroupPage && !$isClassPage && !$isUnitPage) {
            return false;
        }

        $this->setBlock($templateHandle, 'class_list', 'class_list_block');
        $this->setBlock($templateHandle, 'class_mark', 'class_mark_block');
        $this->setBlock($templateHandle, 'student_grades', 'student_grades_block');
        $this->setBlock('class_mark', 'markbook_link', 'markbook_link_block');

        // Hide markbook link on unit pages
        if ($isUnitPage) {
            $this->setVar('markbook_link_block', '');
        } else {
            $this->parse('markbook_link_block', 'markbook_link');
        }

        // Determine the term for the grades link
        $this->setVar('WORK_TERM', $this->termMapper->findForToday()->getCode());

        $involvedInGroup = $isGroupPage && $this->userAccessMapper->involvedInGroup(
            $this->getUser(),
            $this->getContext()->getId()
        );

        $involvedInClass = $isClassPage && $this->userAccessMapper->involvedInSubject(
            $this->getUser(),
            $this->getContext()->getCodeString()
        );

        /* Output student grades link to students (other users with READ access may be able to see this page) */
        if (($involvedInGroup || $involvedInClass) && UserAccess::ACCESS_READ === $this->getContentPermission()) {
            $this->parse('student_grades_block', 'student_grades');
            $hasClassActions = true;
        } else {
            $this->setVar('student_grades_block', '');
        }

        // Not a class, or externalDB disabled, or no write access?
        if (!$isClassPage || !$this->externalDbEnabled || $this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            $this->setVar('class_list_block', '');
        } else {
            $this->parse('class_list_block', 'class_list');
            $hasClassActions = true;
        }

        // Render link to markbook if user is staff and has WRITE+ perm
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE && $this->getUser()->getRole()->isStaff()) {
            $this->parse('class_mark_block', 'class_mark');
            $hasClassActions = true;
        } else {
            $this->setVar('class_mark_block', '');
        }

        return $hasClassActions;
    }
}
