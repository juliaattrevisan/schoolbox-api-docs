<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Lumberjack\Logger;
use External\IConnector;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Factory for creating an external database class.
 *
 * Design:
 *   * If external connection is disabled, always return an Offline implementation to maintain interface
 *   * If enabled, check that 'external_type' is valid, throw exception if invalid (this is distinct from unsupported!)
 *   * Try to return specific implementation, fall back to Offline if database connectivity unavailable
 *
 * Note that not all external_types are supported, in those cases this simply return an Offline implementation
 */
class External_DatabaseFactory
{
    /**
     * Builds an attendance link connector.
     *
     * @param IConfigLoader $config
     * @return External_AttendanceLinkInterface
     * @throws UnexpectedValueException If enabled but external_type is invalid
     */
    public function buildAttendanceLinkConnector(IConfigLoader $config): External_AttendanceLinkInterface
    {
        if ($this->isEnabled($config) && $config->get('external_attendance')) {
            switch ($this->getValidExternalType($config)) {
                case IConnector::EXTERNAL_TYPE_TASS:
                    return new External_Tass_AttendanceLink();
                case IConnector::EXTERNAL_TYPE_SYNERGETIC:
                    return new External_Synergetic_AttendanceLink();
                case IConnector::EXTERNAL_TYPE_SCHOOLBOX:
                    return new External_Schoolbox_AttendanceLink();
            }
        }
        return new External_Offline_AttendanceLink();
    }


    /**
     * Builds a class connector.
     *
     * @param IConfigLoader $config
     * @return External_ClassInterface
     * @throws UnexpectedValueException If external_type is invalid
     */
    public function buildClassConnector(IConfigLoader $config): External_ClassInterface
    {
        if ($this->isEnabled($config)) {
            try {
                return $this->buildEnabledClassConnector($this->getValidExternalType($config), $this->getSchoolCode($config));
            } catch (Schoolbox\External\Exception | Alaress\Database\Exception $e) {
            }
        }
        return new External_Offline_Class();
    }


    /**
     * Builds a ping connector.
     *
     * @param IConfigLoader $config
     * @return External_PingInterface
     * @throws UnexpectedValueException If external_type is invalid
     */
    public function buildPingConnector(IConfigLoader $config): External_PingInterface
    {
        if ($this->isEnabled($config)) {
            return $this->buildEnabledPingConnector($this->getValidExternalType($config));
        }
        return new External_Offline_Ping();
    }


    /**
     * Builds a timetable connector.
     *
     * @param IConfigLoader $config
     * @return External_TimetableInterface
     * @throws UnexpectedValueException If external_type is invalid
     */
    public function buildTimetableConnector(IConfigLoader $config): External_TimetableInterface
    {
        if ($this->isEnabled($config)) {
            try {
                return $this->buildEnabledTimetableConnectorInternal($this->getValidExternalType($config), $this->getSchoolCode($config));
            } catch (Schoolbox\External\Exception | Alaress\Database\Exception $e) {
            }
        }
        return new External_Offline_Timetable();
    }


    /**
     * Builds a user connector.
     *
     * @param string        $externalType
     * @param IConfigLoader $config
     * @return External_UserInterface
     * @throws UnexpectedValueException If external_type is invalid
     */
    public function buildUserConnector(string $externalType, IConfigLoader $config): External_UserInterface
    {
        if ($this->isEnabled($config)) {
            try {
                return $this->buildEnabledUserConnector(
                    $externalType ?: $this->getValidExternalType($config),
                    $this->getSchoolCode($config),
                    $config->get('external_tass_version')
                );
            } catch (Schoolbox\External\Exception | Alaress\Database\Exception $e) {
            }
        }
        return new External_Offline_User();
    }


    /**
     * Builds a user detention connector.
     *
     * @param IConfigLoader $config
     * @return External_UserDetentionInterface
     * @throws UnexpectedValueException If external_type is invalid
     */
    public function buildUserDetentionConnector(IConfigLoader $config): External_UserDetentionInterface
    {
        if ($this->isEnabled($config) && $this->getValidExternalType($config) === IConnector::EXTERNAL_TYPE_SYNERGETIC) {
            try {
                return new External_Synergetic_UserDetention();
            } catch (Alaress\Database\Exception $e) {
            }
        }
        return new External_Offline_UserDetention();
    }


    /**
     * Builds a markbook connector. If its enabled
     *
     * @param IConfigLoader $config
     * @return External_MarkbookInterface
     * @throws UnexpectedValueException If external_type is invalid
     * @throws InvalidArgumentException If wrong construction params provided
     */
    public function buildMarkbookConnector(IConfigLoader $config): External_MarkbookInterface
    {
        if (
            $this->isEnabled($config) &&
            $config->get(ConfigMapper::NAME_EXTERNAL_MARKBOOK) &&
            $this->getValidExternalType($config) === IConnector::EXTERNAL_TYPE_SYNERGETIC
        ) {
            try {
                $log = new Logger(STDOUT, STDOUT, \Monolog\Logger::INFO, [], true, 'markbook');
                return new External_Synergetic_Markbook($config->get('synergetic_mark_sync_value'), $log);
            } catch (Alaress\Database\Exception $e) {
            }
        }
        return new External_Offline_Markbook();
    }


    /**
     * Builds a pastoral connector. If its enabled
     *
     * @param IConfigLoader $config
     * @return External_PastoralInterface
     * @throws UnexpectedValueException If external_type is invalid
     */
    public function buildPastoralConnector(IConfigLoader $config): External_PastoralInterface
    {
        if (
            $this->isEnabled($config) &&
            $config->get('external_pastoral') &&
            $this->getValidExternalType($config) === IConnector::EXTERNAL_TYPE_SYNERGETIC
        ) {
            try {
                return $this->buildSynergeticPastoralConnector($this->getSchoolCode($config));
            } catch (Alaress\Database\Exception $e) {
            }
        }
        return new External_Offline_Pastoral();
    }


    /**
     * Returns whether external database is online/enabled by examining relevant config
     *
     * @param IConfigLoader $config
     * @return bool
     */
    private function isEnabled(IConfigLoader $config): bool
    {
        return $config->get('external_enable') && Schoolbox\Database\Factory::getInstanceExternal();
    }


    /**
     * Returns valid configured 'external_type' from config, throw exception if invalid
     *
     * @param IConfigLoader $config
     * @return string
     * @throws UnexpectedValueException If external type is unrecognised
     */
    private function getValidExternalType(IConfigLoader $config): string
    {
        $externalType = $config->get('external_type');
        if (!in_array($externalType, IConnector::EXTERNAL_TYPES, true)) {
            throw new UnexpectedValueException("Invalid 'external_type': '$externalType'");
        }
        return $externalType;
    }


    /**
     * Returns configured 'site_school_code' from config
     *
     * @param IConfigLoader $config
     * @return string
     */
    private function getSchoolCode(IConfigLoader $config): string
    {
        return strtoupper($config->get('site_school_code'));
    }


    /**
     * @param string $externalType
     * @param string $siteSchoolCode
     * @return External_ClassInterface
     */
    private function buildEnabledClassConnector(string $externalType, string $siteSchoolCode): External_ClassInterface
    {
        switch ($externalType) {
            case IConnector::EXTERNAL_TYPE_SCHOOLBOX:
                return new External_Schoolbox_Class();
            case IConnector::EXTERNAL_TYPE_TASS:
                return new External_Tass_Class();
            case IConnector::EXTERNAL_TYPE_ISAMS:
                return new External_Isams_Class();
            case IConnector::EXTERNAL_TYPE_EDUMATE:
                return new External_Edumate_Class();
            case IConnector::EXTERNAL_TYPE_PCSCHOOLS:
                return new External_Pcschools_Class();
            case IConnector::EXTERNAL_TYPE_SCHOOLPRO:
                return new External_Schoolpro_Class();
            case IConnector::EXTERNAL_TYPE_SCHOOLEDGE:
                return new External_Schooledge_Class();
            case IConnector::EXTERNAL_TYPE_SYNERGETIC:
                switch ($siteSchoolCode) {
                    case "SCC":
                        return new External_Synergetic_Scc_Class();
                    case "SMCS":
                        return new External_Synergetic_Smcs_Class();
                }
                return new External_Synergetic_Class();
            case IConnector::EXTERNAL_TYPE_DOUBLEFIRST:
                return new External_Doublefirst_Class();
        }
        return new External_Offline_Class();
    }


    /**
     * Builds a ping connector.
     *
     * @param string $databaseName
     * @return External_PingInterface
     */
    public function buildEnabledPingConnector(string $databaseName): External_PingInterface
    {
        switch ($databaseName) {
            case IConnector::EXTERNAL_TYPE_TASS:
                return new External_Tass_Ping();
            case IConnector::EXTERNAL_TYPE_ISAMS:
                return new External_Isams_Ping();
            case IConnector::EXTERNAL_TYPE_EDUMATE:
                return new External_Edumate_Ping();
            case IConnector::EXTERNAL_TYPE_PCSCHOOLS:
                return new External_Pcschools_Ping();
            case IConnector::EXTERNAL_TYPE_SCHOOLBOX:
                return new External_Schoolbox_Ping();
            case IConnector::EXTERNAL_TYPE_SCHOOLPRO:
                return new External_Schoolpro_Ping();
            case IConnector::EXTERNAL_TYPE_SCHOOLEDGE:
                return new External_Schooledge_Ping();
            case IConnector::EXTERNAL_TYPE_SYNERGETIC:
                try {
                    return new External_Synergetic_Ping();
                } catch (Alaress\Database\Exception $e) {
                }
                break;
            case IConnector::EXTERNAL_TYPE_DOUBLEFIRST:
                return new External_Doublefirst_Ping();
        }
        return new External_Offline_Ping();
    }


    /**
     * Builds a timetable connector.
     *
     * @param string $externalType
     * @param string $siteSchoolCode
     * @return External_TimetableInterface
     */
    public function buildEnabledTimetableConnectorInternal(string $externalType, string $siteSchoolCode): External_TimetableInterface
    {
        switch ($externalType) {
            case IConnector::EXTERNAL_TYPE_SYNERGETIC:
                return new External_Synergetic_Timetable();
            case IConnector::EXTERNAL_TYPE_SCHOOLEDGE:
                return new External_Schooledge_Timetable();
            case IConnector::EXTERNAL_TYPE_TASS:
                return new External_Tass_Timetable();
            case IConnector::EXTERNAL_TYPE_ISAMS:
                return new External_Isams_Timetable();
            case IConnector::EXTERNAL_TYPE_EDUMATE:
                return new External_Edumate_Timetable();
            case IConnector::EXTERNAL_TYPE_PCSCHOOLS:
                return $siteSchoolCode === 'CC'
                    ? new External_Pcschools_Cc_Timetable()
                    : new External_Pcschools_Timetable();
            case IConnector::EXTERNAL_TYPE_SCHOOLBOX:
                return new External_Schoolbox_Timetable();
            case IConnector::EXTERNAL_TYPE_SCHOOLPRO:
                return new External_Schoolpro_Timetable();
            case IConnector::EXTERNAL_TYPE_DOUBLEFIRST:
                return new External_Doublefirst_Timetable();
        }
        return new External_Offline_Timetable();
    }


    /**
     * @param string $externalType
     * @param string $siteSchoolCode
     * @param string $tassVersion
     * @return External_UserInterface
     */
    private function buildEnabledUserConnector(string $externalType, string $siteSchoolCode, string $tassVersion): External_UserInterface
    {
        $customClassName = sprintf("External_%s_%s_User", ucfirst(strtolower($externalType)), ucfirst(strtolower($siteSchoolCode)));
        if (class_exists($customClassName)) {
            return new $customClassName();
        }

        switch ($externalType) {
            case IConnector::EXTERNAL_TYPE_TASS:
                switch ($tassVersion) {
                    case 'V52':
                        return new External_Tass_V52_User();
                    case 'V53':
                        return new External_Tass_V53_User();
                }
                return new External_Tass_User();
            case IConnector::EXTERNAL_TYPE_ISAMS:
                return new External_Isams_User();
            case IConnector::EXTERNAL_TYPE_EDUMATE:
                return new External_Edumate_User();
            case IConnector::EXTERNAL_TYPE_PCSCHOOLS:
                return new External_Pcschools_User();
            case IConnector::EXTERNAL_TYPE_SCHOOLBOX:
                return new External_Schoolbox_User();
            case IConnector::EXTERNAL_TYPE_SCHOOLPRO:
                return new External_Schoolpro_User();
            case IConnector::EXTERNAL_TYPE_SCHOOLEDGE:
                return new External_Schooledge_User();
            case IConnector::EXTERNAL_TYPE_SYNERGETIC:
                return new External_Synergetic_User();
            case IConnector::EXTERNAL_TYPE_DOUBLEFIRST:
                return new External_Doublefirst_User();
        }
        return new External_Offline_User();
    }


    /**
     * @param string $siteSchoolCode
     * @return External_Synergetic_Pastoral
     */
    private function buildSynergeticPastoralConnector(string $siteSchoolCode): External_Synergetic_Pastoral
    {
        switch ($siteSchoolCode) {
            case 'SMCS':
                return new External_Synergetic_Smcs_Pastoral();
        }
        return new External_Synergetic_Pastoral();
    }
}
