<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;

class RemovePlayer extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Division\Model $division;

    public function __construct(\Schoolbox\Fixtures\Division\Model $division, \Schoolbox\User $user)
    {
        $this->division = $division;
        $this->setReferer('/fixtures/divisions/' . $this->division->getId() . '/lobby');

        $this->setFormParam('userId[]', $user->getId());
    }

    public function getUrl(): string
    {
        return '/fixtures/divisions/' . $this->division->getId() . '/removePlayers';
    }
}
