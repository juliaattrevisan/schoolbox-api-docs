<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

use Monolog\Formatter\NormalizerFormatter;
use Monolog;

class CliFormatter extends NormalizerFormatter
{
    // fg colourings
    private const BLACK        = '0;30';
    private const DARK_GRAY    = '1;30';
    private const BLUE         = '0;34';
    private const LIGHT_BLUE   = '1;34';
    private const GREEN        = '0;32';
    private const LIGHT_GREEN  = '1;32';
    private const CYAN         = '0;36';
    private const LIGHT_CYAN   = '1;36';
    private const RED          = '0;31';
    private const LIGHT_RED    = '1;31';
    private const PURPLE       = '0;35';
    private const LIGHT_PURPLE = '1;35';
    private const BROWN        = '0;33';
    private const YELLOW       = '1;33';
    private const LIGHT_GRAY   = '0;37';
    private const WHITE        = '1;37';

    // bg colourings
    private const BG_BLACK      = '40';
    private const BG_RED        = '41';
    private const BG_GREEN      = '42';
    private const BG_YELLOW     = '43';
    private const BG_BLUE       = '44';
    private const BG_MAGENTA    = '45';
    private const BG_CYAN       = '46';
    private const BG_LIGHT_GRAY = '47';

    /** @const int how many decimals of time you want */
    public const TIME_RESOLUTION = 5;

    /** @var array */
    protected static $formats = [
        // regular colours
        Monolog\Logger::DEBUG     => ['d', self::CYAN, '' ],
        Monolog\Logger::INFO      => [ ' ', '', '' ],
        Monolog\Logger::NOTICE    => [' ', self::BROWN, '' ],
        Monolog\Logger::WARNING   => ['*', self::BLACK, self::BG_YELLOW ],
        Monolog\Logger::ERROR     => ['*', self::RED, '' ],
        Monolog\Logger::CRITICAL  => ['!', self::LIGHT_RED, '' ],
        Monolog\Logger::ALERT     => ['!', self::WHITE, self::BG_RED ],
        Monolog\Logger::EMERGENCY => ['!', self::WHITE, self::BG_MAGENTA ],

        // custom character colours
        '>' => ['>', self::WHITE, '' ],
        '<' => ['<', self::WHITE, '' ],
        '-' => ['-', self::BLUE, '' ],
        '+' => ['+', self::GREEN, '' ],
        '?' => ['?', self::YELLOW, '' ],
        'e' => ['e', self::YELLOW, '' ],
        '~' => ['~', self::LIGHT_RED, '' ]
    ];


    /** @var bool */
    protected $porcelain;

    /** @var bool */
    protected $contextualise;

    /**
     * @param bool $porcelain
     * @param bool $outputContext
     */
    public function __construct($porcelain = false, $outputContext = false)
    {
        $this->porcelain = $porcelain;
        $this->contextualise = $outputContext;
    }


    /**
     * Formats a log record.
     *
     * @param  Monolog\LogRecord $record A record to format
     * @return mixed The formatted record
     */
    public function format(Monolog\LogRecord $record)
    {
        return $this->decorate($this->generateRecord($record), $record['level'], $record['context']);
    }


    /**
     * @param Monolog\LogRecord $record
     * @return mixed|string
     */
    protected function generateRecord(Monolog\LogRecord $record)
    {
        $map = [
            'memory_usage'      => 'mem',
            'memory_peak_usage' => 'mempeak',
            'process_id'        => 'pid'
        ];

        // if this is already formatted like a log, don't reformat it
        if (preg_match("`\[.\].* => `", $record->message)) {
            return $record->message;
        }

        $datetime = $record->datetime;
        $date = $datetime->format('Y-m-d H:i:s');
        if (isset($record->extra['microtime'])) {
            $date .= substr($record->extra['microtime'], 1, self::TIME_RESOLUTION + 1);
            unset($record->extra['microtime']);
        }

        $pieces = array("[$date]");

        foreach ($record['extra'] as $key => $value) {
            if ($this->porcelain) {
                if (isset($map[$key])) {
                    $key = $map[$key];
                }
                $pieces[] = "[$key:$value]";
            } else {
                $pieces[] = "[$value]";
            }
        }

        $pieces[] = " => {$record->message}";

        if ($this->contextualise) {
            $context = $record->context;
            unset($context['char']);
            foreach ($context as $key => $value) {
                $pieces[] = "\n   $key => $value";
            }
        }

        return implode('', $pieces);
    }


    /**
     * @param string $string
     * @param int $level
     * @return string
     */
    protected function prependSeverity($string, $level)
    {
        $severity = $level;
        $char     = self::$formats[$level][0];

        $severity = $this->porcelain ? "[@$severity]" : '';

        return "[$char]$severity$string";
    }


    /**
     * @param string $string
     * @param int $level
     * @param array $context
     * @return string
     */
    protected function decorate($string, $level, $context)
    {
        if ($level <= Monolog\Logger::NOTICE && $level >= Monolog\Logger::INFO && isset($context['char'])) {
            $char = $context['char'][0];
            if (array_key_exists($char, self::$formats)) {
                $level = $char;
            }
        }

        $string = $this->prependSeverity($string, $level);

        if (!$this->porcelain) {
            if ($level >= Monolog\Logger::ALERT) {
                $string = "\n$string\n";
            }
            $string = $this->applyFormat($string, self::$formats[$level][1], self::$formats[$level][2]);
        }

        return "$string\n";
    }


    /**
     * @param string $string
     * @param string $fg
     * @param string $bg
     * @return string
     */
    protected function applyFormat($string, $fg = null, $bg = null)
    {
        // default to reset
        $colouring = "\033[0m";

        // going to apply *something*
        if ($fg || $bg) {
            $colouring = "";
        }
        // apply fg
        if ($fg) {
            $colouring .= "\033[{$fg}m";
        }
        // apply bg
        if ($bg) {
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
        foreach ($records as $record) {
            $result[] = $this->format($record);
        }
        return $result;
    }
}
