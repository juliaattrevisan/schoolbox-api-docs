<?php
/**
 * Builder for a node list to be output by jsTree.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;

use Group as CategoryHandler;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Resource\Folder\Mapper;

/**
 * Class CategoryTreeBuilder
 *
 * @deprecated in favour of Resource\Folder\TreeBuilder, this class retained for backward compatibility
 * @package Schoolbox\Resource
 */
class CategoryTreeBuilder extends Folder\TreeBuilder
{

    /**
     * @deprecated
     * @param Mapper     $mapper
     * @param TaskMapper $taskMapper
     * @param \Group     $handler
     */
    public function __construct(Mapper $mapper, TaskMapper $taskMapper, CategoryHandler $handler)
    {
        parent::__construct($mapper, $taskMapper, $handler);
    }

}
