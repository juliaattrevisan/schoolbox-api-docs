<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Sport;

use Payload\Request;
use Payload\PostTrait;

class Create extends Request
{
    use PostTrait;

    public function __construct(string $sportsName)
    {
        $this->setReferer('/fixtures/sports/new');

        $this->setFormParam('id', '');
        $this->setFormParam('name', $sportsName);
        $this->setFormParam('url', 'http://www.example.com');
        $this->setFormParam('positions[sportId][0]', '');
        $this->setFormParam('positions[sportId][1]', '');
        $this->setFormParam('positions[sportId][2]', '');
        $this->setFormParam('positions[sportId][3]', '');
        $this->setFormParam('positions[sportId][4]', '');
        $this->setFormParam('positions[id][0]', '');
        $this->setFormParam('positions[id][1]', '');
        $this->setFormParam('positions[id][2]', '');
        $this->setFormParam('positions[id][3]', '');
        $this->setFormParam('positions[id][4]', '');
        $this->setFormParam('positions[name][0]', 'Position 1');
        $this->setFormParam('positions[name][1]', 'Position 2');
        $this->setFormParam('positions[name][2]', 'Position 3');
        $this->setFormParam('positions[name][3]', 'Position 4');
        $this->setFormParam('positions[name][4]', 'Position 5');
    }

    public function getUrl(): string
    {
        return '/fixtures/sports/';
    }
}
