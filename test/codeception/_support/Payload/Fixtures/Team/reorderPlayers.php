<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Team;

use Payload\Request;
use Payload\PostTrait;

class reorderPlayers extends Request
{
    use PostTrait;
    
    private $team;
    
    public function __construct(\Schoolbox\Fixtures\Team\Model $team)
    {
        $this->team=$team;
        $this->setReferer('/fixtures/divisions/' . $this->team->getDivision()->getId() . '/lobby');
        
        //As the team players are not set for some reason I will be skipping this part.
        /*$count=0;
        foreach ($this->team->getPlayers()->getAll() as $user)
        {
            $this->setFormParam('userId['.$count.']', $user->getId());
            $count++;
        }*/
        
    }
    
    public function manuallyPopulatePlayer(\Schoolbox\User $player, int $array_num)
    {
        $this->setFormParam('userId['.$array_num.']', $player->getId());
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