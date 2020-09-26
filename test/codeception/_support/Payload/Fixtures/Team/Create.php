<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/*
 * divisionId	7
teamId	
name	Its+a+team
url	http://www.example.com
coach[]	existing:1

Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/teams/
https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7/teams/new
*/

namespace Payload\Fixtures\Team;

use Payload\Request;
use Payload\PostTrait;

class Create extends Request
{
    use PostTrait;
    
    private $division;
    
    public function __construct(string $name, \Schoolbox\Fixtures\Division\Model $division)
    {
        $this->division=$division;
        $this->setReferer('/fixtures/divisions/' . $this->division->getId() . '/teams/new');
        
        $this->setFormParam('divisionId', $this->division->getId());
        $this->setFormParam('teamId', '');
        $this->setFormParam('name', $name);
        $this->setFormParam('url', 'http://www.example.com');
        #$this->setFormParam('coach[]', '');
        
    }
    
    public function getUrl():string
    {
        return '/fixtures/teams/';
    }
}