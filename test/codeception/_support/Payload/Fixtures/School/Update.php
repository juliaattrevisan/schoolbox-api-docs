<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\School;

use Payload\Request;
use Payload\PostTrait;

class Update extends Request
{
    use PostTrait;
    
    private $school;
    
    public function __construct(\Schoolbox\Fixtures\School\Model $school)
    {
        $this->school=$school;
        
        $this->setReferer('/fixtures/school');
        
        $this->setFormParam('entityId', $this->school->getId());
        $this->setFormParam('name', $this->school->getName());
    }
    
    public function setName($newName)
    {
        $this->setFormParam('name', $newName);
    }
    
    public function getUrl():string
    {
        return '/fixtures/school/'.(string)($this->school->getId()).'/edit';
    }
}