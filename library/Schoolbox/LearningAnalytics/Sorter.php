<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

class Sorter
{

    /**
     * An associative array to uasort
     *
     * @var array
     */
    private $data;

    /**
     * Array key to sort on
     *
     * @var string
     */
    private $key = '';


    /**
     * Sorter constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }


    /**
     * Find ascending
     *
     * @param $key
     * @return array
     */
    public function findAscending($key = null)
    {
        $temp = $this->data;

        if ($key !== null) {
            $this->key = $key;

            uasort($temp, [$this, 'cmpAscendingKey']);
        } else {
            uasort($temp, [$this, 'cmpAscending']);
        }

        return $temp;
    }


    /**
     * Find descending
     *
     * @param $key
     * @return array
     */
    public function findDescending($key = null)
    {
        $temp = $this->data;

        if ($key !== null) {
            $this->key = $key;

            uasort($temp, [$this, 'cmpDescendingKey']);
        } else {
            uasort($temp, [$this, 'cmpDescending']);
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
    private function cmpAscendingKey($a, $b)
    {
        if ($a[$this->key] > $b[$this->key]) {
            return 1;
        } else if ($a[$this->key] === $b[$this->key]) {
            return 0;
        }

        return -1;
    }


    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpDescendingKey($a, $b)
    {
        if ($a[$this->key] > $b[$this->key]) {
            return -1;
        } else if ($a[$this->key] === $b[$this->key]) {
            return 0;
        }

        return 1;
    }


    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpAscending($a, $b)
    {
        if ($a > $b) {
            return 1;
        } else if ($a === $b) {
            return 0;
        }

        return -1;
    }


    /**
     * Custom compare function
     *
     * @param $a
     * @param $b
     * @return mixed
     */
    private function cmpDescending($a, $b)
    {
        if ($a < $b) {
            return 1;
        } else if ($a === $b) {
            return 0;
        }

        return -1;
    }
}
