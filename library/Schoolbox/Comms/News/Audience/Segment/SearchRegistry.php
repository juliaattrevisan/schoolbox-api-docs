<?php
/**
 * Registry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\User;
use Schoolbox\Resource\Folder;

class SearchRegistry implements ISearch
{

    /**
     * @var array|ISearch[]
     */
    private $registry;


    /**
     * These entity types can have parents_of and teachers_of modifiers
     * Users don't have modifiers
     *
     * @var array
     */
    private $modifierRegistry = [
        'campus', 'folder', 'school_house', 'year', 'role', 'pastoral_group'
    ];


    /**
     * Registry constructor.
     *
     * @param User          $user
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(User $user, Folder\Mapper $folderMapper)
    {
        $this->registry = [
            $campusSql = new Strategy\Campus(),
            $folderSql = new Strategy\Folder($user, $folderMapper),
            $houseSql = new Strategy\House(),
        ];

        /* Staff-only search capability
         */
        if ($user->getRole()->isStaff()) {
            $this->registry = array_merge($this->registry, [
                $yearSql = new Strategy\Year(),
                $roleSql = new Strategy\Role(),
                $userSql = new Strategy\User(),
                $pastoralGroupSql = new Strategy\PastoralGroup(),
            ]);
        }
    }

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        $pieces = [];

        /** @var ISearch $strategy */
        foreach ($this->registry as $strategy) {
            $pieces[] = "({$strategy->getSearchSql()})";
        }

        return implode(' UNION ', $pieces);
    }


    /**
     * Decide whether $entityType has the modifiers parents_of and teachers_of
     *
     * @param string $entityType
     * @return bool
     */
    public function hasModifier(string $entityType): bool
    {
        return \in_array($entityType, $this->modifierRegistry, true);
    }
}
