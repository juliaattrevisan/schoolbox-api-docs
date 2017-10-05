<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database\Factory as DatabaseFactory;

class External_Schoolbox_Class implements External_ClassInterface
{

    /** @var IConnector $internalDatabase */
    private $internalDatabase;

    /** @var ConfigMapper $configMapper */
    private $configMapper;

    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        $this->configMapper = ConfigMapper::getInstance();

        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }

    /**
     * @param string $classCodes
     *
     * @return IQuery
     */
    protected function getClassQuery($classCodes)
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
            $predicate["bindings"]
        );
    }

    /**
     * @param string $classCodes
     *
     * @return IQuery
     */
    protected function getTeacherQuery($classCodes)
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
			    WHERE       c.code IN ({$predicate["parameters"]}) AND 
			                u.deleted_at IS NULL
			    ORDER BY    u.lastname, u.firstname
		    ",
            $predicate["bindings"]
        );
    }

    /**
     * @param string $classCode
     *
     * @return array
     */
    public function getStudents($classCode)
    {
        $studentQuery = $this->getClassQuery($classCode);

        if (!$studentQuery instanceof IQuery) {
            return [];
        }

        $studentQuery->bindInputs([':type', Group::STUDENT_GROUP_TYPE]);
        $studentIterator = $studentQuery->executeIterator();

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
                ];

                $found[$studentExternalId] = true;
            }
        }

        return $studentListAdaption;
    }

    /**
     * @param string $classCode
     *
     * @return array
     */
    public function getTeacherName($classCode)
    {
        $teachers = [];

        $teacherQuery = $this->getClassQuery($classCode);

        if ($teacherQuery instanceof IQuery) {
            $teacherQuery->bindInputs([':type', Group::STAFF_GROUP_TYPE]);
            $teacherIterator = $teacherQuery->executeIterator();

            foreach ($teacherIterator as $teacher) {
                $teachers[] = $teacher['synergy_id'];
            }
        }

        $teacherQuery = $this->getTeacherQuery($classCode);

        if ($teacherQuery instanceof IQuery) {
            $teacherIterator = $teacherQuery->executeIterator();

            foreach ($teacherIterator as $teacher) {
                $teachers[] = $teacher['synergy_id'];
            }
        }

        return array_unique($teachers);
    }
}
