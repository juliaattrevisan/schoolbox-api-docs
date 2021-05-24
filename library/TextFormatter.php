<?php

/**
 * Formats text strings into more meaningful stuff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class TextFormatter
{

    /**
     * Create a greeting string. If it's 5pm, change to Evening
     *
     * @return string
     */
    public static function greeting()
    {
        if (date('A') == 'AM') {
            return 'Good Morning';
        } else if (date('G') < 17) {
            return 'Good Afternoon';
        }

        return 'Good Evening';
    }


    /**
     * Cut a string to the character limit and append ellipsis (...)
     *
     * @param  string $content  String to cut
     * @param  int    $chars    Number of chars to cut to
     * @param  string $encoding Encoding of $content
     * @return string
     */
    public static function cutString($content, $chars, $encoding = 'UTF-8')
    {
        $summary = mb_substr($content, 0, $chars, $encoding);

        if (strlen($content) > strlen($summary)) {
            return $summary . '...';
        }

        return $summary;
    }


    /**
     * Cut off a summary at the fullstop within the character limit
     * Alternatively just at the word
     *
     * @param  string $content  String to cut
     * @param  int    $chars    Number of chars to cut to
     * @param  bool   $addEllipsis
     * @param  string $encoding Encoding of $content
     * @return string
     */
    public static function cutSentence($content, $chars, $addEllipsis = false, $encoding = 'UTF-8')
    {

        $content = strip_tags($content);

        if (mb_strlen($content, $encoding) < $chars) {
            return $content;
        }

        $summary   = mb_substr($content, 0, $chars, $encoding);
        $position  = mb_strrpos($summary, '.', null, $encoding) + 1;
        $positionQ = mb_strrpos($summary, '?', null, $encoding) + 1;
        $positionB = mb_strrpos($summary, '!', null, $encoding) + 1;

        if ($position > 1 && $position > $positionQ && $position > $positionB) {
            $output = mb_substr($summary, 0, $position, $encoding);
        } else if ($positionQ > 1 && $positionQ > $positionB) {
            $output = mb_substr($summary, 0, $positionQ, $encoding);
        } else if ($positionB > 1) {
            $output = mb_substr($summary, 0, $positionB, $encoding);
        } else {
            $position = mb_strrpos($summary, ' ', null, $encoding);
            $output   = mb_substr($summary, 0, $position, $encoding);

            $addEllipsis = true;
        }

        if ($output && $addEllipsis) {
            $output .= '...';
        }

        return $output;
    }


    /**
     * Output a line in CSV format
     *
     * @param array $line
     * @param string $delimiter
     * @param string $enclosure
     * @return string
     */
    public static function outputCSV($line, $delimiter = ',', $enclosure = '"')
    {
        // No leading delimiter
        $writeDelimiter = false;
        $string = '';

        foreach ($line as $dataElement) {
           // Replaces a double quote with two double quotes
            $dataElement = str_replace('"', '""', $dataElement);

           // Adds a delimiter before each field (except the first)
            if ($writeDelimiter) {
                $string .= $delimiter;
            }

           // Encloses each field with $enclosure and adds it to the string
            $string .= $enclosure . $dataElement . $enclosure;

           // Delimiters are used every time except the first.
            $writeDelimiter = true;
        }

        return $string;
    }
}
