<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Resource\Attachment;

use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;

/**
 * Class Factory
 *
 * @package Schoolbox\Resource\Attachment
 */
class Factory
{

    const DEFAULT_NAME_PATTERN = '\\Schoolbox\\Resource\\Attachment\\Filesystem\\%sController';

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
    public function __construct(array $classmap = [])
    {
        $this->classmap = $classmap;

        $this->setSource('')
             ->setMimeFilter(null)
             ->allowMulti(false);
    }

    /**
     * @param string $source
     * @return self
     */
    public function setSource($source)
    {
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
     * @param string $source
     * @return ControllerInterface
     * @throws OutOfRangeException
     */
    public function build(string $source): ControllerInterface
    {
        $classname = $this->classmap[$source] ?? sprintf(self::DEFAULT_NAME_PATTERN, ucfirst($source));
var_dump($source);
var_dump($this->classmap);
var_dump($classname);
        if (!class_exists($classname)) {
            throw new OutOfRangeException("No controller found: {$source}");
        }

        $controller = new $classname($this->multi, $this->mime, $this->conf, $this->rosetta);

        if (!$controller instanceof ControllerInterface) {
            throw new OutOfRangeException('Controller does not implement ControllerInterface');
        }

        return $controller;
    }


    /**
     * @return ControllerInterface[]
     */
    public function getMappedControllers(): array
    {
        $controllers = [];

        foreach ($this->classmap as $source => $controller) {
            try {
                $controllers[] = $this->build($source);
            } catch (OutOfRangeException $e) {

                /* Suppress filesystems whose controllers couldn't spawn
                 */
            }
        }

        return $controllers;
    }
}
