<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Lumberjack;



use Monolog\Formatter\NormalizerFormatter;
use Monolog;

class CliFormatter extends NormalizerFormatter {

    // fg colourings
    const black        = '0;30';
    const dark_gray    = '1;30';
    const blue         = '0;34';
    const light_blue   = '1;34';
    const green        = '0;32';
    const light_green  = '1;32';
    const cyan         = '0;36';
    const light_cyan   = '1;36';
    const red          = '0;31';
    const light_red    = '1;31';
    const purple       = '0;35';
    const light_purple = '1;35';
    const brown        = '0;33';
    const yellow       = '1;33';
    const light_gray   = '0;37';
    const white        = '1;37';

    // bg colourings
    const bg_black      = '40';
    const bg_red        = '41';
    const bg_green      = '42';
    const bg_yellow     = '43';
    const bg_blue       = '44';
    const bg_magenta    = '45';
    const bg_cyan       = '46';
    const bg_light_gray = '47';

    /** @const int how many decimals of time you want */
    const TIME_RESOLUTION = 5;

    /** @var bool */
    protected $porcelain;

    /** @var bool */
    protected $contextualise;

    /**
     * @param bool $porcelain
     * @param bool $outputContext
     */
    public function __construct($porcelain = false, $outputContext = false) {
        $this->porcelain = $porcelain;
        $this->contextualise = $outputContext;
    }


    /**
     * Formats a log record.
     *
     * @param  array $record A record to format
     * @return mixed The formatted record
     */
    public function format(array $record)
    {
        $map = array(
            'memory_usage'      => 'mem',
            'memory_peak_usage' => 'mempeak',
            'process_id'        => 'pid'
        );

        // if this is already formatted like a log, don't reformat it
        if(preg_match("`\[.\].* => `", $record['message'])) {
            return $record['message'];
        }

        /** @var \DateTime $datetime */
        $datetime = $record['datetime'];
        $date = $datetime->format('Y-m-d H:i:s');
        if(isset($record['extra']['microtime'])) {
            $date .= substr($record['extra']['microtime'], 1, self::TIME_RESOLUTION + 1);
            unset($record['extra']['microtime']);
        }

        $pieces = array("[$date]");

        foreach($record['extra'] as $key => $value) {
            if($this->porcelain) {
                if(isset($map[$key])) {
                    $key = $map[$key];
                }
                $pieces[] = "[$key:$value]";
            }
            else {
                $pieces[] = "[$value]";
            }
        }

        $pieces[] = " => {$record['message']}";

        if($this->contextualise) {
            $context = $record['context'];
            unset($context['char']);
            foreach($context as $key => $value) {
                $pieces[] = "\n   $key => $value";
            }
        }

        $result = $this->decorate(implode('', $pieces), $record['level'], $record['context']);


        return $result;
    }


    /**
     * @param string $string
     * @param int $level
     * @param array $context
     * @return string
     */
    protected function decorate($string, $level, $context) {
        $formats = array(
            // regular colours
            Monolog\Logger::DEBUG     => array('d', self::cyan,       ''),
            Monolog\Logger::INFO      => array(' ', '',               ''),
            Monolog\Logger::NOTICE    => array(' ', '',               ''),
            Monolog\Logger::WARNING   => array('*', self::black,      self::bg_yellow),
            Monolog\Logger::ERROR     => array('*', self::red,        ''),
            Monolog\Logger::CRITICAL  => array('!', self::light_red,  ''),
            Monolog\Logger::ALERT     => array('!', self::white,      self::bg_red),
            Monolog\Logger::EMERGENCY => array('!', self::white,      self::bg_magenta),

            // custom character colours
            '>' => array('>', self::white,      ''),
            '<' => array('<', self::white,      ''),
            '-' => array('-', self::blue,       ''),
            '+' => array('+', self::green,      ''),
            '?' => array('?', self::yellow,     ''),
            'e' => array('e', self::yellow,     ''),
            '~' => array('~', self::light_red,  '')
        );

        $severity = $level;

        $char = $formats[$level][0];

        if($level <= Monolog\Logger::NOTICE && $level >= Monolog\Logger::INFO && isset($context['char'])) {
            $char = $context['char'][0];
            if(array_key_exists($char, $formats)) {
                $level = $char;
            }
        }

        $severity = $this->porcelain ? "[@$severity]" : '';

        $string = "[$char]$severity$string";

        if(!$this->porcelain) {
            if($level >= Monolog\Logger::ALERT) {
                $string = "\n$string\n";
            }
            $string = $this->applyFormat($string, $formats[$level][1], $formats[$level][2]);
        }

        return "$string\n";
    }


    /**
     * @param string $string
     * @param string $fg
     * @param string $bg
     * @return string
     */
    protected function applyFormat($string, $fg = null, $bg = null) {
        // default to reset
        $colouring = "\033[0m";

        // going to apply *something*
        if($fg || $bg) {
            $colouring = "";
        }
        // apply fg
        if($fg) {
            $colouring .= "\033[{$fg}m";
        }
        // apply bg
        if($bg) {
            $colouring .= "\033[{$bg}m";
        }

        return $colouring . $string . "\033[0m";
    }


    /**
     * Formats a set of log records.
     *
     * @param  array $records A set of records to format
     * @return mixed The formatted set of records
     */
    public function formatBatch(array $records)
    {
        $result = array();
        foreach($records as $record) {
            $result[] = $this->format($record);
        }
        return $result;
    }

}
