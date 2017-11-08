<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;


use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\Exception\IO\IOException;
use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Worker\PipeWriter;
use Alaress\WorkerCrew\Worker\WorkerInterface;
use ErrorException;
use Exception;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class InputStreamListener extends AbstractIOStreamListener
{

    /** @var LoopInterface */
    protected $loop;

    /** @var ProtocolInterface */
    protected $proto;

    /** @var PipeWriter */
    protected $writer;

    /** @var WorkerInterface */
    protected $worker;

    /** @var bool */
    protected $buffering = false;

    /** @var string */
    protected $buffer = '';


    /**
     * @param LoggerInterface   $log
     * @param LoopInterface     $loop
     * @param ProtocolInterface $protocol
     * @param PipeWriter        $writer
     * @param WorkerInterface   $worker
     */
    public function __construct(
        LoggerInterface $log,
        LoopInterface $loop,
        ProtocolInterface $protocol,
        PipeWriter $writer,
        WorkerInterface $worker
    ) {
        parent::__construct($log);
        $this->loop   = $loop;
        $this->proto  = $protocol;
        $this->writer = $writer;
        $this->worker = $worker;
    }


    /**
     * Sent when the stream has been closed for any reason
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamClosed(StreamEvent $e)
    {
        // input stream closed, no more data can come in, let's just bug out
        $this->loop->stop();
    }


    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e)
    {
        $data = $e->getData();

        ob_start();

        try {
            // cease and desist received
            if ($this->proto->isCancelled($data)) {
                $this->streamClosed($e);
                ob_end_clean();
                return;
            }

            if (!$this->buffering) {
                $this->resetBuffer(true);
            }

            $this->buffer .= $data;

            // if EOT not yet received
            if (!$this->proto->isEnded($data)) {
                ob_end_clean();
                return;
            }

            $this->buffering = false;
            $this->log->debug('Data transfer complete (got ' . strlen($this->buffer) . ' bytes), starting work');
            $this->writer->write($this->worker->parse($this->buffer));
            $this->log->debug("Task Complete");

            if ($buffer = ob_get_contents()) {
                $this->log->debug("Captured output: $buffer");
            }

        } catch(IOException $e) {
            // this means the pipe or connection to an external service has been broken
            // no point logging, no point carrying on. everything is meaningless.
            $this->loop->stop();
        } catch(ErrorException $e) {
            $msg = sprintf("Error encountered: '%s' in %s:%s", $e->getMessage(), $e->getFile(), $e->getLine());
            $this->log->warning($msg);
            $this->writer->write($this->proto->cancel());
        } catch(PDOException $e) {
            $this->log->emergency("PDOException: " . $e->getMessage(), array($e));
            $this->writer->write($this->proto->reject(), true);
            $this->loop->stop();
        } catch(Exception $e) {
            // all exceptions must be caught, otherwise the react loop explodes and this worker dies
            $msg = sprintf("Worker exception: %s - '%s'", get_class($e), $e->getMessage());
            $this->log->critical($msg, array($e));
            $this->writer->write($this->proto->cancel());
        }

        $this->resetBuffer();
        ob_end_clean();

    }


    /**
     * @param bool $isBuffering
     * @return void
     */
    protected function resetBuffer($isBuffering = false)
    {
        unset($this->buffer);
        $this->buffer    = '';
        $this->buffering = $isBuffering;
    }

}
