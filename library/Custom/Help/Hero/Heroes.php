<?php
/**
 * Heroes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

class Heroes
{

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * Homepage id for the Hero Wall
     *
     * @var int
     */
    private $heroFolderId = 2209;

    /**
     * Heroes constructor.
     *
     * @param Folder\Mapper    $folderMapper
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(Folder\Mapper $folderMapper, UserAccessMapper $userAccessMapper)
    {
        $this->folderMapper = $folderMapper;
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * Find a list of Heroes already on the Wall
     * /homepage/2209
     *
     * @return Collection
     * @throws \DomainException
     */
    public function findHeroes(): Collection
    {
        try {
            $folder = $this->folderMapper->findById($this->heroFolderId);
            $users = $this->userAccessMapper->findUsersInCategory($folder);
        }
        catch (Mapper\Exception $e) {
            throw new \DomainException('Could not obtain Hero Wall folder id');
        }

        return $users;
    }
}