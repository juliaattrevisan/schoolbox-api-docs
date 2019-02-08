<?php
/**
 * Factory for creating an external database class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @return External\IConnector
     * @throws LogicException if even the offline class could not be created.
     *                        (This probably means you passed an invalid value
     *                         for $databaseType.)
     */
    private function buildConnector(string $databaseType, string $databaseName = ''): External\IConnector
    {
        $offlineClass    = "External_Offline_$databaseType";
        $databaseClasses = array();
        if (!Config::get('external_enable')) {
            $databaseClasses[] = $offlineClass;
        } else {
            if (empty($databaseName)) {
                $databaseName = Config::get('external_type');
            }
            if (empty($databaseName)) {
                $databaseClasses[] = $offlineClass;
            } else {
                // Enable school-specific connection classes
                $databaseClasses[] = "External_".ucfirst($databaseName)."_".ucfirst(Config::get('site_location'))."_".$databaseType;
                $databaseClasses[] = "External_".ucfirst($databaseName)."_".$databaseType;
            }
        }

        // Try each database class in order of definition
        foreach ($databaseClasses as $databaseClass) {

            if (file_exists(Autoloader::getClassFile($databaseClass))) {
                try {
                    return new $databaseClass();
                } catch (\Schoolbox\External\Exception $e) {
                } catch (\Alaress\Database\Exception $e) {
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
     * @param string $databaseName
     * @return External_AttendanceInterface
     * @throws LogicException If cannot build connector
     */
    public function buildAttendanceConnector(string $databaseName = ''): External_AttendanceInterface
    {
        return $this->buildConnector('Attendance', $databaseName);
    }


    /**
     * Builds a class connector.
     *
     * @param string $databaseName
     * @return External_ClassInterface
     * @throws LogicException If cannot build connector
     */
    public function buildClassConnector(string $databaseName = ''): External_ClassInterface
    {
        return $this->buildConnector('Class', $databaseName);
    }


    /**
     * Builds a ping connector.
     *
     * @param string $databaseName
     * @return External_PingInterface
     * @throws LogicException If cannot build connector
     */
    public function buildPingConnector(string $databaseName = ''): External_PingInterface
    {
        return $this->buildConnector('Ping', $databaseName);
    }


    /**
     * Builds a timetable connector.
     *
     * @param string $databaseName
     * @return External_TimetableInterface
     * @throws LogicException If cannot build connector
     */
    public function buildTimetableConnector(string $databaseName = ''): External_TimetableInterface
    {
        return $this->buildConnector('Timetable', $databaseName);
    }


    /**
     * Builds a user connector.
     *
     * @param  string $databaseName
     * @return External_UserInterface
     * @throws LogicException If cannot build connector
     */
    public function buildUserConnector(string $databaseName = ''): External_UserInterface
    {
        return $this->buildConnector('User', $databaseName);
    }

    /**
     * Builds a user detention connector.
     *
     * @param  string $databaseName
     * @return External_UserDetentionInterface
     * @throws LogicException If cannot build connector
     */
    public function buildUserDetentionConnector(string $databaseName = ''): External_UserDetentionInterface
    {
        return $this->buildConnector('UserDetention', $databaseName);
    }

    /**
     * Builds a markbook connector. If its enabled
     *
     * @param  string $databaseName
     * @return External_MarkbookInterface
     * @throws LogicException If cannot build connector
     */
    public function buildMarkbookConnector(string $databaseName = ''): External_MarkbookInterface
    {
        if(Config::get('external_markbook')) {
            return $this->buildConnector('Markbook', $databaseName);
        }

        return $this->buildConnector('Markbook', 'Offline');
    }

    /**
     * Builds a pastoral connector. If its enabled
     *
     * @param  string $databaseName
     * @return External_PastoralInterface
     * @throws LogicException If cannot build connector
     */
    public function buildPastoralConnector(string $databaseName = ''): External_PastoralInterface
    {
        if(Config::get('external_pastoral')) {
            return $this->buildConnector('Pastoral', $databaseName);
        }

        return $this->buildConnector('Pastoral', 'Offline');
    }
}
