<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\MobileApp;

use Schoolbox\Resource\Storage\IStorage;


class App
{
    /** @var string */
    private $id;

    /** @var IStorage */
    private $pem;


    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return IStorage
     */
    public function getPem()
    {
        return $this->pem;
    }


    /**
     * @param IStorage $pem
     * @return $this
     */
    public function setPem($pem)
    {
        $this->pem = $pem;
        return $this;
    }

}