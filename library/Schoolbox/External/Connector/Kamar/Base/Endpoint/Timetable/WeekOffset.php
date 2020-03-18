<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Alaress_Date_Format;
use PDOException;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class WeekOffset
 *
 * Calculate the week offset from the start of the academic term
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class WeekOffset
{

    /**
     * @var TimetableCampusMapper
     */
    private $timetableCampusMapper;


    /**
     * WeekOffset constructor.
     *
     * @param TimetableCampusMapper $timetableCampusMapper
     */
    public function __construct(TimetableCampusMapper $timetableCampusMapper)
    {
        $this->timetableCampusMapper = $timetableCampusMapper;
    }


    /**
     * Determine which week we're in, if possible
     * Returns null when it can't be determined
     *
     * @param Alaress_Date_Format $date
     * @return int|null
     * @throws PDOException
     * @throws Exception
     */
    public function findWeekOffset(Alaress_Date_Format $date): ?int
    {

        /* Term goes between Start and Finish inclusively
         *
         * So long as term Start never equal a previous term's Finish
         * this should be okay. If your term (timetable cycle) is set up
         * like this:
         *
         * start: 27/01/2020
         * finish: 20/03/2020
         * current: 20/03/2020
         *
         * Then you're in that term. The next term should be:
         *
         * start: 21/03/2020
         * finish: some other point in the future > 21/03/2020
         */
        $query = "
            SELECT *
            FROM {$this->timetableCampusMapper->getTable()}
            WHERE start <= :currentDate
            AND finish >= :currentDate
            LIMIT 1
        ";

        $statement = $this->timetableCampusMapper->getDatabase()->prepare($query);
        $statement->execute([
            'currentDate' => $date->getDatabaseDate()
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $cycle = $this->timetableCampusMapper->hydrate($row);
        print_r($query);
        print_r($row);
        print_r($cycle);
//        $term = $this->$timetableCycleMapper->findForDate($date);
//
//        /* Term not found
//         */
//        if (!$term->getId()) {
//            return null;
//        }
//
//        $startWeek = (int)date('W', $term->getStart()->getUnixTimestamp());
//        $currentWeek = (int)date('W', $date->getUnixTimestamp());
//
//        /* Include the current week in calculations using a +1 offset
//         * e.g. if term begins on 8/1/20,
//         *     8/1/20, 9/1/20 and 10/1/20 are in week 1
//         *     11/1/20 is in week 2
//         *     17/1/20 is in week 2
//         *     21/2/20 is in week 3
//         */
//        $offset = $currentWeek - $startWeek + 1;
//
//        if ($offset > 0) {
//            return $offset;
//        }

        return null;
    }
}
