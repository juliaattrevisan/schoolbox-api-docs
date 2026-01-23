<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Attachment\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Pastoral\Attachment\Model;
use Schoolbox\User;

/**
 * Model-to-array translator for pastoral attachments.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Storage translator, for translating the storage object.
     *
     * @var callable
     */
    private $storageTranslator;

    /**
     * Constructor
     *
     * @param Generator              $routeGenerator
     * @param Authorizer             $authorizer
     * @param User                   $currentUser
     * @param callable               $storageTranslator
     */
    public function __construct(
        Generator $routeGenerator,
        Authorizer $authorizer,
        User $currentUser,
        callable $storageTranslator,
    ) {
        $this->routeGenerator = $routeGenerator;
        $this->authorizer     = $authorizer;
        $this->currentUser    = $currentUser;
        $this->storageTranslator = $storageTranslator;
    }

    /**
     * Convert this model to an array.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id' => $model->getId(),
            'realname' => $model->getRealname(),
            'storage' => call_user_func($this->storageTranslator, $model->getStorage()),
            'route' => $this->getRouteIfAuthorized(
                'pastoral.getStudentRecordAttachment',
                [
                    'recordId' => $model->getPastoral()->getId(),
                    'studentId' => $model->getPastoral()->getStudent()->getId(),
                    'attachmentId' => $model->getId(),
                ],
            ),
        ];
    }
}
