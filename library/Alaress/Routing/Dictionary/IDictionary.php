<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Dictionary;

/**
 * A routing dictionary is a utility for figuring out how to construct the Trie structure of a "route table".
 * It provides the sorting algorithm used, as well as a translation table of literal-to-pattern
 *
 * @package Alaress\Routing\Dictionary
 */
interface IDictionary
{

    /**
     * Return the delimiter this dictionary defines for separating terms.
     *  - For URLs or *nix paths this is `/`
     *  - For MSDOS paths this is `\\` (good luck)
     *  - For Redis this is `:` or `.`
     *  - For AMQP this is `.`
     *
     * @return string
     */
    public function getDelimiter();


    /**
     * Translate the given shard according to this dictionary, converting literals into regex patterns.
     * This is useful for being able to define your own "pattern language", such as:
     *   *:literal.#   where `*` matches a single word and `#` matches multi-word
     *
     * @param string $shard
     * @return string
     */
    public function translateShard($shard);


    /**
     * Translate the given string according to this dictionary, converting literals into regex patterns.
     * This is useful for being able to define your own "pattern language", such as:
     *   *:literal.#   where `*` matches a single word and `#` matches multi-word
     *
     * @param string $string
     * @return string
     */
    public function translateWhole($string);


    /**
     * Invoked by ksort to sort the trie structure
     *
     * @param string $keyA
     * @param string $keyB
     * @return int
     */
    public function sort($keyA, $keyB);
}
