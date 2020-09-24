<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/*
 * returnUrl	aHR0cHM6Ly92YWdyYW50LmRldi5zY2hvb2xib3guY2xvdWQvZml4dHVyZXMvc3BvcnRzLzQ=
sportId	4
divisionId	7
name	Bams
seasonId	5
url	http://www.example.com

Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7
https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7/edit
*/

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;

class Update extends Request
{
    use PostTrait;
    
    private $division;
    
    public function __construct(\Schoolbox\Fixtures\Division\Model $division)
    {
        $this->division=$division;
        $this->setReferer('/fixtures/divisions/' . $this->division->getId() . '/edit');
        
        $this->setFormParam('sportId', $this->division->getSport()->getId());
        $this->setFormParam('divisionId', $this->division->getId());
        $this->setFormParam('name', $this->division->getName());
        $this->setFormParam('seasonId', $this->division->getSeason()->getId());
        $this->setFormParam('url', $this->division->getUrl());
    }
    
    public function getUrl():string
    {
        return '/fixtures/divisions/' . $this->division->getId();
    }
}