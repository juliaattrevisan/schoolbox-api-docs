<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * No conversion. A int is converted to the same int (representing a folder.id)
 */
class KeyConverter extends AbstractKeyConverter
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
    public function getItem(string $key, User $user, string $page): int
    {
        $this->assertValidKey($key, $user, $page);
        return $this->folderIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->folderIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!$this->folderIds->offsetExists($key)) {
            throw new InvalidArgumentException("No Folder ID found for key: $key");
        }

        switch ($page) {
            case Filter\Page\Model::PAGE_CLASS_MARK_BOOK:
            case Filter\Page\Model::PAGE_COURSE_MARK_BOOK:
                // Must be staff or instructor
                $notStaff = !$user->getRole()->isStaff();
                if ($notStaff && $this->access->getFolderAccess($user, (int)$key) < UserAccess::ACCESS_WRITE) {
                    throw new InvalidArgumentException("Session user lacks read perm to folder: $key");
                }
                break;
            case Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS:
            default:
                // No perm check
                break;
        }
    }
}
