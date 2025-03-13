<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Schoolbox\Learning\Assessment\Participant;

/**
 * Translator for translating a Participant model into an array.
 */
class Participations
{
    /**
     * @param Participant $participation
     * @return array
     */
    public function __invoke(Participant $participation): array
    {
        return [
            'id' => $participation->getId(),
            'user' => $participation->getUser(),
            'plan' => call_user_func(new ParticipationPlans(), $participation->getPlan()),
            'createdAt' => $participation->getCreatedAt()?->toRfc3339String(),
            'updatedAt' => $participation->getUpdatedAt()?->toRfc3339String(),
            'excludedAt' => $participation->getExcludedAt()?->toRfc3339String(),
        ];
    }
}
