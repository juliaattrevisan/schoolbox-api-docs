<?php
/**
 * @author    Anyone Foolish Enough To Claim It <everyone@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @param string $classCode CSV list of class codes to find students in
     *
     * @return array
     */
    public function getStudents($classCode)
    {
        $classCodes = array_filter(explode(',', $classCode));

        if (count($classCodes) == 0) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($classCodes, 'classCode');

        /** @noinspection SqlDialectInspection */
        $studentIterator = $this->externalDatabase->executeQueryIterator(
            "
                SELECT      p.denbighID, 
                            p.surname, 
                            p.preferred_name, 
                            s.academicYear, 
                            s.house
                FROM        Classes c
                LEFT JOIN   Enrolments e ON e.classCode = c.classCode
                LEFT JOIN   Students s ON s.peopleDenbighID = e.studentID
                LEFT JOIN   People p ON p.denbighID = s.peopleDenbighID
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
                'StudentTutorDescription' => '',
            ];
        }

        return $students;
    }

    /**
     * {@inheritDoc}
     * @param string $classCode CSV list of class codes to find staff in
     *
     * @return array
     */
    public function getTeacherName($classCode)
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
                LEFT JOIN       Staff s ON s.staffCode = c.staffCode
                LEFT JOIN       People p ON p.denbighID = s.peopleDenbighID
                WHERE           c.ClassCode IN ({$predicate["placeholders"]})
		    ",
            $predicate["bindings"]
        )->fetchColumnArray('denbighID');
    }
}