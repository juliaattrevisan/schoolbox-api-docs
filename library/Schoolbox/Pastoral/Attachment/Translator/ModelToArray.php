<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Attachment\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Alaress\HTMLPurifier\IPurifier;
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
     * Constructor
     *
     * @param Generator  $routeGenerator
     * @param Authorizer $authorizer
     * @param User       $currentUser
     */
    public function __construct(
        Generator $routeGenerator,
        Authorizer $authorizer,
        User $currentUser,
    ) {
        $this->routeGenerator = $routeGenerator;
        $this->authorizer     = $authorizer;
        $this->currentUser    = $currentUser;
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
            'storage' => [
                'filename' => $model->getStorage()->getFilename(),
                'mimetype' => [
                    'isImage' => $model->getStorage()->getMimetype()->isImage(),
                ],
                'mime' => $model->getStorage()->getMime(),
                'hash' => $model->getStorage()->getHash(),
            ],
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
