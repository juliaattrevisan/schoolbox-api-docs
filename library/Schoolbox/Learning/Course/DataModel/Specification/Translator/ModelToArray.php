<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Course\DataModel\Specification\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Router;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\Resource\Folder\Translator\IModelToArray;
use Schoolbox\User;

/**
 * Course spec model to array translator.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Router $router,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private IModelToArray $folderTranslator,
    ) {
    }

    /**
     * @param Model $courseSpec
     * @return array
     */
    public function __invoke(Model $courseSpec): array
    {
        return [
            'id'          => $courseSpec->getId(),
            'title'       => $courseSpec->getTitle(),
            'subjectCode' => $courseSpec->getSubjectCode(),
            'folder'      => call_user_func($this->folderTranslator, $courseSpec->getFolder()),
            '_links'      => [
                'modify' => $this->getRouteIfAuthorized(
                    'course.modifyCourse',
                    ['courseSpec' => $courseSpec, 'id' => $courseSpec->getId()],
                ),
            ],
        ];
    }
}
