<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Fixtures\Seasons;

use Payload\Request;
use Payload\PostTrait;

class ChangeState extends Request
{
    use PostTrait;
    
    const ARCHIVE = 'archive';
    const RESTORE = 'unArchive';
    const DELETE = 'delete';
    
    private $action;
    private $seasonID;
    
    public function __construct(\Schoolbox\Fixtures\Season\Model $Season, string $action)
    {
        $this->seasonID=$Season->getId();
        $this->action=$action;
        $this->setReferer('/fixtures/seasons');
        $this->setHeader('X-Requested-With', 'XMLHttpRequest');
    }
    
    public function getUrl():string
    {
        return '/fixtures/seasons/' . $this->seasonID . '/' . $this->action;
    }
}