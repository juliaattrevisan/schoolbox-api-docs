<?php
/**
 * TranslatorRegistry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\User;
use Schoolbox\Resource\Folder;

class TranslatorRegistry implements ITranslate
{

    /**
     * @var array
     */
    private $registry;


    /**
     * Registry constructor.
     * @param User          $user
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(User $user, Folder\Mapper $folderMapper)
    {
        $this->registry = [
            $campusSql = new Strategy\Campus(),
            $roleSql = new Strategy\Role(),
            $folderSql = new Strategy\Folder($user, $folderMapper),
            $houseSql = new Strategy\House(),
            $userSql = new Strategy\User(),
            $yearSql = new Strategy\Year(),
            $pastoralGroupSql = new Strategy\PastoralGroup()
        ];
    }


    /**
     * Translate this ITranslate instance from an entity_type to a human-readable version
     *
     * @return string
     */
    public function getTranslateSql(): string
    {
        $pieces = [];

        foreach ($this->registry as $strategy) {
            $pieces[] = "({$strategy->getTranslateSql()})";
        }

        return implode(' UNION ', $pieces);
    }
}
