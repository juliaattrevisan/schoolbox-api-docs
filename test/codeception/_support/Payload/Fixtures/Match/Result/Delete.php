<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Match\Result;

use Payload\Request;
use Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Result\Model $result;

    public function __construct(\Schoolbox\Fixtures\Result\Model $result)
    {
        $this->result = $result;

        $this->setReferer('/fixtures/matches/' . $this->result->getMatch()->getId() . '/results/45' . $this->result->getId());
    }

    public function getUrl(): string
    {
        return '/fixtures/results/' . $this->result->getId() . '/delete';
    }
}
