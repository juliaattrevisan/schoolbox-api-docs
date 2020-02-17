<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Alaress\Authorizer\Authorizer;
use Aura\Router;
use Custom\Tigs\Summative\StudentAttributes;
use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Builds a suitable data structure for rendering the AAM components
 */
class AcademicAchievementMatrix extends AbstractMatrix
{

    /**
     * @param StudentAttributes $attributes
     * @param GenericCollection $unitSpecs
     * @param User              $student
     * @param Folder\Model      $folder
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $aamWorkTypeId
     * @param RubricDataSource  $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     * @param string|null       $roleTypeOverride
     * @throws InvalidArgumentException
     */
    public function __construct(
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        User $student,
        Folder\Model $folder,
        Visibility $visibility,
        User $viewer,
        int $aamWorkTypeId,
        RubricDataSource $rubricMapper,
        Authorizer $authorizer,
        Router\Router $router,
        ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $unitSpecs, $student, $folder, $visibility, $viewer, $aamWorkTypeId, $rubricMapper, $authorizer, $router, $roleTypeOverride);
    }
}
