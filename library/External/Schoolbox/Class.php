<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\Role;

class External_Schoolbox_Class implements External_ClassInterface
{

    /** @var IConnector $internalDatabase */
    private $internalDatabase;

    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }

    /**
     * {@inheritDoc}
     * @param string $classCode
     *
     * @return array
     */
    public function getStudents($classCode)
    {
        $studentIterator = $this->getClassIterator($classCode, Role::STUDENT);

        if (!$studentIterator instanceof IIterator) {
            return [];
        }

        $studentListAdaption = [];
        $found               = [];

        foreach ($studentIterator as $student) {
            $studentExternalId = $student['synergy_id'];

            if (!array_key_exists($studentExternalId, $found)) {
                $studentListAdaption[] = [
                    'synergy_id'              => $studentExternalId,
                    'lastname'                => $student['lastname'],
                    'firstname'               => $student['firstname'],
                    'yearlevel'               => $student['year'],
                    'house'                   => $student['house'],
                    'StudentMedicalAlert'     => '',
                    'StudentSpecialNeedsFlag' => '',
                    'StudentTutorDescription' => '',
                ];

                $found[$studentExternalId] = true;
            }
        }

        return $studentListAdaption;
    }

    /**
     * {@inheritDoc}
     * @param string $classCodes
     * @param string $type Const indicating type of user (e.g. Group::STUDENT_GROUP_TYPE)
     *
     * @return IIterator
     */
    protected function getClassIterator($classCodes, $type)
    {
        $codes = array_filter(explode(',', $classCodes));

        if (empty($codes)) {
            return null;
        }

        $predicate = PredicateBuilder::getArrayBindings($codes, 'classCode');

        return $this->internalDatabase->prepareQuery(
            "
                SELECT        u.id, 
                              u.synergy_id,
                              u.title, 
                              u.firstname,
                              u.lastname,
                              u.year,
                              u.house
                FROM          class c
                LEFT JOIN     class_user cu ON cu.class_id = c.id
                LEFT JOIN     user u ON u.id = cu.user_id
                LEFT JOIN     groups g ON g.id = u.group_id
                WHERE         c.code IN ({$predicate["placeholders"]}) AND
                              g.group_type = :type AND
                              u.deleted_at IS NULL
                ORDER BY      u.lastname,
                              u.firstname
            ",
            array_merge(
                $predicate["bindings"],
                ['type' => $type]
            )
        )->executeIterator();
    }

    /**
     * {@inheritDoc}
     * @param string $classCode
     *
     * @return array
     */
    public function getTeacherName($classCode)
    {
        $teachers = [];

        $classIterator = $this->getClassIterator($classCode, Role::STAFF);

        if ($classIterator instanceof IIterator) {
            foreach ($classIterator as $teacher) {
                $teachers[] = $teacher['synergy_id'];
            }
        }

        $teacherIterator = $this->getTeacherIterator($classCode);

        if ($teacherIterator instanceof IIterator) {
            foreach ($teacherIterator as $teacher) {
                $teachers[] = $teacher['synergy_id'];
            }
        }

        return array_unique($teachers);
    }

    /**
     * {@inheritDoc}
     * @param string $classCodes
     *
     * @return IIterator
     */
    protected function getTeacherIterator($classCodes)
    {
        $codes = array_filter(explode(',', $classCodes));

        if (empty($codes)) {
            return null;
        }

        $predicate = PredicateBuilder::getArrayBindings($codes, 'classCode');

        return $this->internalDatabase->prepareQuery(
            "
                SELECT      u.id,
                            u.synergy_id,
                            u.title,
                            u.firstname,
                            u.lastname,
                            u.year,
                            u.house
                FROM        class c
                LEFT JOIN   class_period cp ON c.id = cp.class_id
                INNER JOIN  user u ON u.id = cp.teacher_id
                WHERE       c.code IN ({$predicate["placeholders"]}) AND
                            u.deleted_at IS NULL
                ORDER BY    u.lastname,
                            u.firstname
            ",
            $predicate["bindings"]
        )->executeIterator();
    }
}
