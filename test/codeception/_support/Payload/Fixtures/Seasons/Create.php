<?php 
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Seasons;

use Payload\Request;
use Payload\PostTrait;

class Create extends Request
{
    use PostTrait;
    
    public function __construct(string $seasonName)
    {
        $this->setReferer('/fixtures/seasons/new');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        $this->setFormParam('id', '');
        $this->setFormParam('name', $seasonName);
        
        $creationTime = time();
        
        $this->setFormParam('start', date('d/m/Y', strtotime('- 3 months', $creationTime)));
        $this->setFormParam('end', date('d/m/Y', strtotime('+ 3 months', $creationTime)));
    }
    
    public function getUrl():string
    {
        return '/fixtures/seasons/';
    }
}