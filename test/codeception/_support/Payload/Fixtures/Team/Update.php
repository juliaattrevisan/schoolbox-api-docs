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
 
namespace Payload\Fixtures\Team;

use Payload\Request;
use Payload\PostTrait;

class Update extends Request
{
    use PostTrait;
    
    private $team;
    
    public function __construct(\Schoolbox\Fixtures\Team\Model $team)
    {
        $this->team=$team;
        $this->setReferer('/fixtures/teams/' . $this->team->getId() . '/edit');
        
        $this->setFormParam('teamId', $this->team->getId());
        $this->setFormParam('divisionId', $this->team->getDivision()->getId());
        $this->setFormParam('name', $this->team->getName());
        #$this->setFormParam('coach[]', '');
    }
    
    public function getUrl():string
    {
        return '/fixtures/teams/' . $this->team->getId();
    }
}