<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker;

use Alaress\Exception\IO\IOException;
use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Psr\Log\LoggerInterface;

class PipeWriter
{

    /** @var ProtocolInterface */
    protected $proto;

    /** @var LoggerInterface */
    protected $log;

    /** @var resource */
    protected $downpipe;

    /**
     * @param DispatchStrategyInterface $strat
     * @param LoggerInterface $log
     * @param resource $downpipe
     */
    public function __construct($strat, $log, $downpipe)
    {
        $this->proto = $strat->getProtocol();
        $this->log = $log;
        $this->downpipe = $downpipe;
    }


    /**
     * Write a string to the worker process, ensuring to be sensitive of pipe buffering
     *
     * @param string $string
     * @param bool $err
     * @throws IOException
     * @return self
     */
    public function write($string, $err = false)
    {
        $chunk = $this->proto->chunkLength();
        $strlen = strlen($string);
        $end = $err ? $this->proto->reject() : $this->proto->end();

        for ($written = 0; $written < $strlen; $written += $fwrite) {
            $shard = str_pad(substr($string, $written, $chunk), $chunk, $end);

            // so...guarantee that fread() will actually PUT all the data in the pipe... but only for exactly as long as needed
            stream_set_blocking($this->downpipe, 1);
            $fwrite = fwrite($this->downpipe, $shard);
            stream_set_blocking($this->downpipe, 0);

            /*
             * The size of $shard will never be zero, so if fwrite() returns 0, this means the pipe has been broken.
             * In socket programming, fwrite() does not return false when it fails to write (go figure)
             * @see http://php.net/manual/en/function.fwrite.php#96951
             */
            if ($fwrite === false || $fwrite === 0) {
                throw new IOException("Broken pipe, could not write to target");
            }
        }
        fflush($this->downpipe);

        if ($overhang = $written % $chunk) {
            $this->log->error("$overhang bytes unflushed");
        }

        $this->log->info(sprintf("Sent %d bytes to target (%d x %d-byte chunks)", $written, $written / $chunk, $chunk));

        if ($written < $strlen) {
            throw new IOException("Pipe communication terminated prematurely after $written bytes, " . ($strlen - $written) . " unsent");
        }

        return $this;
    }
}
