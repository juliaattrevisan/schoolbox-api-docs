<?php
/**
 * @author    Anyone Foolish Enough To Claim It <everyone@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory as DatabaseFactory;

/** @noinspection PhpUndefinedClassInspection */
class External_Denbigh_Class implements External_ClassInterface
{
    /** @var IConnector $externalDatabase */
    private $externalDatabase;

    /**
     * External_Denbigh_Class constructor.
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->externalDatabase = DatabaseFactory::getInstanceExternal();
    }

    /**
     * {@inheritDoc}
     */
    public function getStudents(string $classCode): array
    {
        $classCodes = array_filter(explode(',', $classCode));

        if (count($classCodes) == 0) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($classCodes, 'classCode');

        /** @noinspection SqlDialectInspection */
        $studentIterator = $this->externalDatabase->getQueryIterator(
            "
                SELECT      p.denbighID,
                            p.surname,
                            p.preferred_name,
                            s.academicYear,
                            s.house
                FROM        Classes c
                JOIN        Enrolments e ON e.classCode = c.classCode
                JOIN        Students s ON s.peopleDenbighID = e.studentID
                JOIN        People p ON p.denbighID = s.peopleDenbighID
                WHERE       c.classCode IN ({$predicate["placeholders"]})
                GROUP BY    denbighID
                ORDER BY    p.surname ASC
            ",
            $predicate["bindings"]
        );

        $students = [];

        foreach ($studentIterator as $student) {
            $students[] = [
                'synergy_id'              => $student['denbighID'],
                'lastname'                => $student['surname'],
                'firstname'               => $student['preferred_name'],
                'yearlevel'               => $student['academicYear'],
                'house'                   => $student['house'],
                'StudentMedicalAlert'     => '',
                'StudentSpecialNeedsFlag' => '',
                'EnglishSecondFlag'       => '',
                'StudentTutorDescription' => '',
            ];
        }

        return $students;
    }

    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        $classCodes = array_filter(explode(',', $classCode));

        if (count($classCodes) == 0) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($classCodes, 'classCode');

        /** @noinspection SqlDialectInspection */
        return $this->externalDatabase->executeQuery(
            "
                SELECT DISTINCT p.denbighID
                FROM            Classes c
                JOIN            Staff s ON s.staffCode = c.staffCode
                JOIN            People p ON p.denbighID = s.peopleDenbighID
                WHERE           c.ClassCode IN ({$predicate["placeholders"]})
            ",
            $predicate["bindings"]
        )->fetchColumnArray('denbighID');
    }

    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return [];
    }

    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return [];
    }
}
