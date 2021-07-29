<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Seasons;

use Payload\Request;
use Payload\PostTrait;

class Update extends Request
{
    use PostTrait;

    private int $seasonID;

    public function __construct(\Schoolbox\Fixtures\Season\Model $Season)
    {
        $this->seasonID = $Season->getId();
        $name = $Season->getName();
        $start = date('d/m/Y', $Season->getStart()->getUnixTimeStamp());
        $end = date('d/m/Y', $Season->getEnd()->getUnixTimeStamp());

        $this->setReferer('/fixtures/seasons/' . $this->seasonID . '/edit');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->setFormParam('id', $this->seasonID);
        $this->setFormParam('name', $name);

        $this->setFormParam('start', $start);
        $this->setFormParam('end', $end);
    }

    public function getUrl(): string
    {
        return '/fixtures/seasons/' . $this->seasonID;
    }
}
