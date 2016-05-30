<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;


use Alaress\Async\Notification\Cloud;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MobileApp;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;
use ZendService\Apple\Apns;

class PushHandler extends AbstractProcessingHandler
{

    /** @var MobileApp\ServiceManager */
    protected $cloudManager;

    /**
     * @param LoggerInterface $log    A logger to which we can log stuff... and things...
     * @param int             $level  The minimum logging level at which this handler will be triggered
     * @param bool            $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(LoggerInterface $log, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $conf = ConfigMapper::getInstance();

        $services = array();
        if ($conf->get('push_notifications_enabled')) {
            if ($conf->get('push_gcm_api_key')) {
                $services[] = new Cloud\Google\GCM($conf->get('push_gcm_api_key'));
            }
            if ($conf->get('push_apns_cert_path')) {

                // this is the only way I can think of that will be AWS S3 compatible
                $tempCert = $this->getTempCertFilename();

                // since this is on-construction, check the file in play and see if it needs updating
                if ($this->apnsCertRequiresUpdate($conf->get('push_apns_cert_path'))) {
                    // copy the cert from storage
                    $fp = fopen($tempCert, 'w');
                    Storage\Mapper::getInstance()->findByHash($conf->get('push_apns_cert_path'))->pipe($fp);
                    fclose($fp);
                }

                $services[] = new Cloud\Apple\APNS(Apns\Client\Message::PRODUCTION_URI, $tempCert, $conf->get('push_apns_cert_key') ?: null);
            }
        }

        $this->cloudManager = new MobileApp\ServiceManager($services, $log);
    }


    /**
     * @param string $hashInStorage
     * @return bool
     */
    protected function apnsCertRequiresUpdate($hashInStorage)
    {
        $tempCert = $this->getTempCertFilename();

        if (!is_readable($tempCert)) {
            return true;
        }
        if (!filesize($tempCert)) {
            return true;
        }
        if (sha1_file($tempCert) != $hashInStorage) {
            return true;
        }

        return false;
    }


    /**
     * @return string
     */
    protected function getTempCertFilename()
    {
        return sys_get_temp_dir() . '/sbapns.pem';
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record)
    {
        if (preg_match('`instant.user.([0-9]+)`', $record['channel'], $matches)) {
            $user = UserMapper::getInstance()->findById($matches[1]);
            $this->cloudManager->push($user->getId(), [$record['message']]);
        }
    }

} 
