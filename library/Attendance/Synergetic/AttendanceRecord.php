<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Statement\Query\IQuery;

class Attendance_Synergetic_AttendanceRecord
{
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();
    }

    /**
     * Update an attendance record
     *
     * @param array $data
     * @return IQuery|bool
     */
    public function update($data)
    {
        if (isset($data['ID'], $data['AttendanceMasterSeq'])) {
            $userID = $data['ID'];
            $AttendanceMasterSeq = $data['AttendanceMasterSeq'];
            unset($data['ID']);
            unset($data['AttendanceMasterSeq']);

            $query = "UPDATE dbo.wkAttendances SET";

            $params = [];
            $paramCount = 0;

            // Set through all the data contained in array and set it

            foreach ($data as $key => $value) {
                $paramCount++;
                $query .= " $key = :param{$paramCount},";
                $params['param' . $paramCount] = $value;
            }

            $query = substr($query, 0, -1);

            // Set based on two key values sequence and user id

            $query .= "
                WHERE wkAttendanceMasterSeq = :attendanceMasterSeq
                AND ID = :id
            ";

            $params['attendanceMasterSeq'] = $AttendanceMasterSeq;
            $params['id'] = $userID;

            return $this->connector->executeQuery($query, $params);
        }

        return false;
    }
}
