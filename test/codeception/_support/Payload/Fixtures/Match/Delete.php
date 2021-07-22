<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Match;

use Payload\Request;
use Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;
    
    var $match;
    
    public function __construct(\Schoolbox\Fixtures\Match\Model $match)
    {
        $this->match=$match;
        
        $this->setReferer('/fixtures/divisions/' . $this->match->getDivision()->getId() . '/matches' );
        
    }
    
    public function getUrl():string
    {
        return '/fixtures/matches/' . $this->match->getId() . '/delete';
    }
}