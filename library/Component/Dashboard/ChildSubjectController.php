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

        // Get the Verso school's Travel Log data
        $travelLogRenderer = new Renderer(
            $this->getConfigLoader()->get('site_school_code'),
            $this->getConfigLoader()->get('verso_client_id'),
            $this->getConfigLoader()->get('verso_client_secret'),
            $this->getConfigLoader()->get('travelLog_api_caching')
        );

        $i = 0;

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

            /* Profile Editor: Buttons
             * Renders the button island the same way as on the profile screen
             */
            $buttonIslandRenderer = new \Schoolbox\Search\User\ButtonIslandRenderer(
                $this->getUser(),
                \Alaress_Date_Format::now(),
                new \Eportfolio_Authenticator($_SESSION['user'], $userChild),
                $this->getConfigLoader()->get('geosnapshot_api_key') ?? '',
                $this->getConfigLoader()->get('geosnapshot_enabled'),
                (bool)$this->getConfig('compactMode'),
                false,
                $this->getAuthorizer(),
                $this->getRouteGenerator(),
                \Schoolbox\Profile\Button\Mapper::getInstance(),
            );

            $this->setVar([
                'BUTTON_ISLAND' => $buttonIslandRenderer->render($this->getSitePage()->getTwig(), $userChild),
            ]);


            // School-specific buttons

            /* Diocesan Junior School Students don't have links to subjects
             */
            if (($this->getConfigLoader()->get('site_school_code') === 'DSFG') && (new ValidJuniorSchoolStudent())->isSatisfiedBy($userChild)) {
                $this->setVar([
                    'subject_item' => '',
                ]);
            }

            /* TIGS Participation Report
             */
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

            // Parse the parent block
            $this->parse("student_subjects", "student_block", true);

            /* Clear children after parsing out the parent
             */
            $this->setVar([
                'subject_item' => '',
            ]);
        }

        return $i > 0;
    }
}
