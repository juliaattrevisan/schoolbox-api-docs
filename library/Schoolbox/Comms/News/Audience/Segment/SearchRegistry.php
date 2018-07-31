<?php
/**
 * Registry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\IDatabase;

class SearchRegistry implements ISearch
{

    /**
     * @var array
     */
    private $registry;
    
    /**
     * Registry constructor.
     *
     * @param IDatabase $db
     */
    public function __construct(IDatabase $db)
    {
        $this->registry = [
            $campusSql = new Strategy\Campus(),
            $roleSql = new Strategy\Role(),
            $folderSql = new Strategy\Folder($db),
            $houseSql = new Strategy\House(),
            $userSql = new Strategy\User(),
            $yearSql = new Strategy\Year(),
            $pastoralGroupSql = new Strategy\PastoralGroup()
        ];
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