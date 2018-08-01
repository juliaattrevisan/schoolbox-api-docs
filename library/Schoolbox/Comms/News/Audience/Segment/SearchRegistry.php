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

class SearchRegistry implements ISearch
{

    /**
     * @var array
     */
    private $registry;


    /**
     * Registry constructor.
     *
     * @param User      $user
     */
    public function __construct(User $user)
    {
        $this->registry = [
            $campusSql = new Strategy\Campus(),
            $folderSql = new Strategy\Folder(),
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

        foreach($this->registry as $strategy) {
            $pieces[] = $strategy->getSearchSql();
        }

        return implode(' UNION ', $pieces);
    }
}