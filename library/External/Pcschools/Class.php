<?php
/**
 * PCSchools class connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_Class implements External_ClassInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * Get a list of students in a particular $classCode
     * lastname,
     * firstname,
     * yearlevel,
     * StudentHouseDescription as house,
     * StudentMedicalAlert,
     * StudentSpecialNeedsFlag,
     * synergy_id,
     * ClassCampus
     *
     * @param $classCode
     *
     * @return array
     */
    public function getStudents($classCode)
    {
        // Class code format: S.YEAR + SU.ABBREV + S.CLASS
        // May also need to take TIME_PERIOD into account
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll("
                SELECT
                    tf.TIME_PERIOD,
                    st.LEGAL_SURNAME AS lastname,
                    st.GIVEN_NAME AS firstname,
                    st.CURRENT_YEAR AS yearlevel,
                    st.HOUSE AS house,
                    st.ALERT AS StudentMedicalAlert,
                    '' AS StudentSpecialNeedsFlag,
                    st.STUDKEY AS synergy_id,
                    st.CAMPUS AS campusCode
                FROM TIMEFILE tf
                INNER JOIN SUB su
                    ON  tf.[YEAR]      = su.[YEAR]
                    AND tf.SUBJECT     = su.SUBJECT_NO
                    AND tf.CLASS       = su.CLASS
                    AND tf.TIME_PERIOD = su.PERIOD_ID
                INNER JOIN SUBJECT s  ON tf.SUBJECT = s.NO
                INNER JOIN STUDENT st ON su.STUDKEY = st.STUDKEY
                WHERE (tf.YEAR + tf.ABBREV + tf.CLASS) = {$this->getDatabase()->smartQuote($classCode)}
            ")
        );
    }

    /**
     * Returns a list of *THE IDs OF* teachers that are associated
     * with a class (method is misnamed)
     *
     * @param $classCode
     *
     * @return array
     */
    public function getTeacherName($classCode)
    {
        return array_map(
            function($v) { return $v; /*['TEACHER']; */},
            $this->getDatabase()->trimAll(
                $this->getDatabase()->getAll("
                    SELECT DISTINCT
                        tf.TIME_PERIOD,
                        tf.TEACHER
                    FROM TIMEFILE tf
                    WHERE (tf.YEAR + tf.ABBREV + tf.CLASS) = {$this->getDatabase()->smartQuote($classCode)}
                ")
            )
        );
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
}
