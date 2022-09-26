<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Predicate\InvalidColumnException;
use Aura\Router\Exception\RouteNotFound;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Assessment\Term\Model;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Assessment\Term;
use Schoolbox\Resource\Folder;

/**
 * @method Folder\Model getContext()
 */
class Component_Homepage_ManageClassController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Term\Mapper
     */
    private Term\Mapper $termMapper;

    /**
     * Is the external DB enabled in the admin config?
     *
     * @var bool
     */
    private bool $externalDbEnabled;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws InvalidArgumentException If unable to find a specified mapper class
     */
    public function execute(): bool
    {
        /** @var Term\Mapper $termMapper */
        $termMapper              = $this->getMapperFactory()->get(Term\Mapper::class);
        $this->termMapper        = $termMapper;
        $this->externalDbEnabled = (bool)Config::get('external_enable');

        // This component is always present on homepages (embedded in page tot menu)
        return true;
    }

    /**
     * @return Model
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getCurrentTerm(): Model
    {
        return $this->termMapper->findForDateAndYearLevel(Alaress_Date_Format::now(), $this->getContext()->getYearLevels()->first());
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return boolean
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws PDOException
     * @throws RuntimeException
     * @throws InvalidColumnException
     * @throws NotFoundExceptionInterface|ContainerExceptionInterface
     * @throws RouteNotFound
     * @throws DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        $isGroupPage  = $this->getContext()->isGroup();
        $isClassPage  = $this->getContext()->isClass();
        $wasClassPage = $this->getContext()->wasClass();
        $isUnitPage   = $this->getContext()->hasUnitSpecification();
        $isCoursePage = $this->getContext()->hasCourseSpecification();
        $lmsEnabled   = $this->getConfigLoader()->get(ConfigMapper::NAME_LMS_ENABLED);

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
        $this->setBlock($templateHandle, 'award_list_link', 'award_list_link_block');
        $this->setBlock($templateHandle, 'markbook_link', 'markbook_link_block');
        $this->setBlock($templateHandle, 'rubric_markbook_link', 'rubric_markbook_link_block');
        $this->setBlock($templateHandle, 'course_markbook_link', 'course_markbook_link_block');
        $this->setBlock($templateHandle, 'manage_criteria', 'manage_criteria_block');
        $this->setBlock($templateHandle, 'class_pastoral_report_link', 'class_pastoral_report_link_block');
        $this->setBlock($templateHandle, 'course_link', 'course_link_block');
        $this->setBlock($templateHandle, 'assessment_calendar_link', 'assessment_calendar_link_block');
        $this->setBlock($templateHandle, 'qrcode_link', 'qrcode_link_block');

        // Determine the term for the grades link
        $currentTerm = $this->getCurrentTerm();

        // Show markbook link to staff on class or group pages
        $this->setVar('markbook_link_block');
        if ($lmsEnabled && $this->getConfigLoader()->get(ConfigMapper::NAME_CLASS_MARKBOOK_ENABLED)) {
            $classMarkbookLink = $this->buildMarkbookLink();
            if (
                $classMarkbookLink
                && ($isGroupPage || $isClassPage || $wasClassPage)
            ) {
                $this->setVar('MARKBOOK_LINK', $classMarkbookLink);
                $this->parse('markbook_link_block', 'markbook_link');
                $hasClassActions = true;
            }
        }

        /** @var Alaress\Authorizer\Authorizer $authorizer */
        $authorizer   = $this->getSitePage()->getDi()->get('authorizer');
        $route        = 'rubricMarkbook.getMarkbook';
        $folderParams = [
            'folder'   => $this->getContext(),
            'folderId' => $this->getContext()->getId(),
        ];

        // Show rubric markbook link on class or group pages to those who are authorized
        // All of these permissions should be baked into the authorizer check
        if (
            ($isGroupPage || $isClassPage || $wasClassPage)
            && $this->getConfigLoader()->get('rubric_markbook_enable')
            && $authorizer->isPathAuthorizedForUser($route, $folderParams, $this->getUser())
        ) {
            $this->setVar('RUBRIC_MARKBOOK_LINK', $this->getRouter()->generate($route, $folderParams));
            $this->parse('rubric_markbook_link_block', 'rubric_markbook_link');
            $hasClassActions = true;
        } else {
            $this->setVar('rubric_markbook_link_block');
        }

        // Show course markbook link to staff on unit or course pages (if feature enabled)
        $this->setVar('course_markbook_link_block');
        if (
            $isStaffOrSuper
            && ($isUnitPage || $isCoursePage)
            && $this->getConfigLoader()->get('course_markbook_enable')
        ) {
            $courseMarkbookLink = $this->buildCourseMarkbookLink($isUnitPage, $isCoursePage);
            if ($courseMarkbookLink) {
                $this->setVar('COURSE_MARKBOOK_LINK', $this->buildCourseMarkbookLink($isUnitPage, $isCoursePage));
                $this->parse('course_markbook_link_block', 'course_markbook_link');
                $hasClassActions = true;
            }
        }

        // Badge Report
        if (
            ($isClassPage || $isGroupPage)
            && $this->getConfigLoader()->get(ConfigMapper::NAME_BADGES_ENABLED)
            && $authorizer->isPathAuthorizedForUser('learningBadgeAward.getListPage', $folderParams, $this->getUser())
        ) {
            $this->setVar(
                'AWARD_LIST_LINK',
                $this->getRouter()->generate(
                    'learningBadgeAward.getListPage',
                    $folderParams
                )
            );
            $this->parse('award_list_link_block', 'award_list_link');
            $hasClassActions = true;
        } else {
            $this->setVar('award_list_link_block');
        }

        /* Assessment Calendar Link
         */
        $route = 'learningAssessmentCalendar.getReport';
        $params = [];

        if (
            $authorizer->isPathAuthorizedForUser($route, $params, $this->getUser())
            && $this->getConfigLoader()->get('assessment_calendar_enabled')
        ) {
            $assessmentCalendarUrl = $this->getRouter()->generate('learningAssessmentCalendar.getReport');
            if ($isUnitPage) {
                $assessmentCalendarUrl .= '?course=' . $this->getContext()->getUnitSpecification()->getCourse()->getId();
            } elseif ($isCoursePage) {
                $assessmentCalendarUrl .= '?course=' . $this->getContext()->getCourseSpecification()->getId();
            } elseif ($isClassPage || $isGroupPage) {
                $assessmentCalendarUrl .= '?folder=' . $this->getContext()->getFolder()->getId();
            }
            $this->setVar('ASSESSMENT_CALENDAR_LINK', $assessmentCalendarUrl);
            $this->parse('assessment_calendar_link_block', 'assessment_calendar_link');
        } else {
            $this->setVar([
                'assessment_calendar_link_block' => ''
            ]);
        }

        // Generate QR Code
        if (
            ($isClassPage || $isGroupPage)
            && $this->getConfigLoader()->get(ConfigMapper::NAME_QR_LOGIN_ENABLED)
            && $authorizer->isPathAuthorizedForUser('qrcode.getListPage', $folderParams, $this->getUser())
        ) {
            $this->setVar(
                'QRCODE_LINK',
                $this->getRouter()->generate(
                    'qrcode.getListPage',
                    $folderParams
                )
            );
            $this->parse('qrcode_link_block', 'qrcode_link');
            $hasClassActions = true;
        } else {
            $this->setVar('qrcode_link_block');
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
        $route  = 'learningGrades.getGradesForSubject';
        $params = [
            'userId'    => $this->getUser()->getId(),
            'termCode'  => $currentTerm->getCode(),
            'subjectId' => $this->getContext()->getId(),
        ];
        if (
            ($involvedInGroup || $involvedInClass)
            && $lmsEnabled
            && UserAccess::ACCESS_READ === $this->getContentPermission()
            && $this->getAuthorizer()->isPathAuthorizedForUser($route, $params, $this->getUser())
        ) {
            $this->setVar('STUDENT_GRADE_LINK', $this->getRouter()->generate($route, $params));
            $this->parse('student_grades_block', 'student_grades');
            $hasClassActions = true;
        } else {
            $this->setVar('student_grades_block');
        }

        // Not a class, or externalDB disabled, or no write access?
        if (!$isClassPage || !$this->externalDbEnabled || $this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            $this->setVar('class_list_block');
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
        if (
            ($isClassPage || $isGroupPage || $isUnitPage || $isCoursePage)
            && $lmsEnabled
            && $this->getContentPermission() >= UserAccess::ACCESS_WRITE
            && $this->getUser()->getRole()->isStaff()
        ) {
            $this->parse('manage_criteria_block', 'manage_criteria');
            $hasClassActions = true;
        } else {
            $this->setVar('manage_criteria_block');
        }

        /*
         * Render the class_pastoral_report_link_block if:
         *  * the page is a class page AND
         *  * pastoral module is enabled AND
         *  * the user is authorised to access the link destination
         */
        if (
            $isClassPage &&
            $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ENABLED) &&
            $authorizer->isPathAuthorizedForUser('pastoralClassReportModule.getReport', $folderParams, $this->getUser())
        ) {
            $this->setVar(
                'PASTORAL_CLASS_REPORT_LINK',
                $this->getRouter()->generate('pastoralClassReportModule.getReport', $folderParams)
            );
            $this->parse('class_pastoral_report_link_block', 'class_pastoral_report_link');
            $hasClassActions = true;
        } else {
            $this->setVar('class_pastoral_report_link_block');
        }

        // Output course modify link if course page
        $this->setVar('course_link_block');

        /* Decide if you have WRITE permissions before providing Modify link
         */
        if ($isCoursePage && $this->getAccess()->getFolderAccess($this->getUser(), $this->getContext()->getCourseSpecification()->getFolder()->getId()) > UserAccess::ACCESS_READ) {
            $this->setVar('PAGE_COURSE_ID', (string)$this->getContext()->getCourseSpecification()->getId());
            $this->parse('course_link_block', 'course_link');
            $hasClassActions = true;
        }

        return $hasClassActions;
    }

    /**
     * Build a link to the markbook, if this controller's user has permission to
     * view it.
     *
     * @return string|null
     * @throws RuntimeException
     * @throws NotFoundExceptionInterface|ContainerExceptionInterface
     * @throws RouteNotFound
     */
    private function buildMarkbookLink(): ?string
    {
        $classArg = [
            'folder'   => $this->getContext(),
            'folderId' => $this->getContext()->getId(),
        ];
        $route = 'learningMarkbook.getMarkbookForClass';

        if (
            !$this->getConfigLoader()->get(ConfigMapper::NAME_CLASS_MARKBOOK_ENABLED)
            || !$this->getAuthorizer()->isPathAuthorizedForUser($route, $classArg, $this->getUser())
        ) {
            return null;
        }

        return $this->getRouter()->generate($route, $classArg);
    }

    /**
     * If homepage was a class, don't filter markbook by term, else filter to just the current term.
     *
     * @param bool $isUnitPage
     * @param bool $isCoursePage
     * @return ?string
     * @throws RuntimeException
     * @throws NotFoundExceptionInterface|ContainerExceptionInterface
     * @throws RouteNotFound
     */
    private function buildCourseMarkbookLink(bool $isUnitPage, bool $isCoursePage): ?string
    {
        $route  = null;
        $params = null;

        if ($isCoursePage) {
            $courseSpec = $this->getContext()->getCourseSpecification();
            $route      = 'courseMarkbook.getMarkbook';
            $params     = [
                'courseSpec'   => $courseSpec,
                'courseSpecId' => $courseSpec->getId(),
            ];
        } elseif ($isUnitPage) {
            $route  = 'courseMarkbook.getRedirect';
            $params = [
                'courseSpecId' => $this->getContext()->getUnitSpecification()->getCourse()->getId(),
                'unitSpecId'   => $this->getContext()->getUnitSpecification()->getId(),
                'folderId'     => 0,
            ];
        }

        if ($route && $this->getAuthorizer()->isPathAuthorizedForUser($route, $params, $this->getUser())) {
            return $this->getRouter()->generate($route, $params);
        }

        return null;
    }
}
