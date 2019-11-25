<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\CoCurricular;

/**
 * Class Activities
 *
 * @package Custom\Diocesan\AchievementReport\CoCurricular
 */
class Activities extends Base
{

    /**
     * Find all the co-curricular activities in which a student has been involved,
     * grouped by year and ordered
     *
     * @return array
     */
    public function findData(): array
    {
        $query = "
            SELECT * 
            FROM uvSchoolBoxCoCurricularClasses 
            WHERE StudentID = '{$this->getExternalUserId()}'
            ORDER BY StudentID, StudentFileYear Desc, OrderSort, SubjectClassesDescription
        ";

        $result = $this->getDb()->getAll($query) ?: [];

        new \Alaress_Probe($result);
        return $result;
    }
}
