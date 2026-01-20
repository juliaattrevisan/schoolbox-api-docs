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

    /**
     * @param Generator  $routeGenerator
     * @param Authorizer $authorizer
     * @param User       $currentUser
     * @param Collection $profileConfig
     * @param bool       $includeName   whether to include names for adjustments instead of IDs
     */
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
                        $carry[$config->getName()] = $value->reduce(function (array $arr, $item) {
                            $arr[] = [
                                'id'   => $item->getId(),
                                'name' => $item->getName(),
                            ];
                            return $arr;
                        }, []);
                    }
                } else {
                    $carry[$config->getName()] = $value;
                }
            }
            return $carry;
        }, []);

        return [
            'id'         => $model->getId(),
            'status'     => $this->getStatus($model),
            'student'    => [
                'id'        => $student->getId(),
                'fullName'  => $student->getFullName(),
                'givenName' => $student->getGivenName(),
                'lastName'  => $student->getLastName(),
                'yearLevel' => $student->getYear() ? [
                    'id'   => $student->getYear()->getId(),
                    'name' => $student->getYear()->getName(),
                ] : null,
                '_links'    => [
                    'profile' => $this->getRouteIfAuthorized(
                        'searchUser.getProfile',
                        ['userId' => $student->getId()],
                    ),
                ],
            ],
            ...$config,
            '_routes' => [
                'linkedRecords' => $this->getRouteIfAuthorized(
                    'differentiationProfiles.getLinkedPastoralRecords',
                    ['studentId' => $student->getId()],
                ),
                'create'        => $this->getRouteIfAuthorized(
                    'differentiationProfiles.createDifferentiationProfileData',
                    [
                        'student'   => $student,
                        'studentId' => $student->getId(),
                    ],
                ),
                'edit'          => $this->getRouteIfAuthorized(
                    'differentiationProfiles.putDifferentiationProfileData',
                    [
                        'student'   => $student,
                        'studentId' => $student->getId(),
                    ],
                ),
            ],
            'created_at' => $model->getCreatedAt()?->getShort(),
            /* Have not decided to include created_by full details
             * so just returning the full name for now
             * TODO: update if needed
             */
            'created_by' => $model->getCreatedBy()?->getFullName(),
            'updated_at' => $model->getUpdatedAt()?->getShort(),
            /* Have not decided to include updated_by full details
             * so just returning the full name for now
             * TODO: update if needed
             */
            'updated_by' => $model->getUpdatedBy()?->getFullName(),
            'deleted_at' => $model->getDeletedAt()?->getShort(),
        ];
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
