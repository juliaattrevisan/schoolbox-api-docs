<?php
/**
 * Availability
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Stk\NCEA;

use Alaress\Database\Connector\IConnector;


class Availability
{
    /** @var IConnector */
    private $externalDb;

    /**
     * Availability constructor.
     *
     * @param IConnector $externalDb
     */
    public function __construct(IConnector $externalDb)
    {
        $this->externalDb = $externalDb;
    }

    /**
     * Whether the report is available at all
     *
     * @param int $yearLevel
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    public function hasAccess(int $yearLevel): bool
    {
        $query = '
            SELECT * 
            FROM uluShowNCEAReport
            WHERE YearLevel = :yearLevel
            AND ShowOnOLE = 1
        ';

        $statement = $this->externalDb->prepareQuery($query);

        $statement->execute([
            'yearLevel' => $yearLevel
        ]);

        $row = $statement->fetch();

        return \is_array($row);
    }
}