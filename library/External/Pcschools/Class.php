<?php
/**
 * PCSchools class connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_Pcschools_Class implements External_ClassInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Students that have left the school are in this year
     */
    private const LEFT_YEAR = 99;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
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

        $codeQuery = $this->getTimefileFilter($classCode);

        $query = "SELECT DISTINCT
                    st.LEGAL_SURNAME AS lastname,
                    st.GIVEN_NAME AS firstname,
                    st.CURRENT_YEAR AS yearlevel,
                    st.HOUSE AS house,
                    st.ALERT AS StudentMedicalAlert,
                    '' AS StudentSpecialNeedsFlag,
                    '' AS EnglishSecondFlag,
                    '' AS StudentTutorDescription,
                    st.STUDKEY AS synergy_id
                FROM TIMEFILE tf
                INNER JOIN SUB su
                    ON  tf.[YEAR]      = su.[YEAR]
                    AND tf.SUBJECT     = su.SUBJECT_NO
                    AND tf.CLASS       = su.CLASS
                    AND tf.TIME_PERIOD = su.PERIOD_ID
                INNER JOIN SUBJECT s  ON tf.SUBJECT = s.NO
                INNER JOIN STUDENT st ON su.STUDKEY = st.STUDKEY
                WHERE
                    st.CURRENT_YEAR <> '".self::LEFT_YEAR."' AND
                    {$codeQuery};";

        return $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
    }

    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $query = $this->getTimefileFilter($classCode);

        return array_map(
            function($v) { return $v['TEACHER']; },
            $this->getDatabase()->trimAll(
                $this->getDatabase()->getAll("
                    SELECT DISTINCT
                        tf.TEACHER
                    FROM TIMEFILE tf
                    WHERE $query
                    AND tf.STATUS != 'S'
                ")
            )
        );
    }

    /**
     * Gets a where part for the timefile query in `getTeachers()` and `getStudents()`.
     *
     * @param string $classCodes
     *
     * @return string
     */
    private function getTimefileFilter($classCodes)
    {
        $query = [];
        foreach (explode(',', $classCodes) as $code) {

            $parts = explode('/', $code);
            if (count($parts) > 1) {
                list($timePeriod, $code) = $parts;
            }
            $filter = "(tf.YEAR + tf.ABBREV + tf.CLASS) = {$this->getDatabase()->smartQuote($code)}";
            if (isset($timePeriod)) {
                $filter .= " AND tf.TIME_PERIOD = {$this->getDatabase()->smartQuote($timePeriod)}";
            }

            $query[] = '('.$filter.')';
        }
        $query = '('.implode(' OR ', $query).')';

        return $query;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
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
