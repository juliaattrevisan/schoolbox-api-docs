<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Timetable
 *
 * Populates the timetable_cycle, timetable_campus and timetable_period tables.
 *
 * Assumes the luCampus table has been populated.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;

class Synchronise_External_Cis_Strategy_Timetable extends Synchronise_Base_Strategy_Timetable
{
    use Synchronise_External_Cis_Strategy_BatchXmlTrait;

    protected $location  = '';
    protected $columns   = array();
    protected $title     = "Update Schoolbox Timetable Details Synchronisation";
    protected $campus_id = 1;

    /**
     * @var Campus
     */
    protected $campus;

    /**
     * @var TimetableCycle
     */
    protected $cycle;

    /**
     * Setup verbose output
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_External_Cis_Verbose();
    }

    /**
     * {@inheritdoc}
     */
    public function generateList()
    {
        $iterator   = new External_Isams_Batch_Reader_Timetable($this->getXmlReader());
        $this->list = $iterator->getAccountInfo();
    }

    /**
     * Converts the synced item into a format that executeCreateUpdate() can
     * understand.
     *
     * @param  array $list_item
     * @return array
     */
    public function setupSyncedItemDetails($list_item)
    {
        return $list_item;
    }

    /**
     * Runs the create/update script for this class.
     *
     * @param  array $new_item
     * @return int
     */
    public function executeCreateUpdate($new_item)
    {
        $campusCode            = $new_item['TimetableCampusName'];
        $cycleMapper           = TimetableCycleMapper::getInstance();
        $campusMapper          = CampusMapper::getInstance();

        // Timetable cycle: assume a cycle per campus
        /** @var TimetableCycle $cycle */
        try {
            $cycle = $cycleMapper->findOneByColumn('title', $campusCode);
            if ($this->perform_update) {
                printf("Updating timetable \"%s\"\n", $campusCode);
            } else {
                printf("Planning update of timetable \"%s\"\n", $campusCode);
                $this->verbose_filter->incrementUpdated();
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            $cycle = $cycleMapper->createModel();
            if ($this->perform_update) {
                printf("Creating timetable \"%s\"\n", $campusCode);
            } else {
                printf("Planning create of timetable \"%s\"\n", $campusCode);
                $this->verbose_filter->incrementCreated();
            }
        }
        // Assume day length is max of whatever periods are defined
        $dayLength = 0;
        foreach ($new_item['TimetableDays'] as $day) {
            $dayLength = max($dayLength, count($day['DayPeriods']));
        }
        $cycle
            ->setCycleLength(count($new_item['TimetableDays']))
            ->setDayLength($dayLength)
            ->setTitle($campusCode)
            ->setRegular(true)
        ;
        if ($this->perform_update) {
            /** @var Campus $campus */
            try {
                $campus = $campusMapper->findOneByColumn('ext_timetable_code', $campusCode);
            } catch (\Schoolbox\Mapper\Exception $e) {
                printf("Skipping non-existent campus \"%s\"\n", $campusCode);
                return 0;
            }
            if ($cycle->getId()) {
                $this->verbose_filter->incrementUpdated();
            } else {
                $this->verbose_filter->incrementCreated();
            }

            $cycleMapper->save($cycle);
            $this->executeCreateUpdateTimetableCampus($cycle, $campus);
            $this->executeCreateUpdateTimetablePeriod($new_item, $cycle);
        }
        return 1;
    }

    /**
     * Creates or updates a timetable_period entry.
     *
     * @param array          $new_item
     * @param TimetableCycle $cycle
     */
    protected function executeCreateUpdateTimetablePeriod($new_item, $cycle)
    {
        $timetablePeriodMapper = TimetablePeriodMapper::getInstance();

        // Timetable periods
        $timetablePeriods = new \Schoolbox\Collection();
        foreach ($new_item['TimetableDays'] as $day) {
            foreach ($day['DayPeriods'] as $period) {
                // Should delete unfound ones
                $identifier = [
                    'cycle_id' => $cycle->getId(),
                    'day'      => $day['DayNumber'],
                    'sequence' => $period['PeriodNumber'],
                ];
                /** @var TimetablePeriod $timetablePeriod */
                try {
                    // Period ID also has to be set manually
                    // This is because class periods need to know the ID.
                    $timetablePeriod = $timetablePeriodMapper->findById($period['ID']);
//                    printf(
//                        "Updating timetable period \"%s\", day %d, period %d\n",
//                        $campusCode,
//                        $identifier['day'],
//                        $identifier['sequence']
//                    );
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $timetablePeriod = $timetablePeriodMapper->createModel($period['ID']);
//                    printf(
//                        "Creating timetable period \"%s\", day %d, period %d\n",
//                        $campusCode,
//                        $identifier['day'],
//                        $identifier['sequence']
//                    );
                }
                // Dates are always H:i
                $timetablePeriod
                    ->setCycle($cycle)
                    ->setDay($day['DayNumber'])
                    ->setTitle($period['PeriodName'])
                    ->setSequence($period['PeriodNumber'])
                    ->setStart(new Alaress_Date_Format($period['PeriodStart'], 'H:i'))
                    ->setFinish(new Alaress_Date_Format($period['PeriodEnd'], 'H:i'));
                $timetablePeriods->add($timetablePeriod);
            }
        }
        $timetablePeriodMapper->save($timetablePeriods);
    }

    /**
     * Creates a timetable_campus entry for this record.
     *
     * @param TimetableCycle $cycle
     * @param Campus $campus
     */
    protected function executeCreateUpdateTimetableCampus($cycle, $campus)
    {
        $timetableCampusMapper = TimetableCampusMapper::getInstance();

        // Timetable campus: fill in for this campus
        // Assume timetable campus entries last forever
        /** @var TimetableCampus $timetableCampus */
        try {
            $timetableCampus = $timetableCampusMapper->findById($cycle->getId());
//            printf("Updating timetable campus \"%s\"\n", $campusCode);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // Assume this timetable started last Monday, and continues forever
            $startDate       = new Datetime('last Monday');
            $timetableCampus = $timetableCampusMapper->createModel($cycle->getId());
            $timetableCampus
                ->setCampus($campus)
                ->setStart(new Alaress_Date_Format($startDate))
                ->setFinish(new Alaress_Date_Format($startDate->modify('+100 year')))
                ->setOffset(0);
//            printf("Updating timetable campus \"%s\"\n", $campusCode);
        }
        $timetableCampusMapper->save($timetableCampus);
    }
}
