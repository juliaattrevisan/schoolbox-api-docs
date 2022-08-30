<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment;

use Alaress_Date_Format;
use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\User;

/**
 * Class Factory
 *
 * @package Schoolbox\Resource\Attachment
 */
class Factory
{

    /**
     * Filesystem controllers follow this nomenclature
     */
    private const DEFAULT_NAME_PATTERN = '\\Schoolbox\\Resource\\Attachment\\Filesystem\\%sController';


    /**
     * @var array
     */
    protected $classmap;


    /**
     * @var string|bool|null
     */
    protected $mime;


    /**
     * @var bool
     */
    protected $multi;


    /**
     * @var IConfigLoader
     */
    protected $conf;


    /**
     * @var RosettaStone
     */
    protected $rosetta;


    /**
     * @var bool
     */
    protected $useFolder = true;


    /**
     * @var int
     */
    protected $defaultFolderId = 0;


    /**
     * @var User
     */
    private $user;


    /**
     * The expiry date to use for any files uploaded to the controllers this
     * factory creates, or null if not provided.
     */
    private ?Alaress_Date_Format $expiryDate = null;


    /**
     * @param array $classmap
     */
    public function __construct(array $classmap = [])
    {
        $this->classmap = $classmap;

        $this
             ->setMimeFilter(null)
             ->allowMulti(false);
    }


    /**
     * @param bool $useFolder
     * @return $this
     */
    public function setUseFolder(bool $useFolder): self
    {
        $this->useFolder = $useFolder;
        return $this;
    }


    /**
     * @param int $defaultFolderId
     * @return $this
     */
    public function setDefaultFolderId(int $defaultFolderId): self
    {
        $this->defaultFolderId = $defaultFolderId;
        return $this;
    }


    /**
     * @param string|bool|null $mime
     * @return self
     */
    public function setMimeFilter($mime): self
    {
        $this->mime = $mime;
        return $this;
    }


    /**
     * @param bool $multi
     * @return self
     */
    public function allowMulti($multi): self
    {
        $this->multi = $multi;
        return $this;
    }


    /**
     * @param IConfigLoader $conf
     * @return self
     */
    public function setConfig(IConfigLoader $conf): self
    {
        $this->conf = $conf;
        return $this;
    }


    /**
     * @param RosettaStone $rosetta
     * @return self
     */
    public function setRosetta(RosettaStone $rosetta): self
    {
        $this->rosetta = $rosetta;
        return $this;
    }


    /**
     * @param User $user
     * @return self
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @param Alaress_Date_Format|null $expiryDate
     * @return static
     */
    public function setExpiryDate(?Alaress_Date_Format $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
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

        if (!class_exists($classname)) {
            throw new OutOfRangeException("No controller found: {$source}");
        }

        $controller = new $classname(
            $this->multi,
            $this->mime,
            $this->conf,
            $this->rosetta,
            $this->useFolder,
            $this->defaultFolderId,
            $this->user
        );

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
