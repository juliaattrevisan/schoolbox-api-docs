<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Usage;

/**
 * Class Item
 *
 * @package Schoolbox\Usage
 */
class Item
{

    /**
     * Name of disk usage
     *
     * @var string
     */
    private $name = '';


    /**
     * Occupies this many bytes
     *
     * @var int
     */
    private $bytes = 0;


    /**
     * Constructor
     *
     * @param string $name
     */
    public function __construct($name) {
        $this->setName($name);
    }


    /**
     * Add bytes to the already bitten
     *
     * @param int $bytes
     */
    public function add($bytes) {
        $this->bytes += (int)$bytes;
    }


    /* Getters/Setters
     */

    /**
     * Set bytes
     *
     * @param int $bytes
     */
    public function setBytes($bytes) {
        $this->bytes = $bytes;
    }

    /**
     * Get bytes
     *
     * @return int
     */
    public function getBytes() {
        return $this->bytes;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }
}
