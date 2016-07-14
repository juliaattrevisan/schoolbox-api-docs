<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\MobileApp;


use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Alaress\Async\Notification\Cloud;
use Schoolbox\MobileApp;
use Schoolbox\Resource\Storage;
use ZendService\Apple\Apns;

class ServiceManagerFactory
{

    const TEMP_CERT_FILENAME = '/sbapns.pem';

    /**
     * @var IConfigLoader
     */
    private $conf;
    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * ServiceManagerFactory constructor.
     *
     * @param IConfigLoader   $conf
     * @param LoggerInterface $log
     */
    public function __construct(IConfigLoader $conf, LoggerInterface $log)
    {
        $this->conf = $conf;
        $this->log = $log;
    }


    /**
     * @param array $providers
     * @return ServiceManager
     */
    public function build(array $providers)
    {
        $services = [];
        $log = $this->log;

        if ($this->conf->get('push_notifications_enabled')) {

            if (in_array(Registry::PROVIDER_GOOGLE, $providers)) {
                try {
                    $services[] = $this->buildGoogleService();
                    $log->debug("Added GCM service");
                } catch (OutOfBoundsException $e) {
                    $log->debug($e->getMessage());
                }
            }

            if (in_array(Registry::PROVIDER_APPLE, $providers)) {
                try {
                    $services[] = $this->buildAppleService();
                    $log->debug("Added APNS service");
                } catch (OutOfBoundsException $e) {
                    $log->debug($e->getMessage());
                }
            }

        } else {
            $log->debug("Push notifications are not enabled");
        }

        // return this anyway, even if it's empty
        return new MobileApp\ServiceManager($services, $log);
    }


    /**
     * @throws OutOfBoundsException
     * @return Cloud\Google\GCM
     */
    protected function buildGoogleService()
    {
        $conf = $this->conf;

        if ($conf->get('push_gcm_api_key')) {
            return new Cloud\Google\GCM($conf->get('push_gcm_api_key'));
        }
        throw new OutOfBoundsException("GCM is not enabled");
    }


    /**
     * @throws OutOfBoundsException
     * @return Cloud\Apple\APNS
     */
    protected function buildAppleService()
    {
        $conf = $this->conf;
        $log  = $this->log;

        if ($conf->get('push_apns_cert_path')) {

            // this is the only way I can think of that will be AWS S3 compatible
            $tempCert = $this->getTempCertFilename();

            // since this is on-construction, check the file in play and see if it needs updating
            if ($this->apnsCertRequiresUpdate($conf->get('push_apns_cert_path'))) {
                // copy the cert from storage
                $fp = fopen($tempCert, 'w');
                Storage\Mapper::getInstance()->findByHash($conf->get('push_apns_cert_path'))->pipe($fp);
                fclose($fp);
                $log->debug("Updated Apple cert");

            } else {
                $log->debug("Apple cert is up to date");
            }

            return new Cloud\Apple\APNS(Apns\Client\Message::PRODUCTION_URI, $tempCert, $conf->get('push_apns_cert_key') ?: null);
        }

        throw new OutOfBoundsException("APNS is not enabled");
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
        return sys_get_temp_dir() . self::TEMP_CERT_FILENAME;
    }

}
