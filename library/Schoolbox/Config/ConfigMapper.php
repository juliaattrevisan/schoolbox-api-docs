<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Config;


use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;


/**
 * Class ConfigMapper
 * @package Schoolbox\Config
 * @method Config findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 */
class ConfigMapper extends Mapper implements IConfigLoader
{

    /** @var array */
    protected $cache = array();

    /** @var array */
    protected $consts;

    /**
     * This mapper has no database by default.
     * (It can be installed later?)
     */
    protected function setupDatabase()
    {
    }

    /**
     * This object IS a config loader, so the method for building it returns itself.
     *
     * @return $this
     */
    protected function buildConfigLoader()
    {
        // this line will possibly blow up in the Doctor's face
        return $this;
    }

    /**
     * Set up required constants.
     */
    protected function setup()
    {
        $w = realpath(__DIR__ . "/../../..");
        $this->consts = array(
            'siteRoot' => $w,
            'webRoot'  => "$w/www",
            'webBase'  => ''
        );
    }


    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('config');
        $this->hasModel(Config::class);

        $this->hasMap(
            array(
                'column'     => 'configID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'value',
                'property' => 'value',
                'type'     => self::TYPE_STRING,
            )
        );

    }


    /**
     * Get a config variable
     *
     * @param string $var
     * @return mixed
     */
    public function get($var)
    {
        if (isset($this->consts[$var])) {
            return $this->consts[$var];
        } elseif (defined($var)) {
            return constant($var);
        }
        if (empty($this->cache)) {
            $this->loadCache();
        }
        if (isset($this->cache[$var])) {
            return $this->cache[$var];
        }
        return null;
    }

    /**
     * Load entire cache at once, not just one result at a time
     */
    protected function loadCache()
    {
        // Load database on request only
        if (!($this->database instanceof \Database)) {
            $this->database = \Database::getInstance();
        }
        // Perform this common action without the overhead of hydration
        foreach ($this->database->query("SELECT `name`, `value` FROM `{$this->getTable()}`") as $row) {
            $this->cache[$row['name']] = $row['value'];
        }
    }


    /**
     * Drop any cached config
     *
     * @return void
     */
    public function dropCache()
    {
        $this->cache = array();
    }

    /**
     * Override notifications to silence them
     *
     * @param object $object
     * @param string $verb
     * @param null $previous
     * @return void
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        //Silence notifications
    }

}
