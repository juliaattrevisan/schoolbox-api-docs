<?php
/**
 * TranslatorRegistry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder;

class TranslatorRegistry implements ITranslate
{

    /**
     * @var ITranslate[]
     */
    private $registry;


    /**
     * Registry constructor.
     *
     * @param User             $user
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $isModerationEnabled
     */
    public function __construct(User $user, Folder\Mapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $isModerationEnabled)
    {
        $this->registry = [
            $campusSql = new Strategy\Campus(),
            $roleSql = new Strategy\Role(),
            $folderSql = new Strategy\Folder($user, $folderMapper, $roleAccessMapper, $isModerationEnabled),
            $houseSql = new Strategy\SchoolHouse(),
            $userSql = new Strategy\User(),
            $yearSql = new Strategy\Year(),
            $pastoralGroupSql = new Strategy\PastoralGroup()
        ];
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $pieces = [];

        foreach ($this->registry as $strategy) {
            $pieces[] = "({$strategy->getTranslateSql($segmentIdsPlaceholder)})";
        }

        return \implode(' UNION ALL ', $pieces);
    }
}
