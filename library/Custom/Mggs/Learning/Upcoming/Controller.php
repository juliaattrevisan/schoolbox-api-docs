<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Mggs\Learning\Upcoming;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\User;
use Custom\Mggs\Learning\Personalised;


class Controller extends \Schoolbox\Learning\Upcoming\Controller
{
    /**
     * @param User                $student
     * @param Alaress_Date_Format $date
     * @return array
     */
    protected function buildPayloadForHtml(User $student, Alaress_Date_Format $date)
    {
        $payload = parent::buildPayloadForHtml($student, $date);

        $termDates = $this->findTermDates($date);

        $payload['termDates'] = array_map(function (Alaress_Date_Format $d) {
            return $d->getDatabaseDate();
        }, $termDates);

        if ($termDates[0] && $termDates[3]) {
            $payload['termWeekCount'] = $termDates[0]->diffInWeeks($termDates[3]);
        }

        return $payload;
    }


    /**
     * @param Alaress_Date_Format $now
     * @return Alaress_Date_Format[]|array
     */
    protected function findTermDates(Alaress_Date_Format $now)
    {
        /** @var IConnector $edb */
        $edb = $this->getDi()->get('databaseExternal');

        $stmt = $edb->prepareQuery("
          SELECT TOP 1000 [FileYear]
              ,[FileSemester]
              ,[StartDate]
              ,[EndDate]
          FROM [dbo].[FileSemesters]
          WHERE FileYear = :fileYear
          ORDER BY FileSemestersSeq ASC
        ");

        $stmt->execute([ 'fileYear' => $now->getYear() ]);

        $terms = $semesters = [];

        // index by semester
        while ($row = $stmt->fetch()) {
            $fs = $row['FileSemester'];

            $payload = [
                'start' => Alaress_Date_Format::newDatabaseDate($row['StartDate']),
                'end'   => Alaress_Date_Format::newDatabaseDate($row['EndDate']),
            ];

            if ($fs <= 2) {
                $semesters[$fs] = $payload;
            } else {
                $terms[$fs] = $payload;
            }
        }

        return $this->locateDates($now, $semesters, $terms);
    }


    /**
     * @param Alaress_Date_Format $now
     * @param array               $semesters
     * @param array               $terms
     * @return Alaress_Date_Format[]
     */
    private function locateDates(Alaress_Date_Format $now, array $semesters, array $terms): array
    {
        $selectedSemester = null;

        foreach (array_reverse($semesters) as $semester) {

            if ($now < $semester['end']) {
                $selectedSemester = $semester;
            }
        }

        $termDates = [];

        foreach ($terms as $term) {
            if ($term['start'] >= $selectedSemester['start'] && $term['end'] <= $selectedSemester['end']) {
                $termDates[] = $term['start'];
                $termDates[] = $term['end'];
            }
        }

        return $termDates;
    }


    /**
     * @param User $student
     * @return string
     */
    protected function getTemplateNamespace(User $student)
    {
        $enabledRoles = explode(',', $this->getConfigLoader()->get(Personalised\Admin\Controller::CONFIG));

        if (in_array($student->getRole()->getId(), $enabledRoles)) {
            return Module::BASENAME . 'Mggs';
        }

        return parent::getTemplateNamespace($student);
    }

}
