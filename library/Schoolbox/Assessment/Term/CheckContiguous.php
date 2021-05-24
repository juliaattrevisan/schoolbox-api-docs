<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Term;

/**
 * Class CheckContiguous
 *
 * Performs a quick and dirty analysis of your Term instances to ensure that
 * Ordinary Terms are always contiguous and aren't getting affected by
 * Extraordinary Terms (which have Year Level relations and don't obey the
 * same date rules as their Ordinary counterparts)
 *
 * Due Works etc. all rely on being in a particular Term to show up in Markbooks
 * and stuff like that. Without a Term to help define their existence they can
 * become inaccessible
 *
 * @package Schoolbox\Assessment\Term
 */
class CheckContiguous
{

    /**
     * @var Mapper
     */
    private Mapper $mapper;


    /**
     * CheckContiguous constructor.
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * Generate a quick and dirty report about Ordinary Term continuity
     *
     * @throws \PDOException
     * @throws \DebugBar\DebugBarException
     * @throws \InvalidArgumentException
     */
    public function analyse(): void
    {
        $query = "
            SELECT *
            FROM {$this->mapper->getTable()}
            LEFT JOIN term_year_level tyl ON {$this->mapper->getTable()}.{$this->mapper->getIdentifierColumn()} = tyl.term_id
            WHERE tyl.term_id IS NULL
            ORDER BY `start`
        ";

        $statement = $this->mapper->getDatabase()->prepare($query);
        $statement->execute();

        $contiguous = true;
        $previous = null;

        new \Alaress_Probe('Title', 'Start', 'Actual Finish', 'Diff');
        new \Alaress_Probe('<hr>');

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if ($previous) {
                $d1   = new \Alaress_Date_Format($previous['actual_finish'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);
                $d2   = new \Alaress_Date_Format($row['start'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);
                $diff = $d2->diffInDays($d1);

                new \Alaress_Probe((string)$row['title'], (string)$row['start'], (string)$row['actual_finish'], $diff);

                if ($diff > 1) {
                    $contiguous = false;
                }
            } else {
                new \Alaress_Probe((string)$row['title'], (string)$row['start'], (string)$row['actual_finish'], '');
            }

            $previous = $row;
        }

        if (!$contiguous) {
            new \Alaress_Probe("<strong style='color:#d00'>Not Contiguous</strong>");
        } else {
            new \Alaress_Probe("<strong style='color:#0d0'>Contiguous</strong>");
        }
    }
}
