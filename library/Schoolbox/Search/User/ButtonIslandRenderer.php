<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Search\User;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\Geosnapshot\ValidPhotosAvailable;
use Schoolbox\Profile\Button\Mapper;
use Schoolbox\User;
use Twig\Environment;

class ButtonIslandRenderer
{
    /**
     * Constructor.
     *
     * @param User $viewer
     * @param \Alaress_Date_Format $now
     * @param \Eportfolio_Authenticator $eportfolioAuthenticator
     * @param string $geosnapshotApiKey
     * @param string $geosnapshotEnabled
     * @param bool $compactMode
     * @param bool $emailFunctionsAvailable
     * @param Authorizer $authorizer
     * @param Generator $routeGenerator
     * @param Mapper $profileButtonMapper
     */
    public function __construct(
        private \Schoolbox\User $viewer,
        private \Alaress_Date_Format $now,
        private \Eportfolio_Authenticator $eportfolioAuthenticator,
        private string $geosnapshotApiKey,
        private string $geosnapshotEnabled,
        private bool $compactMode,
        private bool $emailFunctionsAvailable,
        private \Alaress\Authorizer\Authorizer $authorizer,
        private \Aura\Router\Generator $routeGenerator,
        private \Schoolbox\Profile\Button\Mapper $profileButtonMapper,
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
        $isGeosnapshotAvailable = (new ValidPhotosAvailable())
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

        /* omg codesnuffler
         */
        $pastoralRoute = 'pastoral.getStudent';
        $pastoralParams = [
            'studentId' => $viewee->getId(),
            'student'   => $viewee,
        ];

        if ($this->authorizer->isPathAuthorizedForUser($pastoralRoute, $pastoralParams, $this->viewer)) {
            try {
                $pastoralUrl = $this->routeGenerator->generate($pastoralRoute, $pastoralParams);
            } catch (RouteNotFound) {
                // noop
            }
        }

        $params = [
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
             * This never used to show on this component: it was hardcoded
             *  to only show on the profile screen and is a mega derivation.
             */
            'allTeacherIds' => [],
            'emailFunctionsAvailable' => $this->emailFunctionsAvailable,
        ];

        /* Add some extra settings for compact mode where we display
         * buttons in a tot
         */
        if ($this->compactMode) {
            $compactMode = [
                'mode' => 'compact',
                'identifier' => sprintf(
                    '%s%s',
                    'row-options-childsubject-',
                    $viewee->getId()
                )
            ];

            $params = array_merge($params, $compactMode);
        }

        return $twig->render('@searchUser/button/button-island.twig', $params);
    }
}
