<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

/**
 * Class CodeCodec
 *
 * Return a string representation of a (year group, subject code, class) tuple
 * This is a format we adopted for tass more-or-less across the board
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class CodeCodec
{

    /**
     * Encodes three pieces of data into a hyphen-separated string
     * Year Group, Subject Code, Class are concatenated into the format
     * "yearGroup-subjectCode-class", such as:
     *
     * 10-ABC123-2
     *
     * Note that pieces can be null (empty), in particular, the Year Group.
     * It still obeys the same format, and could end up looking like this:
     *
     * -ABC123-2
     *
     * I haven't seen other combinations but I can't trust tass really, so
     * make it as left-leaning as possible and include worst possible case --
     *
     * @param string $yearGroup
     * @param string $subjectCode
     * @param string $class
     * @return string
     */
    public function encode(string $yearGroup, string $subjectCode, string $class)
    {
        return "{$yearGroup}-{$subjectCode}-{$class}";
    }
}
