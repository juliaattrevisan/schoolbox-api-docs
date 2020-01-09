<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Home;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Database;
use Schoolbox\Resource\LogMapper;
use Proxy\Server as ProxyServer;
use Healthcheck;
use Schoolbox\Learning\InlineGrader\Kami;

class Controller extends Base
{

    /** @var Kami\IConnector */
    private $kamiConnector;

    /** @var Database\Factory */
    private $databaseFactory;

    /** @var null|IConnector */
    private $databaseExternal;


    /**
     * @param Kami\IConnector  $kamiConnector
     * @param Database\Factory $databaseFactory
     * @param IConnector|null  $databaseExternal Will be NULL if external is disabled in config, or if unable to connect
     */
    public function __construct(Kami\IConnector $kamiConnector, Database\Factory $databaseFactory, ?IConnector $databaseExternal)
    {
        $this->kamiConnector    = $kamiConnector;
        $this->databaseFactory  = $databaseFactory;
        $this->databaseExternal = $databaseExternal;
    }


    /**
     * Display admin settings homepage
     */
    public function getIndex()
    {
        // Disk usage

        $disk_total = disk_total_space($this->getConfigLoader()->get('file_dir'));
        $disk_free = disk_free_space($this->getConfigLoader()->get('file_dir'));
        $disk_used = $disk_total - $disk_free;
        $disk_percentage = round(($disk_used / $disk_total) * 100);;

        // Memory Usage

        $m = [];

        if (is_readable('/proc/meminfo')) {
            foreach (file('/proc/meminfo') as $ri) {
                $m[strtok($ri, ':')] = str_replace(' kB', '', \trim(strtok(''))) * 1024;
            }

            $memory_total = $m['MemTotal'];
            $memory_used = $memory_total - $m['MemFree'] - $m['Cached'];
            $memory_percentage = round($memory_used / $m['MemTotal'] * 100);
            $memory_gradient = 10 - floor($memory_percentage / 10);
        } else {
            $memory_total = '';
            $memory_used = '';
            $memory_percentage = '';
            $memory_gradient = '';
        }

        // External database checked

        $external_db_version = '';

        if ($this->getConfigLoader()->get('external_enable')) {
            $external_ping = new \External_Ping();

            if ($external_ping->isResponsive()) {
                $external_db = ucfirst($this->getConfigLoader()->get('external_type'));
                $external_db_light = 'success';
                $external_db_status = 'Online';
                $external_db_version = $external_ping->getVersion();
            } else {
                $external_db = ucfirst($this->getConfigLoader()->get('external_type'));
                $external_db_light = 'alert';
                $external_db_status = 'Offline';
            }
        } else {
            $external_db = ucfirst($this->getConfigLoader()->get('external_type'));
            $external_db_light = 'alert';
            $external_db_status = 'Disabled';
        }

        // Email status

        if ($this->getConfigLoader()->get('mail_enable') && $this->getConfigLoader()->get('mail_smtp_host')) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));

            if (@socket_connect($socket, $this->getConfigLoader()->get('mail_smtp_host'), $this->getConfigLoader()->get('mail_smtp_port'))) {
                $email_light = 'success';
                $email_status = 'Online';
            } else {
                $email_light = 'alert';
                $email_status = 'Offline';
            }

            socket_close($socket);
        } else {
            $email_light = 'alert';
            $email_status = 'Disabled';
        }

        // LDAP status

        $ldap_light = 'alert';
        $ldap_status = 'Disabled';

        $ldap_alt_light = 'alert';
        $ldap_alt_status = 'Disabled';

        if ($this->getConfigLoader()->get('ldap_enable')) {
            $tempsession = new \Session;

            // Check primary LDAP server bind

            if (trim($this->getConfigLoader()->get('ldap_host')) != "") {
                $ds = ldap_connect($this->getConfigLoader()->get('ldap_host'));
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                $ldapbind = @ldap_bind($ds, $tempsession->ldap_escape($this->getConfigLoader()->get('ldap_user')), utf8_encode($this->getConfigLoader()->get('ldap_pass')));

                if ($ldapbind) {
                    $ldap_light = 'success';
                    $ldap_status = 'Online';
                } else {
                    $ldap_light = 'alert';
                    $ldap_status = 'Offline';
                }

                unset($ds, $ldapbind);
            }

            // Check secondary LDAP server bind

            if (trim($this->getConfigLoader()->get('ldap_host_alt')) != "") {
                $ds = ldap_connect($this->getConfigLoader()->get('ldap_host_alt'));
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                $ldapbind = @ldap_bind($ds, $tempsession->ldap_escape($this->getConfigLoader()->get('ldap_user_alt')), utf8_encode($this->getConfigLoader()->get('ldap_pass')));

                if ($ldapbind) {
                    $ldap_alt_light = 'success';
                    $ldap_alt_status = 'Online';
                } else {
                    $ldap_alt_light = 'alert';
                    $ldap_alt_status = 'Offline';
                }

                unset($ds, $ldapbind);
            }
        }

        // Check WWW

        $context = ProxyServer::getInstance()->getContext();

        if (@file_get_contents('http://schoolbox.com.au/schoolboxinstaller/status.html', null, $context)) {
            $www_light = 'success';
            $www_status = 'Available';
        } else {
            $www_light = 'alert';
            $www_status = 'Inaccessible';
        }

        // Get logs

        $logs = [];
        $logsRecent = LogMapper::getInstance()->findRecent();

        foreach ($logsRecent as $log) {
            $action = '';

            switch ($log['login']) {
                case '1' :
                    $action = 'Logged in';
                    break;
                case '0' :
                    $action = 'Failed login';
                    break;
                case 'modify':
                    $action = 'Modified ' . $log['ip'];
                    break;
                case 'move':
                    $action = 'Moved ' . $log['ip'];
                    break;
                case 'access':
                    $action = 'Accessed ' . $log['ip'];
                    break;
                case 'create' :
                    $action = 'Added ' . $log['ip'];
                    break;
                case 'delete' :
                    $action = 'Deleted ' . $log['ip'];
                    break;

                default:  //If unknown, skip output
                    continue 2;
            }

            if ($action != '') {

                $date = new \Alaress_Date_Format($log['occurred'], \Alaress_Date_Format::DATABASE_FORMAT);

                $logs[] = [
                    'date' => $date->getShort(),
                    'action' => $action,
                    'name' => "{$log['firstname']} {$log['lastname']}"
                ];
            }
        }

        // Get the navigation

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminHome/index.twig',
                'data' => array(
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                    'disk_used' => \Alaress_Byte_Format::bytesToString($disk_total - $disk_free),
                    'disk_total' => \Alaress_Byte_Format::bytesToString($disk_total),
                    'disk_percentage' => \Alaress_Byte_Format::bytesToString($disk_percentage),
                    'memory_used' => \Alaress_Byte_Format::bytesToString($memory_used),
                    'memory_total' => \Alaress_Byte_Format::bytesToString($memory_total),
                    'memory_percentage' => $memory_percentage,
                    'memory_gradient' => $memory_gradient,
                    'external_db' => $external_db,
                    'external_db_version' => $external_db_version,
                    'external_db_light' => $external_db_light,
                    'external_db_status' => $external_db_status,
                    'email_light' => $email_light,
                    'email_status' => $email_status,
                    'ldap_light' => $ldap_light,
                    'ldap_status' => $ldap_status,
                    'ldap_alt_light' => $ldap_alt_light,
                    'ldap_alt_status' => $ldap_alt_status,
                    'www_light' => $www_light,
                    'www_status' => $www_status,
                    'logs' => $logs,
                    'kamiEnabled' => $this->kamiConnector->isEnabled(),
                    'kamiOnline' => $this->kamiConnector->isOnline(),

                ),
            )
        );
    }

    /**
     * Provides a view of the current external DB health, linked from the admin dashboard
     */
    public function getHealthcheck()
    {
        try {
            $healthCheck = new Healthcheck($this->databaseExternal, $this->databaseFactory, $this->getConfigLoader());
            $healthCheck->run();
            $output = $healthCheck->results();
        } catch (InvalidArgumentException|AlaressDatabaseException $e) {
            $output = [
                'errors' => [
                    $e->getMessage()
                ]
            ];
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@adminHome/healthcheck.twig',
                'data' => array(
                    'pageCurrent' => 'healthcheck',
                    'output' => $output
                )
            )
        );
    }
}
