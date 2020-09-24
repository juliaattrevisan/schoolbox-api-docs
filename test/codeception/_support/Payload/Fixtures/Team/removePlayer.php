<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/*
 * userId[]	25

Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/teams/9/removePlayers
https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7/lobby
 */

namespace Payload\Fixtures\Team;

use Payload\Request;
use Payload\PostTrait;

class removePlayer extends Request
{
    use PostTrait;
    
    private $team;
    
    public function __construct(\Schoolbox\Fixtures\Team\Model $team, \Schoolbox\User $user)
    {
        $this->team=$team;
        $this->setReferer('/fixtures/divisions/' . $this->team->getDivision()->getId() . '/lobby');
        
        $this->setFormParam('userId[]', $user->getId());
        
    }
    
    public function getUrl():string
    {
        return '/fixtures/teams/' . $this->team->getId() . '/removePlayers';
    }
}