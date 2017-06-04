<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class KeyConverter extends Filter\Filters\Assessment\Folder\KeyConverter
{

    /**
     * @var Access
     */
    private $access;


    /**
     * @param Filter\Model $filter
     * @param Collection   $folderIds
     * @param Access       $access
     */
    public function __construct(Filter\Model $filter, Collection $folderIds, Access $access)
    {
        parent::__construct($filter, $folderIds);
        $this->access = $access;
    }


    /**
     * @inheritdoc
     */
    public function sanitiseKey($key, User $user, $page)
    {
        // TODO move this to assertValidKey(). That method will need to take $user and $page as args
        // TODO SA-3532
        switch($page){
            case Filter\Page\Model::PAGE_MARK_BOOK:
            case Filter\Page\Model::PAGE_PENDING_ASSESSMENTS:
                $folderMapper = Folder\Mapper::getInstance();
                $folder       = $folderMapper->createModel((int)$key);
                if(!$this->access->isInstructor($user, $folder)){
                    throw new \InvalidArgumentException();
                }
                break;
        }
        return parent::sanitiseKey($key, $user, $page);
    }
}
