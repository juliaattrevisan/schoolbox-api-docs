<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\School;

use Payload\Request;
use Payload\PostTrait;

class Create extends Request
{
    use PostTrait;
    
    public function __construct(string $schoolName)
    {
        $this->setReferer('/fixtures/school');
        
        $this->setFormParam('entityId', '');
        $this->setFormParam('name', $schoolName);
    }
    
    public function getUrl():string
    {
        return '/fixtures/school/add';
    }
}