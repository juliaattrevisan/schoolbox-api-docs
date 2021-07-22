<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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