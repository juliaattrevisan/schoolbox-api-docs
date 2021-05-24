<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;
use InvalidArgumentException;
use Alaress_Date_Format;

class CanViewAssessable extends AbstractAssessableRule
{

    /** @var Visibility */
    private $visibility;

    /** @var bool */
    private $showClosed;

    /** @var Alaress_Date_Format */
    private $now;


    /**
     * @param Visibility               $visibility
     * @param bool                     $showClosed
     * @param Alaress_Date_Format|null $now
     */
    public function __construct(Visibility $visibility, bool $showClosed = true, Alaress_Date_Format $now = null)
    {
        $this->visibility = $visibility;
        $this->showClosed = $showClosed;
        $this->now        = $now;
    }


    /**
     * @inheritdoc
     * @throws InvalidArgumentException If no assessable found in route params
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new Unauthorized("A user is required to perform this auth check");
        }

        $assessable = $this->findAssessable($params);

        // Can the user see the assessment because of their own relationship?
        if ($this->visibility->isVisibleToUser($assessable, $user, $this->showClosed, $this->now)) {
            return;
        }

        // Can the user see the assessment because their child can?
        if ($user->getRole()->isParent()) {
            foreach ($user->getChildren() as $child) {
                if ($this->visibility->isVisibleToUser($assessable, $child, $this->showClosed, $this->now)) {
                    return;
                }
            }
        }
        throw new Unauthorized("User '{$user->getId()}' is not permitted to see assessment '{$assessable->getId()}'");
    }
}
