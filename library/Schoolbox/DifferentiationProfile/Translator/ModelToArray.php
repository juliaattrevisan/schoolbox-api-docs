<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\DifferentiationProfile\Mapper;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\Admin\DifferentiationProfiles\Model as Config;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as ConfigMapper;
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
            'status'  => $this->getStatus($model),
            'created_at' => $model->getCreatedAt()?->getShort(),
            'updated_at' => $model->getUpdatedAt()?->getShort(),
            'deleted_at' => $model->getDeletedAt()?->getShort(),
        ];

        $configKey = Mapper::CONFIG_METHOD_MAPPING;

        /* filtering out and only get the enabled config values
         */
        $config = $this->profileConfig->reduce(function (array $carry, Config $config) use ($model, $configKey) {
            if (array_key_exists($config->getName(), $configKey)) {
                $value = $model->{$configKey[$config->getName()]}();

                // Convert comma-delimited strings to arrays for adjustment fields
                if ($config->getModule() === ConfigMapper::MODULE_ADJUSTMENTS) {
                    if ($value->count() === 0) {
                        $carry[$config->getName()] = null;
                    } else {
                        $carry[$config->getName()] = array_map('intval', $value->getPropertyValues('id'));
                    }
                } else {
                    $carry[$config->getName()] = $value;
                }
            }
            return $carry;
        }, []);
        return array_merge($return, $config);
    }

    /**
     * Get status array for the given differentiation profile
     *
     * @param Model $model
     * @return array
     */
    private function getStatus(Model $model): array
    {
        $status = [];

        if ($model->getExpired()) {
            $status[] = 'expired';
        }
        if ($model->getOutOfDate()) {
            $status[] = 'outOfDate';
        }

        if (!$model->isPopulated()) {
            $status[] = 'unpopulated';
        }

        return $status;
    }
}
