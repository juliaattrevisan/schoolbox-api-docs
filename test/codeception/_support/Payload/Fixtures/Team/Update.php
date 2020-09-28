<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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