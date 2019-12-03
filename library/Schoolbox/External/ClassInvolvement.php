<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\External;

/**
 * Class ClassInvolvement
 *
 * Class involvement object for Schoolbox external connector.
 *
 * @package Schoolbox\External
 */
class ClassInvolvement
{

    /**
     * @var int
     */
    protected $id;


    /**
     * @var ClassEntry
     */
    protected $class;


    /**
     * @var ?string
     */
    protected $externalId;


    /**
     * @param  \Schoolbox\External\ClassEntry $class
     *
     * @return $this
     */
    public function setClass($class) {
        $this->class = $class;
        return $this;
    }


    /**
     * @return \Schoolbox\External\ClassEntry
     */
    public function getClass() {
        return $this->class;
    }


    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }


    /**
     * @return string|null
     */
    public function getExternalId()
    {
        return $this->externalId;
    }


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }
}
