<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\Role;

/**
 * Class External_Schoolbox_Class
 */
class External_Schoolbox_Class implements External_ClassInterface
{
    /** @var IConnector $internalDatabase */
    private IConnector $internalDatabase;


    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }


    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $studentIterator = $this->getClassIterator($classCode, Role::STUDENT);

        if (!$studentIterator instanceof IIterator) {
            return [];
        }

        $studentListAdaption = [];
        $found               = [];

        foreach ($studentIterator as $student) {
            $studentExternalId = $student['external_id'];

            if (!array_key_exists($studentExternalId, $found)) {
                $studentListAdaption[] = [
                    'synergy_id'              => $studentExternalId,
                    'lastname'                => $student['lastname'],
                    'preferredname'           => $student['preferred_firstname'],
                    'firstname'               => $student['firstname'],
                    'yearlevel'               => $student['year_level'],
                    'house'                   => $student['house_name'],
                    'StudentMedicalAlert'     => $student['alert_medical'] ?? '',
                    'StudentSpecialNeedsFlag' => $student['special_needs'] ?? '',
                    'EnglishSecondFlag'       => $student['esl'] ?? '',
                    'StudentTutorDescription' => $student['tutor_group'] ?? '',
                    'StudentForm'             => $student['form_name'] ?? '',
                ];

                $found[$studentExternalId] = true;
            }
        }

        return $studentListAdaption;
    }


    /**
     * @param string $classCodes
     * @param string $type Const indicating type of user (e.g. Group::STUDENT_GROUP_TYPE)
     *
     * @return IIterator|null
     * @throws \Alaress\Database\Exception
     */
    protected function getClassIterator(string $classCodes, string $type): ?IIterator
    {
        $codes = array_filter(explode(',', $classCodes));

        if (empty($codes)) {
            return null;
        }

        $predicate = PredicateBuilder::getArrayBindings($codes, 'classCode');

        $studentCols = $type === Role::STUDENT
            ? ', sups.year_level,
                 sups.house_name,
                 sups.esl,
                 sups.alert_medical,
                 sups.special_needs,
                 sups.tutor_group,
                 sups.form_name'
            : '';

        $roleTypeJoin = match ($type) {
            Role::STUDENT => ' JOIN sis_user_profile_student sups ON up.id = sups.sis_user_profile_id',
            Role::STAFF => ' JOIN sis_user_profile_staff sups ON up.id = sups.sis_user_profile_id',
            default => '',
        };

        return $this->internalDatabase->prepareQuery(
            "
                SELECT        up.external_id,
                              up.title,
                              up.firstname,
                              up.preferred_firstname,
                              up.lastname
                              $studentCols
                FROM          sis_class c
                    JOIN               sis_class_user cu ON cu.class_id = c.id
                    JOIN               sis_user_profile up ON cu.external_id = up.external_id
                    $roleTypeJoin
                WHERE         c.code IN ({$predicate["placeholders"]})
                ORDER BY      up.lastname,
                              up.firstname
            ",
            $predicate["bindings"],
        )->getIterator();
    }


    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, ?Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $teachers = [];

        $classIterator = $this->getClassIterator($classCode, Role::STAFF);

        if ($classIterator instanceof IIterator) {
            foreach ($classIterator as $teacher) {
                $teachers[] = $teacher['external_id'];
            }
        }

        $teacherIterator = $this->getTeacherIterator($classCode);

        if ($teacherIterator instanceof IIterator) {
            foreach ($teacherIterator as $teacher) {
                $teachers[] = $teacher['external_id'];
            }
        }

        /* Schedule Timetable might contain teacher involvements due to LISS
         * combining data sets with the cyclic timetable
         */
        $pastThreshold   = \Alaress_Date_Format::now()->addWeeks(\Schoolbox\External\Event\TimetableUsers::INVOLVEMENT_PAST_THRESHOLD)->startOfDay()->getUnixTimeStamp();
        $futureThreshold = \Alaress_Date_Format::now()->addWeeks(\Schoolbox\External\Event\TimetableUsers::INVOLVEMENT_FUTURE_THRESHOLD)->endOfDay()->getUnixTimeStamp();

        $staffExternalIds = (new \Schoolbox\External\Event\TimetableUsers(
            \Schoolbox\External\ClassEntryMapper::getInstance(),
            \Schoolbox\External\ClassInvolvementMapper::getInstance(),
            \Schoolbox\External\Event\Mapper::getInstance(),
            \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
            \Schoolbox\External\Profile\Staff\Mapper::getInstance()
        ))->findTeachersForClassCode($classCode, $pastThreshold, $futureThreshold);

        $teachers = array_merge($teachers, $staffExternalIds);

        return array_unique($teachers);
    }


    /**
     * @param string $classCodes
     *
     * @return IIterator|null
     * @throws \Alaress\Database\Exception
     */
    protected function getTeacherIterator(string $classCodes): ?IIterator
    {
        $codes = array_filter(explode(',', $classCodes));

        if (empty($codes)) {
            return null;
        }

        $predicate = PredicateBuilder::getArrayBindings($codes, 'classCode');

        return $this->internalDatabase->prepareQuery(
            "
                SELECT      u.external_id
                FROM        sis_class c
                LEFT JOIN   sis_class_period cp ON c.id = cp.class_id
                INNER JOIN  sis_user_profile u ON u.external_id = cp.teacher_external_id
                INNER JOIN  sis_user_profile_staff ups ON u.id = ups.sis_user_profile_id
                WHERE       c.code IN ({$predicate['placeholders']})
                ORDER BY    u.lastname,
                            u.firstname
            ",
            $predicate['bindings']
        )->getIterator();
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
