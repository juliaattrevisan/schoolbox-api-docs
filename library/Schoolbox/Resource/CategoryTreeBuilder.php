<?php
/**
 * Builder for a node list to be output by jsTree.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\User;

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
     * @param Mapper           $mapper
     * @param TaskMapper       $taskMapper
     * @param UserAccessMapper $userAccessMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param User             $user
     */
    public function __construct(Mapper $mapper, TaskMapper $taskMapper, $userAccessMapper, $roleAccessMapper, $user)
    {
        parent::__construct($mapper, $taskMapper, $userAccessMapper, $roleAccessMapper, $user);
    }

}
