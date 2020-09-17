<?php
/*
 * https://vagrant.dev.schoolbox.cloud/fixtures/sports/4/delete
 * 
 * referer
 * 	
https://vagrant.dev.schoolbox.cloud/fixtures/sports
 */
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Sport;

use Payload\Request;
use Payload\PostTrait;

class Delete extends Request
{
    use PostTrait;
    
    private $sport;
    
    public function __construct(\Schoolbox\Fixtures\Sport\Model $sport)
    {
        $this->setReferer('/fixtures/sports');
        $this->sport=$sport;
    }
    
    public function getUrl():string
    {
        return '/fixtures/sports/'.$this->sport->getId().'/delete';
    }
}