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
     * @param array  $extraParams  Any extra parameters which may be passed to
     *                             the connector's constructor.
     *                             key: param name, value: param value
     *
     * @return External\IConnector
     * @throws LogicException if even the offline class could not be created.
     *                        (This probably means you passed an invalid value
     *                         for $databaseType.)
     */
    private function buildConnector(string $databaseType, string $databaseName = '', array $extraParams = []): External\IConnector
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
                $databaseClasses[] = "External_" . ucfirst($databaseName) . "_" . ucfirst(Config::get('site_location')) . "_" . $databaseType;
                // Enable school-specific connection classes
                if ('tass' === $databaseName && 'User' === $databaseType && Config::get('external_tass_version')) {
                    $databaseClasses[] = "External_" . ucfirst($databaseName) . '_' . Config::get('external_tass_version') . "_" . $databaseType;
                }
                $databaseClasses[] = "External_" . ucfirst($databaseName) . "_" . $databaseType;
            }
        }

        // Try each database class in order of definition
        foreach ($databaseClasses as $databaseClass) {
            try {
                $reflectionClass = new ReflectionClass($databaseClass);
            } catch (ReflectionException $e) {
                // Class doesn't exist: try the next one
                continue;
            }

            try {
                /** @var External\IConnector $connector */
                $connector = $reflectionClass->newInstanceArgs($extraParams);
                return $connector;
            } catch (\Schoolbox\External\Exception|\Alaress\Database\Exception $e) {
                // Something went wrong, probably could not connect. Try the next one
                continue;
            }
        }

        // At this point: return an offline class.
        // If even that doesn't exist, error out.
        try {
            $reflectionClass = new ReflectionClass($offlineClass);
            $connector       = $reflectionClass->newInstanceArgs($extraParams);
            return $connector;
        } catch (ReflectionException $e) {
            throw new LogicException("Class \"$offlineClass\" does not exist");
        }
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
     * Builds an attendance link connector.
     *
     * @param string $databaseName
     * @return External_AttendanceLink
     * @throws LogicException If cannot build connector
     */
    public function buildAttendanceLinkConnector(string $databaseName = '')
    {
        if (Config::get('external_attendance')) {
            return $this->buildConnector('AttendanceLink', $databaseName);
        }
        return $this->buildConnector('AttendanceLink', 'Offline');
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
        $extraParams = [
            'markType' => 'synergetic_mark_sync_value',
        ];
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
