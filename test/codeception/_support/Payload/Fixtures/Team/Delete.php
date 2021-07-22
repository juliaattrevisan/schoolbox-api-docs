<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Team;

use Payload\Request;
use Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;
    
    private $team;
    
    public function __construct(\Schoolbox\Fixtures\Team\Model $team)
    {
        $this->team=$team;
        $this->setReferer('/fixtures/sports/' . $this->team->getDivision()->getSport()->getId());
        
    }
    
    public function getUrl():string
    {
        return '/fixtures/teams/' . $this->team->getId() . '/delete';
    }
}