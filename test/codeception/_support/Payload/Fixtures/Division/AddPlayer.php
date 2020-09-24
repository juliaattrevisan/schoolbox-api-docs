<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/*
 * userId[]	[…]
0	22
1	25

Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7/addPlayers
https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7/lobby
*/

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;

class AddPlayer extends Request
{
    use PostTrait;
    
    private $division;
    
    public function __construct(\Schoolbox\Fixtures\Division\Model $division, \Schoolbox\Collection $users)
    {
        $this->division=$division;
        $this->setReferer('/fixtures/divisions/' . $this->division->getId() . '/lobby');
        
        $count=0;
        foreach ($users as $user)
        {
            $this->setFormParam('userId['.$count.']', $user->getId());
            $count++;
        }
        
    }
    
    public function getUrl():string
    {
        return '/fixtures/divisions/' . $this->division->getId() . '/addPlayers';
    }
}