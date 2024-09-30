<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Course\DataModel\Specification\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
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
        protected Generator $routeGenerator,
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
            'frameWorkId' => $courseSpec->getTemplate()?->getId(),
            'folder'      => call_user_func($this->folderTranslator, $courseSpec->getFolder()),
            '_links'      => [
                'view' => $this->getRouteIfAuthorized(
                    'course.modifyCourse',
                    ['courseSpec' => $courseSpec, 'id' => $courseSpec->getId()],
                ),
                'delete' => $this->getRouteIfAuthorized(
                    'course.deleteCourseSpecification',
                    ['courseSpec' => $courseSpec, 'id' => $courseSpec->getId()],
                )
            ],
        ];
    }
}
