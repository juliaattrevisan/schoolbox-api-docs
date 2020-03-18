<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\TimetableCampus;
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
     * @var Alaress_Date_Format
     */
    private $date;


    /**
     * @var LoggerInterface|null
     */
    private $log;


    /**
     * WeekOffset constructor.
     *
     * @param TimetableCampusMapper $timetableCampusMapper
     * @param Alaress_Date_Format   $date
     * @param LoggerInterface|null  $log
     */
    public function __construct(TimetableCampusMapper $timetableCampusMapper, Alaress_Date_Format $date, LoggerInterface $log = null)
    {
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->date = $date;
        $this->log = $log;
    }


    /**
     * Figure out which term we're in
     * Note that we use timetableCampus start and finish date to decide
     *
     * Returns null when it can't be determined
     *
     * @return int|null
     */
    public function findCurrentTerm(): ?int
    {
        $cycles = $this->timetableCampusMapper->findAll()->sortBy('start', 'asc')->getAll();

        $n = \count($cycles);

        for ($i = 1; $i <= $n; $i++) {
            $currentCycle = $cycles[$i-1];

            if ($this->log) {
                $this->log->debug(sprintf(
                    'Comparing %s <= %s <= %s',
                    $currentCycle->getStart()->getDatabase(),
                    $this->date->getDatabase(),
                    $currentCycle->getFinish()->getDatabase()
                ));
            }

            if ($currentCycle->getStart()->lte($this->date) && $currentCycle->getFinish()->gte($this->date)) {
                if ($this->log) {
                    $this->log->debug(sprintf(
                        'Using cycle id %s',
                        $currentCycle->getCycle()->getId()
                    ));
                }

                return $i;
            }
        }

        return null;
    }


    /**
     * Determine which week we're in, if possible
     * Returns null when it can't be determined
     *
     * @return int|null
     * @throws PDOException
     * @throws Exception
     */
    public function findWeekOffset(): ?int
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
            'currentDate' => $this->date->getDatabaseDate()
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            /** @var TimetableCampus $timetableCampus */
            $timetableCampus = $this->timetableCampusMapper->hydrate($row);

            return $timetableCampus->getStart()->diffInWeeks($this->date);
        }

        return null;
    }
}
