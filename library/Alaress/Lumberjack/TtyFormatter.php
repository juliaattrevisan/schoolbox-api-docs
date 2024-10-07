<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

use Monolog\LogRecord;

class TtyFormatter extends CliFormatter
{
    const P_PATTERN = "%s  %s  %s\n";
    const P_PAD = 5;

    /** @var int */
    private $tty = 0;

    /**
     * @param resource $out
     * @param bool $porcelain
     * @param bool $outputContext
     */
    public function __construct($out, $porcelain = false, $outputContext = false)
    {
        parent::__construct($porcelain, $outputContext);

        // yes I know using the @scream is bad, but I need warnings to *not* show up here if the given resource cannot be tty'd
        if (@posix_isatty($out)) {
            try {
                if (!getcwd()) {
                    // there's this bizarre quirk where SOMETIMES cwd might not be set
                    // or points to an invalid directory - ensure this is not the case
                    chdir('/');
                }
                $this->tty = (int)`tput cols`;
            } catch (\ErrorException $e) {
                // noop - just leave tty as 0
            }
        }
    }


    /**
     * @param LogRecord $record
     * @return mixed|string
     */
    protected function generateRecord(LogRecord $record)
    {
        if ($this->tty === 0) {
            // non-interactive, just generate a plain log
            return rtrim(parent::generateRecord($record));
        }

        $map = [
            'memory_usage'      => [ 'mem',     ' [%s'  ],
            'memory_peak_usage' => [ 'mempeak', '/%s]'  ],
            'process_id'        => [ 'pid',     ' (%s)' ]
        ];

        // if this is already formatted like a log, don't reformat it
        if (preg_match('`^\d+-\d+-\d+ \d+:\d+:\d+`', $record->message)) {
            return rtrim($record->message);
        }

        $datetime = $record->datetime;
        $date = $datetime->format('Y-m-d H:i:s');

        if (isset($record->extra['microtime'])) {
            $date .= substr($record->extra['microtime'], 1, self::TIME_RESOLUTION + 1);
            unset($record->extra['microtime']);
        }

        $left = [ $date ];

        if (is_array($record->extra)) {
            foreach ($record->extra as $key => $value) {
                if (isset($map[$key])) {
                    $value = sprintf($map[$key][1], $value);
                    $key = $map[$key][0];

                    if ($this->porcelain) {
                        $left[] = "[$key:$value]";
                    } else {
                        $left[] = $value;
                    }
                } else {
                    $left[] = "[$value]";
                }
            }
        }

        if (preg_match('`\[.\]\[`', $record->message)) {
            $c = '';
        } else {
            $c = '[ ' . ($record->context['char'] ?? ' ') . ' ]';
        }

        $pieces = [ rtrim($record->message, $c) ];

        // include context?
        if ($this->contextualise) {
            $context = $record->context;
            unset($context['char']);
            foreach ($context as $key => $value) {
                $pieces[] = "\n   $key => $value";
            }
        }

        return $this->render(implode('', $left), implode('', $pieces), $c);
    }


    /**
     * @param string $string
     * @param int    $level
     * @return string
     */
    protected function prependSeverity($string, $level)
    {
        if ($this->tty === 0) {
            return parent::prependSeverity($string, $level);
        }
        return $string;
    }


    /**
     * @param string $left
     * @param string $middle
     * @param string $right
     * @return string
     */
    private function render($left, $middle, $right)
    {
        $output = '';

        $leftLen = strlen($left);
        $rightLen = strlen($right);

        $maxMiddle = $this->tty - ($leftLen + $rightLen + static::P_PAD);

        $first = true;

        $cut = strpos($middle, ' ') < $maxMiddle;

        foreach (explode("\n", wordwrap($middle, $maxMiddle, "\n", $cut)) as $chunk) {
            if ($first) {
                $output .= sprintf(
                    static::P_PATTERN,
                    $left,
                    $chunk,
                    str_pad($right, $this->tty - ($leftLen + strlen($chunk) + static::P_PAD), ' ', STR_PAD_LEFT)
                );

                $first = false;
            } else {
                $output .= sprintf(static::P_PATTERN, str_pad('', $leftLen, ' '), $chunk, '');
            }
        }

        return substr($output, 0, -1);
    }
}
