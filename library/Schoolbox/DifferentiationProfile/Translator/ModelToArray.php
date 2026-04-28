<?php

namespace Schoolbox\DifferentiationProfile\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Assessment\Participant\Model as CustomisationRecord;
use Schoolbox\Collection;
use Schoolbox\DifferentiationProfile\Mapper;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\Admin\DifferentiationProfiles\Model as Config;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * @param Generator       $routeGenerator
     * @param Authorizer      $authorizer
     * @param User            $currentUser
     * @param Collection      $profileConfig
     * @param Collection|null $customisationsByStudent
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private Collection $profileConfig,
        private ?Collection $customisationsByStudent = null,
    ) {
    }

    public function __invoke(Model $model): array
    {
        $student = $model->getStudent();
        /* If the customisations collection is already prefetched then we can get the student's
         * customisations from that collection and pass them through to the CustomisationRecord
         * route authorizer, bypassing the query that authorizer callback would normally perform.
         * If not, return null so the existing authorizer flow can do its usual lookup.
         */
        $customisations = null;
        if ($this->customisationsByStudent !== null) {
            $customisations = $this->customisationsByStudent->getOffset($student->getId())
                ?? new GenericCollection(CustomisationRecord::class);
        }

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
                'campus'    => $student->getCampusCollection()?->reduce(function (array $arr, $campus) {
                    $arr[] = [
                        'id'   => $campus->getId(),
                        'name' => $campus->getName(),
                    ];
                    return $arr;
                }, []),
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
                'customisations' => $this->getRouteIfAuthorized(
                    'customisationRecord.viewRecordByStudent',
                    [
                        'studentId' => $student->getId(),
                        'customisations' => $customisations,
                    ],
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
            'created_at' => $model->getCreatedAt()?->toRfc3339String(),
            /* Have not decided to include created_by full details
             * so just returning the full name for now
             * TODO: update if needed
             */
            'created_by' => $model->getCreatedBy()?->getFullName(),
            'updated_at' => $model->getUpdatedAt()?->toRfc3339String(),
            /* Have not decided to include updated_by full details
             * so just returning the full name for now
             * TODO: update if needed
             */
            'updated_by' => $model->getUpdatedBy()?->getFullName(),
            'deleted_at' => $model->getDeletedAt()?->toRfc3339String(),
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

        if ($model->isExpired()) {
            $status[] = 'expired';
        }
        if ($model->isOutOfDate()) {
            $status[] = 'outOfDate';
        }
        if (!$model->isPopulated()) {
            $status[] = 'unpopulated';
        }

        return $status;
    }
}
