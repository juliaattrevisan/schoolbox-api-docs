<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/**
 * No conversion. A int is converted to the same int (representing a folder.id)
 */
abstract class KeyConverter extends AbstractKeyConverter
{

    /**
     * @var Access
     */
    private $access;

    /**
     * All Folders IDs known to the system (regardless of whether they are available)
     * (using IDs instead of Models as too slow for no value)
     *
     * @var Collection|int[]
     */
    private $folderIds;


    /**
     * @param Filter\Model $filter
     * @param Collection   $folderIds
     * @param Access       $access
     */
    public function __construct(Filter\Model $filter, Collection $folderIds, Access $access)
    {
        parent::__construct($filter);
        $this->folderIds = $folderIds->shallowClone();
        $this->access    = $access;
    }


    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @inheritdoc
     * @return int
     */
    public function getItem(string $key, \Schoolbox\User $user, string $page): int
    {
        $this->assertValidKey($key, $user, $page);
        return $this->folderIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems()
    {
        return $this->folderIds->shallowClone();
    }

    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {

        if (!$this->folderIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No Folder ID found for key: $key");
        }
        if ($this->access->getFolderAccess($user, (int)$key) < UserAccess::ACCESS_READ) {
            throw new \InvalidArgumentException("Session user lacks read perm to folder: $key");
        }
    }
}
