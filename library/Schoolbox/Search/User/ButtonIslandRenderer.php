<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Search\User;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Geosnapshot\ValidStudentPhotosAvailable;
use Schoolbox\Search\User\templates\button\Schoolbox;
use Twig\Environment;

class ButtonIslandRenderer
{
    /**
     * Constructor.
     *
     * @param \Schoolbox\User                  $viewer
     * @param \Alaress_Date_Format             $now
     * @param \Eportfolio_Authenticator        $eportfolioAuthenticator
     * @param string                           $geosnapshotApiKey
     * @param string                           $geosnapshotEnabled
     * @param \Aura\Router\Generator           $routeGenerator
     * @param \Schoolbox\Profile\Button\Mapper $profileButtonMapper
     */
    public function __construct(
        private \Schoolbox\User $viewer,
        private \Alaress_Date_Format $now,
        private \Eportfolio_Authenticator $eportfolioAuthenticator,
        private string $geosnapshotApiKey,
        private string $geosnapshotEnabled,
        private \Aura\Router\Generator $routeGenerator,
        private \Schoolbox\Profile\Button\Mapper $profileButtonMapper
    ) {
    }


    /**
     * Render Profile Buttons into a twig fragment, exactly as it would have been implemented
     * on the Profile screen. It obeys the same Additional Constraints and other requirements.
     *
     * @param Environment     $twig
     * @param \Schoolbox\User $viewee
     * @return string
     */
    public function render(Environment $twig, \Schoolbox\User $viewee): string
    {
        $buttons = $this->profileButtonMapper->findButtonsForUser($viewee, $this->viewer);

        /* Decide whether View Photos (Geosnapshots) is available for this student
         * If you're allowed to make this request...
         */
        $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
            ->isSatisifiedBy(
                $this->geosnapshotApiKey,
                $this->geosnapshotEnabled,
                $this->viewer,
                $viewee
            );

        /* Find out if Pastoral Care is enabled and that you have access to a student's pastoral care records
         * If you do, remember its url
         */
        $pastoralUrl = false;

        try {
            $pastoralUrl = $this->routeGenerator->generate(
                'pastoral.getStudent',
                [
                    'studentId' => $viewee->getId(),
                    'student'   => $viewee,
                ],
                $this->viewer
            );
        } catch (RouteNotFound) {
            // noop
        }

        return $twig->render('@searchUser/button/button-island.twig', [
            /* This now includes system buttons - there's no longer really a distinction between system and
             * custom buttons, as far as the end user is concerned
             */
            'buttons' => $buttons,
            /* This is used for the calendar url, a dynamic set of args
             */
            'now' => $this->now,
            /* The viewee's User instance - sadly, very easy to confuse with USER global, which means
             * the viewer, so be careful with that
             */
            'user' => $viewee,
            /* Retain this old check
             */
            'eportfolioAuth' => $this->eportfolioAuthenticator->canView(),
            /* Decides if pastoral buttons and stuff actually exist beyond our Availability and
             * Visibility settings (ie. does your user have permissions etc to this place)
             *
             * We needed to keep this
             */
            'pastoralUrl' => $pastoralUrl,
            /* Decides if the Geosnapshot button could be accessed
             */
            'isGeosnapshotAvailable' => $isGeosnapshotAvailable,
            /* Used for emailing staff (Email All Teachers)
             * TODO This never used to show on this component: it was hardcoded
             *  to only show on the profile screen and is a mega derivation.
             */
            'allTeacherIds' => [],
        ]);
    }
}
