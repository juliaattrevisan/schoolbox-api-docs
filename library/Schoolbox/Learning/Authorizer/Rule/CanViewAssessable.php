<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;
use InvalidArgumentException;

class CanViewAssessable extends AbstractAssessableRule
{

    /** @var Visibility */
    private $visibility;

    /** @var bool */
    private $showClosed;


    /**
     * @param Visibility $visibility
     * @param bool       $showClosed
     */
    public function __construct(Visibility $visibility, bool $showClosed = true)
    {
        $this->visibility = $visibility;
        $this->showClosed = $showClosed;
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

        if (!$this->visibility->isVisibleToUser($assessable, $user, $this->showClosed)) {
            throw new Unauthorized("User '{$user->getId()}' is not permitted to see assessment '{$assessable->getId()}'");
        }
    }
}
