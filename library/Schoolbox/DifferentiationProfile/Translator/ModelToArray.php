<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\Admin\DifferentiationProfiles\Model as Config;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        private Collection $profileConfig,
    ) {
    }

    public function __invoke(Model $model): array
    {
        $student = $model->getStudent();
        $return = [
            'id'          => $model->getId(),
            'student' => [
                'id' => $student->getId(),
                'fullName' => $student->getFullName(),
                'givenName' => $student->getGivenName(),
                'lastName' => $student->getLastName(),
                'yearLevel' => $student->getYear() ? [
                    'id' => $student->getYear()->getId(),
                    'name' => $student->getYear()->getName(),
                ] : null,
            ],
            'created_at'            => $model->getCreatedAt()?->getLocalShort(),
            'updated_at'            => $model->getUpdatedAt()?->getLocalShort(),
            'deleted_at'            => $model->getDeletedAt()?->getLocalShort(),
        ];

        $configKey = [
            'adjustment_category' => 'getAdjustmentCategory',
            'adjustment_level' => 'getAdjustmentLevel',
            'overview' => 'getOverview',
            'interests_strengths' => 'getInterestsStrengths',
            'needs_challenges' => 'getNeedsChallenges',
            'strategies_adjustments' => 'getStrategiesAdjustments',
            'goals' => 'getGoals',
        ];

        /* filtering out and only get the enabled config values
         */
        $config = $this->profileConfig->reduce(function (array $carry, Config $config) use ($model, $configKey) {
            if (array_key_exists($config->getName(), $configKey)) {
                $carry[$config->getName()] = $model->{$configKey[$config->getName()]}();
            }
            return $carry;
        }, []);
        return array_merge($return, $config);
    }
}
