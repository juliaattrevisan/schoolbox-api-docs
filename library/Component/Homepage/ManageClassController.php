<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use \Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Resource\Folder;

/**
 * @method Folder\Model getContext()
 */
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
        $this->externalDbEnabled = (bool)Config::get('external_enable');

        // This component is always present on homepages (embedded in page tot menu)
        return true;
    }


    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return boolean
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws RuntimeException
     */
    public function renderTemplate($templateHandle)
    {
        $isGroupPage  = $this->getContext()->isGroup();
        $isClassPage  = $this->getContext()->isClass();
        $wasClassPage = $this->getContext()->wasClass();
        $isUnitPage   = $this->getContext()->hasUnitSpecification();
        $isCoursePage = $this->getContext()->hasCourseSpecification();


        // If context is not a group, unit or class, render nothing
        if (!$isGroupPage && !$isClassPage && !$wasClassPage && !$isUnitPage && !$isCoursePage) {
            return false;
        }

        $isStaffOrSuper = $this->getUser()->getRole()->isStaff() || $this->getUser()->isSuperuser();

        $classActionsHeading = "Actions";
        if ($isClassPage || $wasClassPage) {
            $classActionsHeading = "Class Actions";
        } elseif ($isUnitPage) {
            $classActionsHeading = "Unit Actions";
        } elseif ($isCoursePage) {
            $classActionsHeading = "Course Actions";
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
        $this->setBlock($templateHandle, 'student_grades', 'student_grades_block');
        $this->setBlock($templateHandle, 'class_list', 'class_list_block');
        $this->setBlock($templateHandle, 'markbook_link', 'markbook_link_block');
        $this->setBlock($templateHandle, 'rubric_markbook_link', 'rubric_markbook_link_block');
        $this->setBlock($templateHandle, 'course_markbook_link', 'course_markbook_link_block');
        $this->setBlock($templateHandle, 'manage_criteria', 'manage_criteria_block');
        $this->setBlock($templateHandle, 'course_link', 'course_link_block');
        $this->setBlock($templateHandle, 'assessment_calendar_link', 'assessment_calendar_link_block');

        // Determine the term for the grades link
        $currentTerm = $this->termMapper->findForToday();
        $this->setVar('WORK_TERM', $currentTerm->getCode());

        // Show markbook link to staff on class or group pages
        if ($isStaffOrSuper && ($isGroupPage || $isClassPage || $wasClassPage) && $this->getConfigLoader()->get('class_markbook_enable')) {
            $this->setVar('MARKBOOK_LINK', $this->buildMarkbookLink($wasClassPage, $currentTerm));
            $this->parse('markbook_link_block', 'markbook_link');
            $hasClassActions = true;
        } else {
            $this->setVar('markbook_link_block');
        }

        /** @var Alaress\Authorizer\Authorizer $authorizer */
        $authorizer = $this->getSitePage()->getDi()->get('authorizer');
        $route      = 'rubricMarkbook.getMarkbook';
        $params     = ['folderId' => $this->getContext()->getId()];

        // Show rubric markbook link on class or group pages to those who are authorized
        if (($isGroupPage || $isClassPage || $wasClassPage)  && $this->getConfigLoader()->get('rubric_markbook_enable') && $authorizer->isPathAuthorizedForUser($route, $params, $this->getUser())) {
            $this->setVar('RUBRIC_MARKBOOK_LINK', $this->getRouter()->generate($route, $params));
            $this->parse('rubric_markbook_link_block', 'rubric_markbook_link');
            $hasClassActions = true;
        } else {
            $this->setVar('rubric_markbook_link_block');
        }

        // Show course markbook link to staff on unit or course pages (if feature enabled)
        if ($isStaffOrSuper && ($isUnitPage || $isCoursePage) && $this->getConfigLoader()->get('course_markbook_enable')) {
            $this->setVar('COURSE_MARKBOOK_LINK', $this->buildCourseMarkbookLink($isUnitPage, $isCoursePage));
            $this->parse('course_markbook_link_block', 'course_markbook_link');
            $hasClassActions = true;
        } else {
            $this->setVar('course_markbook_link_block');
        }

        /* Assessment Calendar Link
         */
        $route = 'learningAssessmentCalendar.getReport';
        $params = [];

        if ($authorizer->isPathAuthorizedForUser($route, $params, $this->getUser()) && $this->getConfigLoader()->get('assessment_calendar_enabled')) {
            $this->parse('assessment_calendar_link_block', 'assessment_calendar_link');
        } else {
            $this->setVar([
                'assessment_calendar_link_block' => ''
            ]);
        }

        $involvedInGroup = $isGroupPage && $this->getAccess()->involvedInGroup(
                $this->getUser(),
                $this->getContext()->getId()
            );

        $involvedInClass = $isClassPage && $this->getAccess()->involvedInSubject(
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

        /*
         * Render link to manage criteria if:
         * - folder is current class, group, unit or course (ignore ex-classes)
         * - user is staff, and
         * - has WRITE+ perm
         */
        if (($isClassPage || $isGroupPage || $isUnitPage || $isCoursePage) && $this->getContentPermission() >= UserAccess::ACCESS_WRITE
            && $this->getUser()->getRole()->isStaff()) {
            $this->parse('manage_criteria_block', 'manage_criteria');
            $hasClassActions = true;
        } else {
            $this->setVar('manage_criteria_block');
        }

        // Output course modify link if course page
        if ($isCoursePage) {
            $this->setVar('PAGE_COURSE_ID', (string)$this->getContext()->getCourseSpecification()->getId());
            $this->parse('course_link_block', 'course_link');
            $hasClassActions = true;
        } else {
            $this->setVar('course_link_block', '');
        }

        return $hasClassActions;
    }


    /**
     * If homepage was a class, don't filter markbook by term, else filter to just the current term.
     *
     * @param bool       $wasClassPage
     * @param Term\Model $currentTerm
     * @return string
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function buildMarkbookLink(bool $wasClassPage, Term\Model $currentTerm): string
    {
        $classArg = ['folderId' => $this->getContext()->getId()];
        if ($wasClassPage) {
            return $this->getRouter()->generate('learningMarkbook.getMarkbookForClass', $classArg);
        }

        $classAndTermArgs = array_merge($classArg, ['termCode' => $currentTerm->getCode()]);
        return $this->getRouter()->generate('learningMarkbook.getMarkbookForClassAndTerm', $classAndTermArgs);
    }

    /**
     * If homepage was a class, don't filter markbook by term, else filter to just the current term.
     *
     * @param bool $isUnitPage
     * @param bool $isCoursePage
     * @return string
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function buildCourseMarkbookLink(bool $isUnitPage, bool $isCoursePage): ?string
    {
        $courseSpec = $this->getContext()->getCourseSpecification();
        if ($isCoursePage) {
            return $this->getRouter()->generate('courseMarkbook.getMarkbook', [
                'courseSpec'   => $courseSpec,
                'courseSpecId' => $courseSpec->getId(),
            ]);
        }
        if ($isUnitPage) {
            return $this->getRouter()->generate('courseMarkbook.getRedirect', [
                'courseSpecId' => $this->getContext()->getUnitSpecification()->getCourse()->getId(),
                'unitSpecId'   => $this->getContext()->getUnitSpecification()->getId(),
                'folderId'     => 0,
            ]);
        }
        return '';
    }
}
