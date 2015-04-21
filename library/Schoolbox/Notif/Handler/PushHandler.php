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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MobileApp;
use Schoolbox\UserMapper;

class PushHandler extends AbstractProcessingHandler
{

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record)
    {
        $conf = ConfigMapper::getInstance();

        preg_match('`instant.user.([0-9]+)`', $record['channel'], $matches);
        $user = UserMapper::getInstance()->findById($matches[1]);

        $services = array();
        if ($conf->get('push_notifications_enabled')) {
            if ($conf->get('push_gcm_api_key')) {
                $services[] = new Cloud\Google\GCM($conf->get('push_gcm_api_key'));
            }
            if ($conf->get('push_apns_cert_path')) {
                $services[] = new Cloud\Apple\APNS(ZendService\Apple\Apns\Client\Message::SANDBOX_URI, $conf->get('push_apns_cert_path'), $conf->get('push_apns_cert_key') ?: null);
            }
        }
        $cloudManager = new MobileApp\ServiceManager($services);

        $cloudManager->push($user->getId(), $record['message']);
    }

} 