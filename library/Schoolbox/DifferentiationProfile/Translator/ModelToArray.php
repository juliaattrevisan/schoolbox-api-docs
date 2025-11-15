<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\Admin\DifferentiationProfiles\Model as Config;
use Schoolbox\User;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
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
                '_links' => [
                    'profile' => $this->getRouteIfAuthorized(
                        'searchUser.getProfile',
                        ['userId' => $student->getId()],
                    ),
                ],
            ],
            '_routes' => [
                'linkedRecords' => $this->getRouteIfAuthorized(
                    'differentiationProfiles.getLinkedPastoralRecords',
                    ['studentId' => $student->getId()],
                ),
                'edit' => $this->getRouteIfAuthorized(
                    'differentiationProfiles.postDifferentiationProfileData',
                    [
                        'student' => $student,
                        'studentId' => $student->getId()
                    ],
                ),
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
                $value = $model->{$configKey[$config->getName()]}();

                // Convert comma-delimited strings to arrays for adjustment fields
                if (in_array($config->getName(), ['adjustment_category', 'adjustment_level'])) {
                    if ($value === null) {
                        $carry[$config->getName()] = null;
                    } elseif (empty($value)) {
                        $carry[$config->getName()] = [];
                    } else {
                        $carry[$config->getName()] = array_map('intval', explode(',', $value));
                    }
                } else {
                    $carry[$config->getName()] = $value;
                }
            }
            return $carry;
        }, []);
        return array_merge($return, $config);
    }
}
