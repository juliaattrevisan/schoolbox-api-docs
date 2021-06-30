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
                $databaseClasses[] = "External_" . ucfirst($databaseName) . "_" . ucfirst(Config::get('site_school_code')) . "_" . $databaseType;
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
                return $this->buildClassInstance($databaseClass, $extraParams);
            } catch (ReflectionException $e) {
                // Class doesn't exist: try the next one
                continue;
            } catch (Schoolbox\External\Exception | Alaress\Database\Exception $e) {
                // Something went wrong, probably could not connect. Try the next one
                continue;
            }
        }

        // At this point: return an offline class.
        // If even that doesn't exist, error out.
        try {
            return $this->buildClassInstance($offlineClass, $extraParams);
        } catch (ReflectionException | Schoolbox\External\Exception | Alaress\Database\Exception $e) {
            throw new LogicException("Class \"$offlineClass\" does not exist");
        }
    }


    /**
     * Helper method for constructing an instance of `$databaseClass`.
     *
     * @param string $databaseClass
     * @param array  $extraParams
     * @return External\IConnector
     * @throws ReflectionException          if `$databaseClass` doesn't exist
     * @throws BadFunctionCallException     if not all mandatory parameters to
     *                                      `$databaseClass` constructor were
     *                                      passed
     * @throws Schoolbox\External\Exception if for some reason the class could
     *                                      not be constructed
     * @throws Alaress\Database\Exception   if the instance couldn't connect to
     *                                      its DB
     */
    private function buildClassInstance(string $databaseClass, array $extraParams): External\IConnector
    {
        $reflectionClass = new ReflectionClass($databaseClass);

        /** @var External\IConnector $connector */
        $connector   = null;
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            $connector = $reflectionClass->newInstanceWithoutConstructor();
        } else {
            // Build a list of params, as that's what `newInstanceArgs` expects.
            $positionalParams = [];

            foreach ($constructor->getParameters() as $i => $parameter) {
                $paramName = $parameter->getName();
                if (array_key_exists($paramName, $extraParams)) {
                    $positionalParams[$i] = $extraParams[$paramName];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $positionalParams[$i] = $parameter->getDefaultValue();
                } else {
                    // No param value was provided, and there's no default:
                    // this is almost certainly an error
                    throw new BadFunctionCallException(
                        sprintf('Class "%s" constructor is missing argument %d (%s)', $databaseClass, $i + 1, $paramName)
                    );
                }
            }

            $connector = $reflectionClass->newInstanceArgs($positionalParams);
        }

        return $connector;
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
            'markType' => Config::get('synergetic_mark_sync_value'),
        ];
        if (Config::get('external_markbook')) {
            return $this->buildConnector('Markbook', $databaseName, $extraParams);
        }

        return $this->buildConnector('Markbook', 'Offline', $extraParams);
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
        if (Config::get('external_pastoral')) {
            return $this->buildConnector('Pastoral', $databaseName);
        }

        return $this->buildConnector('Pastoral', 'Offline');
    }
}
