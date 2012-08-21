<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class ClientDevice {

    const DEV_UNKNOWN      = -1;
    const DEV_IPHONE       = 0;
    const DEV_IPAD         = 1;
    const DEV_ANDROID      = 2;
    const DEV_WIN_MOBILE   = 3;
    const DEV_PC           = 4;

    const TYPE_UNKNOWN     = -1;
    const TYPE_PHONE       = 0;
    const TYPE_TABLET      = 1;
    const TYPE_DESKTOP     = 2;

    const BROWSER_UNKNOWN  = -1;
    const BROWSER_SAFARI   = 0;
    const BROWSER_CHROME   = 1;
    const BROWSER_FIREFOX  = 2;
    const BROWSER_OPERA    = 3;
    const BROWSER_ANDROID  = 4;

    private $device;
    private $type;
    private $browser;

    /**
     * Constructor should populate device, browser and type members.
     * Replace this constructor with any logic you see fit if not User-Agent.
     *
     * @param $environment
     */
    public function __construct($environment) {

        $e = json_decode($environment);

        // runs IOS
        if($e->ios) {
            if($e->iphone) {
                $this->device  = self::DEV_IPHONE;
                $this->type = self::TYPE_PHONE;
            }
            elseif($e->ipad) {
                $this->device  = self::DEV_IPAD;
                $this->type = self::TYPE_TABLET;
            }
        }
        // doesn't run IOS
        else {
            // detect type
            $this->type = $e->tablet ? self::TYPE_TABLET : self::TYPE_PHONE;

            // detect device platform
            if($e->android) {
                $this->device  = self::DEV_ANDROID;
            }
            elseif($e->wp) {
                $this->device  = self::DEV_WIN_MOBILE;
            }
            else {
                $this->device = self::DEV_UNKNOWN;
            }
        }

        // Chris has deemed this irrelevant
        $this->browser = self::BROWSER_UNKNOWN;

    }

    public function getDevice() {
        return $this->device;
    }

    public function getType() {
        return $this->type;
    }

    public function getBrowser() {
        return $this->browser;
    }

    public function toArray() {
        return array(
            'device' => $this->device,
            'type' => $this->type,
            'browser' => $this->browser,
        );
    }

}
