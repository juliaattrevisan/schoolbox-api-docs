<?php

/**
 * Availability
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Sk\NCEA;

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
     * @param int $yearLevel
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function findMeta(int $yearLevel): array
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

        /* Discard any other unconsumed data to avoid leaving the connection hanging
         */
        $statement->close();

        if (\is_array($row)) {
            return $row;
        }

        return [];
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
        return (bool)\count($this->findMeta($yearLevel));
    }

    /**
     * A special database-controlled text field that displays in the report
     * This used to be hardcoded
     *
     * @param int $yearLevel
     * @return string
     * @throws \Alaress\Database\Exception
     */
    public function findReportComment(int $yearLevel): string
    {
        $row = $this->findMeta($yearLevel);

        if (\count($row)) {
            return $row['ResultComment'];
        }

        return '';
    }
}
