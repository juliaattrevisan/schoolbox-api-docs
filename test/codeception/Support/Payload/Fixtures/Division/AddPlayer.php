<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Division;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;

class AddPlayer extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Division\Model $division;

    public function __construct(\Schoolbox\Fixtures\Division\Model $division, \Schoolbox\Collection $users)
    {
        $this->division = $division;
        $this->setReferer('/fixtures/divisions/' . $this->division->getId() . '/lobby');

        $count = 0;
        foreach ($users as $user) {
            $this->setFormParam('userId[' . $count . ']', $user->getId());
            $count++;
        }
    }

    public function getUrl(): string
    {
        return '/fixtures/divisions/' . $this->division->getId() . '/addPlayers';
    }
}
