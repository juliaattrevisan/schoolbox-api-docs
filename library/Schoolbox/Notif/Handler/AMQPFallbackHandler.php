<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Notif\Handler;


use Monolog\Handler\AbstractProcessingHandler;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;

class AMQPFallbackHandler extends AbstractProcessingHandler
{
    /** @var MySQLHandler */
    protected $sqlHandler;


    /**
     * AMQPFallbackHandler constructor.
     *
     * @param int  $level
     * @param bool $bubble
     */
    public function __construct($level, $bubble)
    {
        parent::__construct($level, $bubble);
    }


    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record)
    {
        $event = unserialize($record['message']);
        $topic = $record['channel'];

        if (!$event instanceof Event) {
            // can't handle this. sozlol
            return;
        }

        $qim = QueueItemMapper::getInstance();

        $qi = $qim->createModel();
        $qi->setIdentifier($event);
        $qi->setCommand($topic);
        $qi->setData(null);

        $qim->save($qi);
    }

}
