<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
    ) {
    }

    public function __invoke(Model $model): array
    {
        $student = $model->getStudent();
        return [
            'id'          => $model->getId(),
            'student' => [
                'id' => $student->getId(),
                'fullName' => $student->getFullName(),
                'yearLevel' => $student->getYear() ? [
                    'id' => $student->getYear()->getId(),
                    'name' => $student->getYear()->getName(),
                ] : null,
            ],
            'adjustment_category'    => $model->getAdjustmentCategory(),
            'adjustment_level'       => $model->getAdjustmentLevel(),
            'overview'               => $model->getOverview(),
            'interests_strengths'    => $model->getInterestsStrengths(),
            'needs_challenges'       => $model->getNeedsChallenges(),
            'strategies_adjustments' => $model->getStrategiesAdjustments(),
            'goals'                  => $model->getGoals(),
            'created_at'            => $model->getCreatedAt()?->getLocalShort(),
            'updated_at'            => $model->getUpdatedAt()?->getLocalShort(),
            'deleted_at'            => $model->getDeletedAt()?->getLocalShort(),
        ];
    }
}
