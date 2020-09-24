<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/*
 * userId[]	[…]
0	22
1	25

Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/teams/9/reorderPlayers
https://vagrant.dev.schoolbox.cloud/fixtures/divisions/7/lobby
*/

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;

class reorderPlayer extends Request
{
    use PostTrait;
    
    private $team;
    
    public function __construct(\Schoolbox\Fixtures\Team\Model $team)
    {
        $this->team=$team;
        $this->setReferer('/fixtures/divisions/' . $this->team->getDivision()->getId() . '/lobby');
        
        $count=0;
        foreach ($team->getPlayers() as $user)
        {
            $this->setFormParam('userId['.$count.']', $user->getId());
            $count++;
        }
        
    }
    
    public function swapPlayerAwithB(int $order_posA, int $order_posB)
    {
        $playerSwapA = $this->getFormParams('userId['.$order_posA.']');
        $playerSwapB = $this->getFormParams('userId['.$order_posB.']');
        if($playerSwapA && $playerSwapB)
        {
            $this->setFormParam('userId['.$order_posA.']', $playerSwapB);
            $this->setFormParam('userId['.$order_posB.']', $playerSwapA);
        }
    }
    
    public function getUrl():string
    {
        return '/fixtures/teams/' . $this->team->getId() . '/reorderPlayers';
    }
}