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
 * Authorization rule for allowing access to an assessment context.
 *
 * A user must have:
 * * the `IsInstructor` authorization if within an implementation context
 * * the `HasHomepage` authorization if within a spec context
 */
class IsInstructorOrHasHomepageAccess extends Any
{
    /**
     * Find the context within the parameters passed to the rule.
     *
     * @var callable(array): AssessmentContext
     */
    private $contextLocator;

    /**
     * Constructor
     *
     * @param Access                             $access
     * @param callable(array): AssessmentContext $contextLocator
     */
    public function __construct(Access $access, callable $contextLocator)
    {
        $this->contextLocator = $contextLocator;
        parent::__construct(
            new IsInstructor($access, $this->isInstructorFolderLocator(...)),
            new HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                $this->hasHomepageAccessFolderIdLocator(...),
                $access,
            ),
        );
    }

    /**
     * Locator for the `isInstructor` authorizer.
     *
     * @param array $params
     * @return Model
     * @throws InvalidPath if the context from `$params` is a spec context
     */
    private function isInstructorFolderLocator(array $params): Model
    {
        /** @var AssessmentContext $context */
        $context = call_user_func($this->contextLocator, $params);
        if ($context->isSpecificationContext()) {
            throw new InvalidPath('Instructor permission is not applicable to specification contexts');
        }
        return $context->getBaseContext()->getFolder();
    }

    /**
     * Locator for the `HasHomepageAccess` authorizer.
     *
     * @param array $params
     * @return int
     * @throws InvalidPath if the context from `$params` is an impl context
     */
    private function hasHomepageAccessFolderIdLocator(array $params): int
    {
        /** @var AssessmentContext $context */
        $context = call_user_func($this->contextLocator, $params);
        if ($context->isImplementationContext()) {
            throw new InvalidPath('Homepage access permission is not applicable to implementation contexts');
        }
        return $context->getBaseContext()->getFolder()->getId();
    }
}
