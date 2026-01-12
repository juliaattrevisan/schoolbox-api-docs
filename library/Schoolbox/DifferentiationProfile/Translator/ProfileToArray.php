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

    /**
     * Translate a differentiation profile
     * Currently only used to translate into a format ready to be used inside a prompt with AI
     * @param Model $model
     * @param bool $includeEmptyFields Whether fields that are empty should be included
     * @return array
     */
    public function __invoke(Model $model, bool $includeEmptyFields = true): array
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

        return $this->profileConfig->reduce(function ($return, \Schoolbox\Admin\DifferentiationProfiles\Model $config) use ($profile, $includeEmptyFields) {
            $key = $config->getName();
            $description = $config->getDescription();
            if (\array_key_exists($key, $profile) && ($includeEmptyFields || !empty($profile[$key]))) {
                $return['field'][$key] = [
                    'field_description' => $description,
                    'field_value' => $profile[$key]
                ];
            }
            return $return;
        }, []);
    }
}
