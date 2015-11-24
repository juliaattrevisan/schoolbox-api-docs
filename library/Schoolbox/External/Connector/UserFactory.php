<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Connector;


class UserFactory {
    const GENERAL = 1;
    const SYNC = 2;

    /**
     * Type
     *
     * @var string
     */
    private $type = self::GENERAL;

    /**
     * External DB type
     *
     * @var string
     */
    private $externalDbType = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->setExternalDbType(\Config::get('external_type'));
    }

    /**
     * Choose which part of the Connector to spawn
     *
     * @return mixed
     * @throws \Exception
     */
    public function produce() {
        $prefix = '\Schoolbox\External\Connector\\' .  ucwords($this->getExternalDbType());

        /* School-specific version of the Connector
         */
        $specific = ucwords(\Config::get('site_location'));
        $general = 'Base';

        switch($this->getType()) {
            case self::GENERAL:
                $class = 'User';
                break;

            case self::SYNC:
            default:
                $class = 'Sync';
                break;
        }

        $specificClassname = "{$prefix}\\{$specific}\\{$class}";
        $generalClassname = "{$prefix}\\{$general}\\{$class}";

        if (class_exists($specificClassname)) {
            return new $specificClassname;
        }
        else if (class_exists($generalClassname)) {
            return new $generalClassname;
        }

        throw new \Exception("Unknown classes: {$specificClassname} {$generalClassname}");
    }

    /**
     * Use usual generic class methods
     */
    public function useGeneral() {
        $this->setType(self::GENERAL);
    }

    /**
     * Use Sync (UPS) classes
     */
    public function useSync() {
        $this->setType(self::SYNC);
    }

    /* Getters/Setters
     */

    /**
     * @param string $type
     */
    private function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    private function getType()
    {
        return $this->type;
    }

    /**
     * @param string $externalDbType
     */
    private function setExternalDbType($externalDbType)
    {
        $this->externalDbType = $externalDbType;
    }

    /**
     * @return string
     */
    private function getExternalDbType()
    {
        return $this->externalDbType;
    }
}