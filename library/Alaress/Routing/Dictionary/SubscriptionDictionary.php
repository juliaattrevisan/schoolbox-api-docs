<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Dictionary;

use InvalidArgumentException;

class SubscriptionDictionary implements IDictionary
{
    const WORD = '\*';
    const WILD = '#';
    const NUM  = '\d+';

    /** @var array */
    private $shardDictionary = [];

    /** @var array */
    private $wholeDictionary = [];

    /** @var string */
    private $delimiter;

    /** @var array */
    private $map;


    /**
     * SubscriptionDictionary constructor.
     *
     * @param string $delimiter
     * @throws InvalidArgumentException
     */
    public function __construct($delimiter)
    {
        if (!is_string($delimiter) || empty($delimiter)) {
            throw new InvalidArgumentException('Delimiter cannot be empty');
        }

        $this->delimiter = $delimiter;

        $word = "[^\\{$delimiter}]+";

        $this->map = [
            self::WORD => $word,  // asterisk matches single word
            self::WILD => '.*',   // hash matches multiple words
            self::NUM  => '(\d+)' // compress digits into a \d+ check rather than storing individually

        ];

        foreach ($this->map as $pattern => $transform) {
            $this->shardDictionary["`^$pattern$`"] = $transform;
            $this->wholeDictionary["`$pattern`"]   = $transform;
        }

        // pre-cache this transformation into the format we will need
        $this->shardDictionary = [
            array_keys($this->shardDictionary), array_values($this->shardDictionary)
        ];

        $this->wholeDictionary = [
            array_keys($this->wholeDictionary), array_values($this->wholeDictionary)
        ];
    }


    /**
     * @inheritdoc
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }


    /**
     * @inheritdoc
     */
    public function translateShard($shard)
    {
        return $this->translate($shard, $this->shardDictionary);
    }


    /**
     * @inheritdoc
     */
    public function translateWhole($string)
    {
        return $this->translate($string, $this->wholeDictionary);
    }


    /**
     * Helper method for the two translate methods
     *
     * @param string $string
     * @param array  $dictionary
     * @return string
     */
    private function translate($string, array &$dictionary)
    {
        return preg_replace($dictionary[0], $dictionary[1], $string);
    }


    /**
     * @inheritdoc
     */
    public function sort($keyA, $keyB)
    {
        if ($this->isWild($keyA)) {
            return 1;
        }
        if ($this->isWild($keyB)) {
            return -1;
        }
        if ($this->isWord($keyA)) {
            return 1;
        }
        if ($this->isWord($keyB)) {
            return -1;
        }
        if ($this->isNumber($keyA)) {
            return 1;
        }
        if ($this->isNumber($keyB)) {
            return -1;
        }
        return strcmp($keyA, $keyB);
    }


    /**
     * Helper method for sorting: is this key a wildcard
     *
     * @param string $key
     * @return bool
     */
    private function isWild($key)
    {
        return $key === $this->map[self::WILD];
    }


    /**
     * Helper method for sorting: is this key a word
     *
     * @param string $key
     * @return bool
     */
    private function isWord($key)
    {
        return $key === $this->map[self::WORD];
    }


    /**
     * Helper method for sorting: is this key a number
     *
     * @param string $key
     * @return bool
     */
    private function isNumber($key)
    {
        return $key === $this->map[self::NUM];
    }
}
