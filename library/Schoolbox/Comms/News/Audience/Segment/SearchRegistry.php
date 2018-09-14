<?php
/**
 * Registry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder;

class SearchRegistry implements ISearch
{

    /**
     * @var array|ISearch[]
     */
    private $registry;


    /**
     * SearchRegistry constructor.
     *
     * @param User             $user
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $moderationEnabled
     */
    public function __construct(User $user, Folder\Mapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $moderationEnabled)
    {
        $this->registry = [
            $campusSql = new Strategy\Campus(),
            $folderSql = new Strategy\Folder($user, $folderMapper, $roleAccessMapper, $moderationEnabled),
            $houseSql = new Strategy\SchoolHouse(),
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
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $pieces = [];

        /** @var ISearch $strategy */
        foreach ($this->registry as $strategy) {
            $pieces[] = "({$strategy->getSearchSql($searchPlaceholder)})";
        }

        // We can UNION ALL and avoid de-duplication logic as each sub-query has a distinct entity type
        return implode(' UNION ALL ', $pieces);
    }
}
