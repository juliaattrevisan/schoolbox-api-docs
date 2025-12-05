<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Schoolbox\Collection;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\User;

class ProfileToArray
{
    public function __construct(
        protected User $currentUser,
        private Collection $profileConfig,
    ) {
    }

    public function __invoke(Model $model): array
    {
        $profile = [
            'adjustment_level' => $model->getAdjustmentLevel()?->getPropertyValues('name') ?? '',
            'adjustment_category' => $model->getAdjustmentCategory()?->getPropertyValues('name') ?? '',
            'overview' => $model->getOverview() ?? '',
            'goals' => $model->getGoals() ?? '',
            'interests_strengths' => $model->getInterestsStrengths() ?? '',
            'needs_challenges' => $model->getNeedsChallenges() ?? '',
            'strategies_adjustments' => $model->getStrategiesAdjustments() ?? '',
        ];

        return $this->profileConfig->reduce(function ($return, \Schoolbox\Admin\DifferentiationProfiles\Model $config) use ($profile) {
            $key = $config->getName();
            $description = $config->getDescription();
            if (array_key_exists($key, $profile)) {
                $return['field'][$key] = [
                    'field_description' => $description,
                    'field_value' => $profile[$key]
                ];
            }
            return $return;
        }, []);
    }
}
