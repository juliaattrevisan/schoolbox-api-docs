<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Router;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;
use Schoolbox\Resource\Folder\Translator\IModelToArray as FolderTranslator;
use Schoolbox\User;

/**
 * Unit spec model to array translator.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Router $router,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private FolderTranslator $folderTranslator,
        private CourseSpecification\Translator\ModelToArray $courseTranslator
    ) {
    }

    /**
     * @param CourseSpecification\Unit\Model $unitSpec
     * @return array
     */
    public function __invoke(CourseSpecification\Unit\Model $unitSpec): array
    {
        return [
            'id'     => $unitSpec->getId(),
            'title'  => $unitSpec->getTitle(),
            'folder' => call_user_func($this->folderTranslator, $unitSpec->getFolder()),
            'course' => call_user_func($this->courseTranslator, $unitSpec->getCourse()),
            '_links' => [
                'read' => $this->getRouteIfAuthorized(
                    'course.modifyCourse',
                    ['courseSpec' => $unitSpec, 'id' => $unitSpec->getId()],
                ),
            ],
        ];
    }
}
