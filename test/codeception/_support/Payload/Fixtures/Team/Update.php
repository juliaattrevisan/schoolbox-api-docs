<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */


/*
 * divisionId	7
teamId	9
name	Its+a+teams
url	http://www.example.com
coach[]	existing:1

Request URL: https://vagrant.dev.schoolbox.cloud/fixtures/teams/9
Referer	https://vagrant.dev.schoolbox.cloud/fixtures/teams/9/edit
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
        $this->setReferer('/fixtures/sports/' . $this->division->getSport()->getId() . '/divisions/new');
        
        $this->setFormParam('sportId', $this->division->getSport()->getId());
        $this->setFormParam('divisionId', $this->division->getId());
        $this->setFormParam('name', $this->division->getName());
        $this->setFormParam('seasonId', $this->division->getSeason()->getId());
    }
    
    public function getUrl():string
    {
        return '/fixtures/divisions/';
    }
}