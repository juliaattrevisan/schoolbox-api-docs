<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/*
 * returnUrl	aHR0cHM6Ly92YWdyYW50LmRldi5zY2hvb2xib3guY2xvdWQvZml4dHVyZXMvc3BvcnRzLzQ=
sportId	4
divisionId	
name	Bam
seasonId	5
url	http://www.example.com

Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/divisions/
Referer https://vagrant.dev.schoolbox.cloud/fixtures/sports/4/divisions/new
*/

namespace Payload\Fixtures\Division;

use Payload\Request;
use Payload\PostTrait;

class Create extends Request
{
    use PostTrait;
    
    private $sport;
    private $season;
    
    public function __construct(string $divisionName, \Schoolbox\Fixtures\Sport\Model $sport, \Schoolbox\Fixtures\Season\Model $season)
    {
        $this->sport=$sport;
        $this->season=$season;
        $this->setReferer('/fixtures/sports/' . $this->sport->getId() . '/divisions/new');
        
        $this->setFormParam('sportId', $sport->getId());
        $this->setFormParam('divisionId', '');
        $this->setFormParam('name', $divisionName);
        $this->setFormParam('seasonId', $this->season->getId());
        $this->setFormParam('url', 'http://www.example.com');
    }
    
    public function getUrl():string
    {
        return '/fixtures/divisions/';
    }
}