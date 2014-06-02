<?php
/**
 * Factory for creating an external database class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_DatabaseFactory {

    /**
     * Builds a database connector to a particular part of an external database.
     *
     * If the external database is disabled, or an error occurs
     *
     * @param string $databaseType The type of class to create. One of "Class",
     *                             "Timetable", "User", "Attendance" or "Ping".
     * @param string $databaseName The database type to use. Defaults to
     *                             whatever the configured external DB is, or
     *                             "offline" if it isn't enabled.
     * @return External_ClassInterface|External_AttendanceInterface|External_PingInterface|External_TimetableInterface|External_UserInterface
     *         Depends on value of $databaseType
     * @throws LogicException if no class could be created
     */
    public function buildConnector($databaseType, $databaseName = '') {
        $offlineClass = "External_Offline_$databaseType";
        if (!Config::get('external_enable')) {
            $databaseClass = $offlineClass;
        }
        else {
            if (empty($databaseName)) {
                $databaseName = Config::get('external_type');
            }
            $databaseClass = "External_".ucfirst($databaseName)."_".$databaseType;
        }
        if (class_exists($databaseClass, true)) {
            try {
                return new $databaseClass();
            }
            catch (\Schoolbox\External\Exception $e) {}
        }

        // At this point: return an offline class.
        // If even that doesn't exist, error out.
        if (class_exists($offlineClass, true)) {
            return new $offlineClass();
        }
        throw new LogicException("Class \"$offlineClass\" does not exist");
    }

    /**
     * Builds an attendance connector.
     *
     * @param  string $databaseName
     * @return External_AttendanceInterface
     */
    public function buildAttendanceConnector($databaseName = '') {
        return $this->buildConnector('attendance', $databaseName);
    }

    /**
     * Builds a class connector.
     *
     * @param  string $databaseName
     * @return External_ClassInterface
     */
    public function buildClassConnector($databaseName = '') {
        return $this->buildConnector('class', $databaseName);
    }

    /**
     * Builds a ping connector.
     *
     * @param  string $databaseName
     * @return External_PingInterface
     */
    public function buildPingConnector($databaseName = '') {
        return $this->buildConnector('ping', $databaseName);
    }

    /**
     * Builds a timetable connector.
     *
     * @param  string $databaseName
     * @return External_TimetableInterface
     */
    public function buildTimetableConnector($databaseName = '') {
        return $this->buildConnector('timetable', $databaseName);
    }

    /**
     * Builds a user connector.
     *
     * @param  string $databaseName
     * @return External_UserInterface
     */
    public function buildUserConnector($databaseName = '') {
        return $this->buildConnector('user', $databaseName);
    }


}
