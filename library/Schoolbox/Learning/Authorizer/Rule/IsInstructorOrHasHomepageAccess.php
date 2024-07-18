<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule\Any;
use Alaress\Authorizer\Rule\HasHomepageAccess;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model;

/**
 * Class IsInstructorOrHasHomepageAccess
 */
class IsInstructorOrHasHomepageAccess extends Any
{
    /**
     * Find the context within the params.
     *
     * @var callable(array): AssessmentContext
     */
    private $contextLocator;

    public function __construct(Access $access, callable $contextLocator)
    {
        $this->contextLocator = $contextLocator;
        parent::__construct(
            new IsInstructor($access, $this->isInstructorContextLocator(...)),
            new HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                $this->hasHomepageAccessFolderIdLocator(...),
                $access,
            ),
        );
    }

    private function isInstructorContextLocator(array $params): Model
    {
        /** @var AssessmentContext $context */
        $context = call_user_func($this->contextLocator, $params);
        if ($context->isSpecificationContext()) {
            // This rule is not applicable to spec contexts
            throw new InvalidPath('Instructor permission is not applicable to specification contexts');
        }
        return $context->getBaseContext()->getFolder();
    }


    private function hasHomepageAccessFolderIdLocator(array $params): int
    {
        /** @var AssessmentContext $context */
        $context = call_user_func($this->contextLocator, $params);
        if (!$context->isSpecificationContext()) {
            // This rule is not applicable to spec contexts
            throw new InvalidPath('Homepage access permission is not applicable to implementation contexts');
        }
        return $context->getBaseContext()->getFolder()->getId();
    }
}
