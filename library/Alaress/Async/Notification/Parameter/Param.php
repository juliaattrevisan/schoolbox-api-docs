<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Parameter;

/**
 * This class is designed to be overridden into a collection of specialised param types:
 * eg. UserParam, CategoryParam, etc.
 *
 * Class Param
 * @package Alaress\Async\Notification
 */
class Param implements ParameterInterface
{

    /** @var string */
    protected $text;

    /** @var string */
    protected $href;

    /** @var int */
    protected $mode;

    /**
     * Timestamp used for STACKING_APPEND
     * @var int
     */
    protected $timestamp = 0;

    /**
     * @param string      $text
     * @param string|null $href
     * @param int         $stacking
     */
    public function __construct($text, $href = null, $stacking = self::STACKING_REPLACE)
    {
        $this->text = $text;
        $this->href = $href;
        $this->mode = $stacking;
    }


    /**
     * @return string
     */
    public function getHref()
    {
        return $this->href;
    }


    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }


    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }


    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *       which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return [
            'text' => $this->text,
            'href' => $this->href,
            'mode' => $this->mode,
            'time' => $this->timestamp,
        ];
    }


    /**
     * Used as a key to this param, to remove duplicates
     * @return string
     */
    public function getHash()
    {
        // do not include time
        $serial = [
            'text' => $this->text,
            'href' => $this->href,
            'mode' => $this->mode,
        ];
        return sha1(json_encode($serial));
    }


    /**
     * Flatten this notification instance
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }


    /**
     * Setter for timestamp
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
