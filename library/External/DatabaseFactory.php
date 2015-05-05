<?php
/**
 * Factory for creating an external database class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_DatabaseFactory
{

    /**
     * Builds a database connector to a particular part of an external database.
     *
     * If the external database is disabled, or an error occurs, returns an
     * offline class.
     *
     * @param string $databaseType The type of class to create. One of "Class",
     *                             "Timetable", "User", "Attendance" or "Ping".
     * @param string $databaseName The database type to use. Defaults to
     *                             whatever the configured external DB is, or
     *                             "offline" if it isn't enabled.
     *
     * @return External_ClassInterface|External_AttendanceInterface|
     *         External_PingInterface|External_TimetableInterface|
     *         External_UserInterface|External_UserDetentionInterface|
     *         External_MarkbookInterface
     *         (depends on value of $databaseType)
     * @throws LogicException if even the offline class could be created.
     *                        (This probably means you passed an invalid value
     *                         for $databaseType.)
     */
    protected function buildConnector($databaseType, $databaseName = '')
    {
        $offlineClass    = "External_Offline_$databaseType";
        $databaseClasses = array();
        if (!Config::get('external_enable')) {
            $databaseClasses[] = $offlineClass;
        } else {
            if (empty($databaseName)) {
                $databaseName = Config::get('external_type');
            }
            // Enable school-specific connection classes
            $databaseClasses[] = "External_".ucfirst($databaseName)."_".ucfirst(Config::get('site_location'))."_".$databaseType;
            $databaseClasses[] = "External_".ucfirst($databaseName)."_".$databaseType;
        }

        // Try each database class in order of definition
        foreach ($databaseClasses as $databaseClass) {
            if (class_exists($databaseClass, true)) {
                try {
                    return new $databaseClass();
                } catch (\Schoolbox\External\Exception $e) {
                }
            }
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
    public function buildAttendanceConnector($databaseName = '')
    {
        return $this->buildConnector('Attendance', $databaseName);
    }

    /**
     * Builds a class connector.
     *
     * @param  string $databaseName
     * @return External_ClassInterface
     */
    public function buildClassConnector($databaseName = '')
    {
        return $this->buildConnector('Class', $databaseName);
    }

    /**
     * Builds a ping connector.
     *
     * @param  string $databaseName
     * @return External_PingInterface
     */
    public function buildPingConnector($databaseName = '')
    {
        return $this->buildConnector('Ping', $databaseName);
    }

    /**
     * Builds a timetable connector.
     *
     * @param  string $databaseName
     * @return External_TimetableInterface
     */
    public function buildTimetableConnector($databaseName = '')
    {
        return $this->buildConnector('Timetable', $databaseName);
    }

    /**
     * Builds a user connector.
     *
     * @param  string $databaseName
     * @return External_UserInterface
     */
    public function buildUserConnector($databaseName = '')
    {
        return $this->buildConnector('User', $databaseName);
    }

    /**
     * Builds a user detention connector.
     *
     * @param  string $databaseName
     * @return External_UserDetentionInterface
     */
    public function buildUserDetentionConnector($databaseName = '')
    {
        return $this->buildConnector('UserDetention', $databaseName);
    }

    /**
     * Builds a markbook connector. If its enabled
     *
     * @param  string $databaseName
     * @return External_MarkbookInterface
     */
    public function buildMarkbookConnector($databaseName = '')
    {
        if(Config::get('external_markbook')) {
            return $this->buildConnector('Markbook', $databaseName);
        }
        else {
            return $this->buildConnector('Markbook', 'Offline');
        }
    }

}
