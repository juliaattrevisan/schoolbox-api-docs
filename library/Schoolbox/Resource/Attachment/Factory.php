<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment;


use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;

class Factory {

    const DEFAULT_NAME_PATTERN = '\\Schoolbox\\Resource\\Attachment\\%s\\%sController';

    /** @var array */
    protected $classmap;

    /** @var string */
    protected $location;

    /** @var string */
    protected $source;

    /** @var string */
    protected $mime;

    /** @var bool */
    protected $multi;

    /** @var IConfigLoader */
    protected $conf;

    /** @var RosettaStone */
    protected $rosetta;


    /**
     * @param array $classmap
     */
    public function __construct(array $classmap = array())
    {
        $this->classmap = $classmap;

        $this->setSource('', '')
             ->setMimeFilter(null)
             ->allowMulti(false);
    }

    /**
     * @param string $location
     * @param string $source
     * @return self
     */
    public function setSource($location, $source)
    {
        $this->location = $location;
        $this->source   = $source;
        return $this;
    }


    /**
     * @param string $mime
     * @return self
     */
    public function setMimeFilter($mime)
    {
        $this->mime = $mime;
        return $this;
    }


    /**
     * @param bool $multi
     * @return self
     */
    public function allowMulti($multi)
    {
        $this->multi = $multi;
        return $this;
    }


    /**
     * @param IConfigLoader $conf
     * @return self
     */
    public function setConfig(IConfigLoader $conf)
    {
        $this->conf = $conf;
        return $this;
    }


    /**
     * @param RosettaStone $rosetta
     * @return self
     */
    public function setRosetta(RosettaStone $rosetta)
    {
        $this->rosetta = $rosetta;
        return $this;
    }


    /**
     * @throws OutOfRangeException
     * @return ControllerInterface
     */
    public function build()
    {
        if (isset($this->classmap[$this->location][$this->source])) {
            $classname = $this->classmap[$this->location][$this->source];
        } else {
            $classname = sprintf(self::DEFAULT_NAME_PATTERN, ucfirst($this->location), ucfirst($this->source));
        }

        if (!class_exists($classname)) {
            throw new OutOfRangeException("No controller found for " . implode(':', array($this->location, $this->source)));
        }

        $controller = new $classname($this->multi, $this->mime, $this->conf, $this->rosetta);

        if (!$controller instanceof ControllerInterface) {
            throw new OutOfRangeException("Controller does not implement ControllerInterface");
        }

        return $controller;
    }


    /**
     * @return ControllerInterface[]
     */
    public function getMappedControllers()
    {
        $controllers = array();
        foreach($this->classmap as $location => $data) {
            foreach($data as $source => $class) {
                $this->setSource($location, $source);
                try {
                    $controllers[] = $this->build();
                } catch (OutOfRangeException $e) {
                    // oh well
                }
            }
        }
        return $controllers;
    }

} 