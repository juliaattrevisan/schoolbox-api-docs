<?php
/**
 * Sorter
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;


class Sorter {

    /**
     * An associative array to uasort
     * @var array
     */
    private $data = array();

    /**
     * Array key to sort on
     *
     * @var string
     */
    private $key = '';

    public function __construct($data) {
        $this->setData($data);
    }

    /**
     * Find ascending
     *
     * @param $key
     * @return array
     */
    public function findAscending($key = null) {
        $temp = $this->getData();

        if ($key != null) {
            $this->setKey($key);

            uasort($temp, array($this, 'cmpAscendingKey'));
        }
        else {
            uasort($temp, array($this, 'cmpAscending'));
        }

        return $temp;
    }

    /**
     * Find descending
     *
     * @param $key
     * @return array
     */
    public function findDescending($key = null) {
        $temp = $this->getData();

        if ($key != null) {
            $this->setKey($key);

            uasort($temp, array($this, 'cmpDescendingKey'));
        }
        else {
            uasort($temp, array($this, 'cmpDescending'));
        }

        return $temp;
    }

    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpAscendingKey($a, $b) {
        return $b[$this->getKey()] - $a[$this->getKey()];
    }

    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpDescendingKey($a, $b) {
        return $a[$this->getKey()] - $b[$this->getKey()];
    }

    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpAscending($a, $b) {
        return $a - $b;
    }

    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpDescending($a, $b) {
        return $b - $a;
    }

    /**
     * Set data
     *
     * @param array $data
     */
    private function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get data
     *
     * @return array
     */
    private function getData()
    {
        return $this->data;
    }

    /**
     * Set key
     *
     * @param string $key
     */
    private function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * Get key
     *
     * @return string
     */
    private function getKey()
    {
        return $this->key;
    }
}