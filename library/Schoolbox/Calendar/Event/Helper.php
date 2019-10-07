<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Schoolbox\Calendar\Event;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Campus;
use Schoolbox\Mapper\Exception;

class Helper
{
    /**
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * Helper constructor.
     * @param RoleMapper $roleMapper
     */
    public function __construct(RoleMapper $roleMapper, FolderMapper $folderMapper)
    {
        $this->roleMapper   = $roleMapper;
        $this->folderMapper = $folderMapper;
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getCampusCollection(Event $event): array
    {
        $result = [];

        /** @var Campus $campus */
        foreach ($event->getCampusCollection()->getAll() as $campus) {
            $result[] = [
                'id' => $campus->getId(),
                'name' => $campus->getName(),
                'extTimetableCode' => $campus->getExtTimetableCode(),
                'timetableDay' => $campus->getTimetableDay(),
            ];
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getRoleCollection(Event $event): array
    {
        $result = [];

        /** @var \Schoolbox\Calendar\Relation\Role $role */
        foreach ($event->getRoleCollection()->getAll() as $role) {
            try {
                $result[] = $this->roleMapper->findById($role->getRoleId());
            } catch (Exception $e) {
                // noop
            }
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return string
     */
    public function getEventLevel(Event $event): ?string
    {
        $result = '';

        if ($event->isCampusLevel()) {
            $result = 'campus';
        } elseif ($event->isPersonalLevel()) {
            $result = 'personal';
        } elseif ($event->isGroupLevel()) {
            $result = 'group';
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getGroupCollection(Event $event): array
    {
        $result = [];

        /** @var \Schoolbox\Calendar\Relation\Category $category */
        foreach ($event->getCategoryCollection()->getAll() as $category) {
            try {
                $folder = $this->folderMapper->findById($category->getCategoryId());

                $result[] = [
                    'name' => $folder->getNameWithCodes(),
                    'id'   => $folder->getId(),
                ];
            } catch (Exception $e) {
                // noop
            }
        }

        return $result;
    }
}
