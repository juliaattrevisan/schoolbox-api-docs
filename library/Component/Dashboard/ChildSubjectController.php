<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Geosnapshot\ValidStudentPhotosAvailable;
use Schoolbox\Permission\Access\UserAccess;
use Custom\Dsfg\ValidJuniorSchoolStudent;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Custom\Vist\TravelLog\Renderer;

class Component_Dashboard_ChildSubjectController extends Component_Dashboard_ControllerAbstract
{
    /** @var GenericCollection<\Schoolbox\User>  */
    private GenericCollection $children;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if ($this->getUser()->getChildren()->count()) {
            $this->children = $this->getUser()->getChildren();
            return true;
        }
        return false;
    }

    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'childSubject')) {
            return false;
        }

        $this->setBlock($templateHandle, "student_block", "student_subjects");
        $this->setBlock("student_block", "subject_block", "subject_item");
//        $this->setBlock('student_block', 'reports', 'reports_block');
//        $this->setBlock('student_block', 'eportfolio', 'eportfolio_block');
//        $this->setBlock('student_block', 'pastoral', 'pastoral_block');
//        $this->setBlock('student_block', 'due_work', 'due_work_block');
//        $this->setBlock('student_block', 'grades_button', 'grades_button_block');
//        $this->setBlock('student_block', 'timetable', 'timetable_block');
//        $this->setBlock('student_block', 'achievements', 'achievements_block');
//        $this->setBlock('student_block', 'custom_buttons', 'custom_buttons_block');
//        $this->setBlock('student_block', 'geosnapshot', 'geosnapshot_block');

        $isParent = $this->getUser()->getRole()->isParent();

        $i = 0;

        // Get the Verso school's Travel Log data
        $travelLogRenderer = new Renderer(
            $this->getConfigLoader()->get('site_school_code'),
            $this->getConfigLoader()->get('verso_client_id'),
            $this->getConfigLoader()->get('verso_client_secret'),
            $this->getConfigLoader()->get('travelLog_api_caching')
        );

        /** @var \Schoolbox\User $userChild */
        foreach ($this->children as $userChild) {
            $i++;

            $this->setVar([
                'STUDENT_ID' => $userChild->getId(),
                'STUDENT_EXTERNAL_ID' => $userChild->getExternalId(),
                'STUDENT_NAME' => Scrub::text($userChild->getFullName()),
                'STUDENT_YEAR' => $userChild->getYear() ? Scrub::text($userChild->getYear()->getLongName($this->getSitePage()->getLocalisedTerm('year', false, true))) : '',
                'STUDENT_YEAR_LEVEL' => $userChild->getYear() ? Scrub::text($userChild->getYear()->getName()) : '',
                'TRAVEL_LOG' => $travelLogRenderer->render($this->getSitePage()->getTwig(), $userChild->getExternalId(), true),
            ]);

//            if (
//                $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ENABLED) &&
//                $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED)
//            ) {
//                $this->parse('pastoral_block', 'pastoral');
//            } else {
//                $this->setVar('pastoral_block', '');
//            }

            if ($userChild->getSubjectCollection()) {
                /** @var array $subject */
                foreach ($userChild->getSubjectCollection() as $subject) {
                    if ($subject['perm'] >= UserAccess::ACCESS_READ) {
                        $this->setVar("STUDENT_SUBJECT_NAME", Scrub::text($subject['name']));
                        $this->setVar("STUDENT_SUBJECT_CODE", urlencode(urlencode($subject['matchedCode'])));
                        $this->parse("subject_item", "subject_block", true);
                    }
                }
            }

            /* Diocesan Junior School Students don't have links to subjects
             */
            if (
                ($this->getConfigLoader()->get('site_school_code') === 'DSFG') &&
                (new ValidJuniorSchoolStudent())->isSatisfiedBy($userChild)
            ) {
                $this->setVar([
                    'subject_item' => '',
                ]);
            }

            /* Output calendar date to show */
            $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

            /* ePortfolio link
             */
            $authenticator = new \Eportfolio_Authenticator($_SESSION['user'], $userChild);

            /* Profile Editor: Buttons
             * Renders the button island the same way as on the profile screen
             */
            $buttonIslandRenderer = new \Schoolbox\Search\User\ButtonIslandRenderer(
                $userChild,
                $now,
                $authenticator,
                $this->getConfigLoader()->get('geosnapshot_api_key'),
                $this->getConfigLoader()->get('geosnapshot_enabled'),
                $this->getRouteGenerator(),
                \Schoolbox\Profile\Button\Mapper::getInstance(),
            );

            $this->setVar([
                'BUTTON_ISLAND' => $buttonIslandRenderer->render($this->getSitePage()->getTwig(), $userChild, true),
            ]);

//            $this->setVar('CAL_TODAY_DATE', $now->getURLDate());

//            /* Hide Due Work link from parents, if Due Work access is restricted and module is disabled
//             */
//            if (
//                !$this->getConfigLoader()->get(ConfigMapper::NAME_UPCOMING_ASSESSMENTS_ENABLED)
//                || ($isParent && $this->getConfigLoader()->get('restrict_parent_duework_access'))
//            ) {
//                $this->setVar('due_work_block', '');
//            } else {
//                $this->parse('due_work_block', 'due_work');
//            }
//
//            // Hide grades from parents, if grades access is restricted or module is disabled
//            if (
//                !$this->getConfigLoader()->get(ConfigMapper::NAME_GRADES_ENABLED)
//                || ($isParent && $this->getConfigLoader()->get('restrict_parent_grades'))
//            ) {
//                $this->setVar('grades_button_block', '');
//            } else {
//                $this->parse('grades_button_block', 'grades_button');
//            }
//
//            if ($userChild->getHideTimetable()) {
//                $this->setVar('timetable_block', '');
//            } else {
//                $this->parse('timetable_block', 'timetable');
//            }


//            if ($authenticator->canView()) {
//                $this->parse('eportfolio_block', 'eportfolio');
//            } else {
//                $this->setVar('eportfolio_block', '');
//            }
//
//            if ($this->getConfigLoader()->get(ConfigMapper::NAME_BADGES_ENABLED)) {
//                $this->parse('achievements_block', 'achievements');
//            } else {
//                $this->setVar('achievements_block', '');
//            }
//
//            /* Academic report link
//            */
//            if ($this->getConfigLoader()->get('external_document_report_enabled')) {
//                $this->parse('reports_block', 'reports');
//            } else {
//                $this->setVar('reports_block');
//            }
//
//            // Custom buttons from admin
//            try {
//                $customButtons = ProfileButtonMapper::getInstance()->findButtonsForUser($userChild, $this->getUser());
//            } catch (MapperException $e) {
//                $customButtons = [];
//            }
//
//            foreach ($customButtons as $customButton) {
//                $this->setVar([
//                    'BUTTON_TEXT' => Scrub::text($customButton->getText()),
//                    'BUTTON_TITLE' =>  Scrub::text($customButton->getTitle()),
//                    'BUTTON_HREF' => htmlspecialchars($customButton->getURL()),
//                    'BUTTON_ICON' => Scrub::text($customButton->getIcon()),
//                    'BUTTON_CSS' => Scrub::text($customButton->getCss()),
//                    'BUTTON_TARGET' => Scrub::text($customButton->getTarget()),
//                ]);
//
//                $this->parse('custom_buttons_block', 'custom_buttons', true);
//            }

            // School-specific buttons
            if ('TIGS' === $this->getConfigLoader()->get('site_school_code')) {
                $this->setBlock('student_block', 'continuous_report', 'continuous_report_block');
                $route  = 'tigsParticipationReport.getReport';
                $params = ['userId' => $userChild->getId(), 'user' => $userChild,];
                if ($this->getAuthorizer()->isPathAuthorizedForUser($route, $params, $this->getUser())) {
                    $this->setVar('HREF_CONTINUOUS_REPORT', $this->getRouteGenerator()->generate($route, $params));
                    $this->parse('continuous_report_block', 'continuous_report');
                } else {
                    $this->setVar('continuous_report_block');
                }
            }

//            /* Decide whether the viewer can see the Geosnapshot button for the viewee
//             */
//            $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
//                ->isSatisifiedBy(
//                    $this->getConfigLoader()->get('geosnapshot_api_key'),
//                    $this->getConfigLoader()->get('geosnapshot_enabled'),
//                    $this->getUser(),
//                    $userChild
//                );

//            if ($isGeosnapshotAvailable) {
//                $this->parse('geosnapshot_block', 'geosnapshot');
//            }

            // Parse the parent block
            $this->parse("student_subjects", "student_block", true);

            /* Clear children after parsing out the parent
             */
            $this->setVar([
                'subject_item' => '',
//                'due_work_block' => '',
//                'custom_buttons_block' => '',
//                'geosnapshot_block' => '',
            ]);
        }

        return $i > 0;
    }
}
