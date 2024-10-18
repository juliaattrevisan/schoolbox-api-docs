<?php

/**
 * JSON handler for ajax-loading admin summary modules.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Engagement\Login;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Admin\Home\SamlCertHelper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->startSentryTransaction(__FILE__);

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$output = array();

if (!isset($_POST['module'])) {
    // don't bother returning anything
    die();
}

/** @var IConfigLoader $config */
$config = $kernel->get('config');

switch ($_POST['module']) {
    // cases mirror categories listed
    case 'users':
        if ($_SESSION['user']->isSuperuser()) {
            $login      = new Login($kernel->get('databaseInternal'), $config->get('session_length'));
            $summary    = $login->userSummary(null);
            $totalUser  = 0;
            $totalLogin = 0;

            foreach ($summary as $group) {
                $output[] = array(
                    'GROUP_ID'        => $group['id'],
                    'GROUP_NAME'      => Scrub::text($group['name']),
                    'GROUP_USER'      => $group['total'],
                    'GROUP_LOGGED_IN' => $group['logged_in'],
                );
                $totalUser += $group['total'];
                $totalLogin += $group['logged_in'];
            }
            $output[] = array(
                'GROUP_ID' => '',
                'GROUP_NAME' => 'Total',
                'GROUP_USER' => $totalUser,
                'GROUP_LOGGED_IN' => $totalLogin,
            );
        }
        break;

    case 'service_www':
        $context = \Proxy\Server::getInstance()->getContext();
        if (@file_get_contents('https://repo.schoolbox.com.au/testfile.txt', false, $context)) {
            $output[] = array('WWW_LIGHT'  => 'success',
                              'WWW_STATUS' => 'Available'
            );
        } else {
            $output[] = array('WWW_LIGHT'  => 'alert',
                              'WWW_STATUS' => 'Inaccessible');
        }

        break;

    case 'letsdebug':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        $domain = "{$config->get('site_hostname')}.{$config->get('site_domain')}";
        $letsdebug_url = "https://letsdebug.net/";
        $method = "http-01";
        $client = new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions(), 'headers' => ['Content-Type' => 'application/json']]);
        $response = $client->post($letsdebug_url, [
            'body' => json_encode([
                'domain' => $domain,
                'method' => $method,
            ]),
        ]);
        $output[] = json_decode($response->getBody()->getContents(), true);
        break;
        // other cases unimplemented for now
    case 'storage':
    case 'memory':
    case 'service_external_db':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        $external_db_version = '';
        $external_db = ucfirst($config->get('external_type'));
        if ($config->get('external_enable')) {
            $external_ping = new \External_Ping();
            if ($external_ping->isResponsive()) {
                $external_db_light = 'success';
                $external_db_status = 'Online';
                $external_db_version = $external_ping->getVersion();
            } else {
                $external_db_light = 'alert';
                $external_db_status = 'Offline';
            }
        } else {
            $external_db_light = 'alert';
            $external_db_status = 'Disabled';
        }
        $output = [
            'external_db' => $external_db,
            'external_db_light' => $external_db_light,
            'external_db_status' => $external_db_status,
            'external_db_version' => $external_db_version,
        ];
        break;
    case 'service_email':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        if ($config->get('mail_enable') && $config->get('mail_smtp_host')) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));

            if (@socket_connect($socket, $config->get('mail_smtp_host'), $config->get('mail_smtp_port'))) {
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
        $output = [
            'email_light' => $email_light,
            'email_status' => $email_status,
        ];
        break;
    case 'service_ldap':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        $ldap_light = 'alert';
        $ldap_status = 'Disabled';

        $ldap_alt_light = 'alert';
        $ldap_alt_status = 'Disabled';

        if ($config->get('ldap_enable')) {
            $tempsession = new \Session();

            // Check primary LDAP server bind

            if (trim($config->get('ldap_host')) !== "") {
                $ldapbind = $tempsession->ldapConnect(
                    $config->get('ldap_host'),
                    $config->get('ldap_user'),
                    $config->get('ldap_pass'),
                    $config->get('ldap_ca_certificate')
                );

                if ($ldapbind) {
                    $ldap_light = 'success';
                    $ldap_status = 'Online';
                } else {
                    $ldap_status = 'Offline';
                }

                unset($ldapbind);
            }

            // Check secondary LDAP server bind

            if (trim($config->get('ldap_host_alt')) !== "") {
                $ldapbind = $tempsession->ldapConnect(
                    $config->get('ldap_host_alt'),
                    $config->get('ldap_user_alt'),
                    $config->get('ldap_pass'),
                    $config->get('ldap_ca_certificate')
                );

                if ($ldapbind) {
                    $ldap_alt_light = 'success';
                    $ldap_alt_status = 'Online';
                } else {
                    $ldap_alt_status = 'Offline';
                }

                unset($ldapbind);
            }
        }
        $output = [
            'ldap_light' => $ldap_light,
            'ldap_status' => $ldap_status,
            'ldap_alt_light' => $ldap_alt_light,
            'ldap_alt_status' => $ldap_alt_status,
        ];
        break;
    case 'rss':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        $rss = new Alaress_Rss_Stream($config->get('release_notes_url'));
        $rss->stream();
        $output = $rss->getOutput();
        break;
    case 'ntp':
        $ntpServer = "pool.ntp.org";
        $socket    = stream_socket_client("udp://$ntpServer:123", $errno, $errstr, 1);
        if (!$socket) {
             $output = array('error' => 'Unable to connect to NTP server', 'errno' => $errno,);
             break;
        }
        stream_set_timeout($socket, 1);
        $data = "\x1b" . str_repeat("\0", 47);
        $result = fwrite($socket, $data);
        if (!$result) {
            $output = array('error' => 'Unable to write to NTP server', 'errno' => -1,);
            break;
        }
        $data = fread($socket, 48);
        // This occurs if firewall is blocking outbound connections to port 123
        if ($data === "" || $data === false) {
            $output = ['error' => 'Unable to fetch time from NTP server.', 'errno' => -1,];
            break;
        }
        fclose($socket);
        $decoded   = unpack('N12', $data);
        $timestamp = (int)sprintf('%u', $decoded[9]);
        $timestamp -= 2208988800; #convert ntp timestamp to unix timestamp
        $output = ['diff' => abs($timestamp - time())];
        break;
    case 'logs':
        if ($_SESSION['user']->isSuperuser()) {
            $logsRecent = LogMapper::getInstance()->findRecent();

            foreach ($logsRecent as $log) {
                $action = '';

                switch ($log['action']) {
                    case '1':
                        $action = 'Logged in';
                        break;
                    case '0':
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
                    case 'create':
                        $action = 'Added ' . $log['ip'];
                        break;
                    case 'delete':
                        $action = 'Deleted ' . $log['ip'];
                        break;

                    default:  //If unknown, skip output
                        continue 2;
                }

                if ($action !== '') {
                    $date = new Alaress_Date_Format($log['occurred'], Alaress_Date_Format::DATABASE_FORMAT);

                    $output[] = [
                        'date' => $date->getShort(),
                        'action' => $action,
                        'name' => \Scrub::text("{$log['firstname']} {$log['lastname']}")
                    ];
                }
            }
        }
        break;
    case 'rabbitmq':
        $amqpOnline = "online";
        $amqpLight = 'success';
        try {
            $kernel->get('amqp');
        } catch (\Throwable $e) {
            $amqpOnline = "offline";
            $amqpLight = 'alert';
        }
        $output = [
            'rabbitmq_status' => $amqpOnline,
            'rabbitmq_light' => $amqpLight,
            ];
        break;
    case 'ssl_cert':
        if (!exec(sprintf("INSTANCE_ID=%s schoolbox-read-instances --option hostnames", INSTANCE_ID), $execOutput, $resultCode)) {
            $output = [
                'error_code'    => 1,
                'error_message' => "Unable to derive instance hostnames",
            ];
        } else {
            $output = [];
            foreach ($execOutput as $host) {
                // Is this an IP address rather than hostname?
                if (preg_match("/^\d+\.\d+\.\d+\.\d+$/", $host)) {
                    continue;
                }
                $streamContext = stream_context_create(
                    [
                        'ssl' => [
                            'capture_peer_cert' => true,
                            'peer_name'         => $host,
                            'verify_peer'       => false,  // Required to fetch expired certificates
                            'allow_self_signed' => true,   // Required to fetch self-signed certificates (used in dev/test)
                        ],
                    ]
                );
                $timeout = 4.0;
                $client = stream_socket_client(
                    'ssl://127.0.0.1:443',
                    $errorNumber,
                    $errorDescription,
                    $timeout,
                    STREAM_CLIENT_CONNECT,
                    $streamContext
                );
                if ($client) {
                    $response              = stream_context_get_params($client);
                    $certificateProperties = openssl_x509_parse($response['options']['ssl']['peer_certificate']);

                    // Check cert is issued by LetsEncrypt for exact hostname
                    if ($certificateProperties['issuer']['O'] !== "Let's Encrypt") {
                        // Not issued by LetsEncrypt
                        $isCustomCert = true;
                    } else {
                        // Does not contain exact match
                        $isCustomCert = !str_contains("DNS:{$certificateProperties['extensions']['subjectAltName']}", $host);
                    }
                    $expiry = (int)(($certificateProperties['validTo_time_t'] - time()) / 3600 / 24);

                    $output["isCustomCert"] = ($output["isCustomCert"] ?? false) || $isCustomCert;
                    $output["expiry"]       = array_key_exists("expiry", $output) ? min($expiry, $output["expiry"]) : $expiry;
                } else {
                    $output = [
                        'error_code'    => $errorNumber,
                        'error_message' => $errorDescription,
                    ];
                    break;
                }
            }
        }
        break;
    case 'turnitin':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        try {
            $output = (new \Schoolbox\Learning\Turnitin\Admin\Status($config))->generate();
        } catch (JsonException $e) {
        }
        break;
    case 'kami':
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');
        $light = 'success';
        $status = 'online';
        $connector  = new Schoolbox\Learning\InlineGrader\Kami\Connector(
            new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]),
            $config->get(ConfigMapper::NAME_KAMI_ENABLED),
            $config->get(ConfigMapper::NAME_KAMI_API_HOST),
            $config->get(ConfigMapper::NAME_KAMI_API_KEY),
            $config->get(ConfigMapper::NAME_SITE_SCHOOL_CODE),
        );
        if (!$connector->isOnline()) {
            $light = 'alert';
            $status = 'offline';
        }
        $output = [
            'kami_status' => $status,
            'kami_light' => $light,
        ];
        break;
    case 'service_saml_cert':
        $samlCertHelper = new SamlCertHelper();

        $samlWidget = $samlCertHelper->buildWidget(
            $config->get('saml_idp_certificate') ?? "",
            $config->get('saml_idp_metadata_url') ?? ""
        );
        $output[] = [
            'SAML_LIGHT'   => $samlWidget->getLight(),
            'SAML_MESSAGE' => $samlWidget->getMessage(),
        ];
        break;
    case 'virus':
        $virusScanner = new Schoolbox\Resource\Storage\Virus\Scanner($kernel->get('filesystem.temp'));
        $status = $virusScanner->checkExists();

        $output['virus_status'] = $status;

        switch ($status) {
            case 'online':
                $output['virus_light'] = 'success';
                break;
            case 'offline':
                $output['virus_light'] = 'alert';
                break;
            case 'unavailable':
                $output['virus_light'] = 'warning';
        }
        break;
    default:
        break;
}

// to test how it looks with delay
// sleep(2);

header("Content-type: application/json");
print json_encode($output);
