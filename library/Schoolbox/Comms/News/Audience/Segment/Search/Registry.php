<?php
/**
 * Registry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Search;

use Alaress\IDatabase;


class Registry implements ISearch
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
            $campusSql = new Campus(),
            $roleSql = new Role(),
            $folderSql = new Folder($db),
            $houseSql = new House(),
            $userSql = new User(),
            $yearSql = new Year(),
            $pastoralGroupSql = new PastoralGroup()
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