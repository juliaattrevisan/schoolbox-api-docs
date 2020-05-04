<?php 

namespace Payload\Fixtures\Seasons;

use Payload\Request;
use Payload\PostTrait;

class Create extends Request
{
    use PostTrait;
    
    /* Referer https://vagrant.dev.schoolbox.cloud/fixtures/seasons/new
    * id
    name	adsgf
    start	04/05/2020
    end	23/05/2020
    */
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