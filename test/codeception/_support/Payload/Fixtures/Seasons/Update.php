<?php

namespace Payload\Fixtures\Seasons;

use Payload\Request;
use Payload\PostTrait;
use Alaress_Date_Format;

class Update extends Request
{
    use PostTrait;
    
    /* 
     * Request URL:https://vagrant.dev.schoolbox.cloud
     * Referer https://vagrant.dev.schoolbox.cloud/fixtures/seasons/4/edit
     * id
     name	adsgf
     start	04/05/2020FORMAT_DATE_PICKER
     end	23/05/2020
     */
    
    private $seasonID;
    
    public function __construct(\Schoolbox\Fixtures\Season\Model $Season)
    {
        $this->seasonID=$Season->getId();
        $name=$Season->getName();
        $start=date('d/m/Y', $Season->getStart()->getUnixTimeStamp());
        $end=date('d/m/Y',$Season->getEnd()->getUnixTimeStamp());
        
        $this->setReferer('/fixtures/seasons/' . $this->seasonID . '/edit');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        $this->setFormParam('id', $this->seasonID);
        $this->setFormParam('name', $name);
        
        $this->setFormParam('start', $start);
        $this->setFormParam('end', $end);
    }
    
    public function getUrl():string
    {
        return '/fixtures/seasons/'. $this->seasonID;
    }
}