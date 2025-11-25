<?php

namespace Schoolbox\Learning\Assessment;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Schoolbox\User;

interface IProgressTick
{
    /**
     * Render this assessment's statuses as progress ticks based on something the $participant is doing
     * in their authoritative response/feedback instances
     *
     * Route Generator and Authorizer are both required, since computation of the progress tick states sometimes have
     * you looking at route permissions to determine Next Available State.
     *
     * The $overload array is a miscellaneous payload container inconvenient-to-obtain stuffs (use optionally)
     *
     * @param Generator $routeGenerator
     * @param Authorizer $authorizer
     * @param Participant|null $participant
     * @param User $viewer
     * @param array $overload
     * @return array|null
     */
    public function getProgressTick(
        Generator $routeGenerator,
        Authorizer $authorizer,
        ?Participant $participant,
        \Schoolbox\User $viewer,
        array $overload = []
    ): ?array;
}
