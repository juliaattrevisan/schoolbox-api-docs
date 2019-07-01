<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IClient;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\Stream;
use Stomp\Protocol;
use Stomp\Transport\Frame as BaseFrame;
use Stomp\Transport\Parser;

/**
 * Class Client
 */
class Client implements IClient
{
    /**
     * The stream connection to the Stomp server
     *
     * @var Stream
     */
    private $stream;


    /**
     * @var string
     */
    private $login;


    /**
     * @var string
     */
    private $passcode;


    /**
     * @var string|null
     */
    private $host;


    /**
     * @var Protocol\Protocol
     */
    private $protocol;


    /**
     * @var Parser
     */
    private $parser;


    /**
     * @var callable[]
     */
    private $callbacks;


    public function __construct(
        Stream $stream,
        string $login,
        string $passcode,
        ?string $host = null
    ) {
        // Stream events
        $this->stream   = $stream;
        $this->login    = $login;
        $this->passcode = $passcode;
        $this->host     = $host;
        $this->setupEvents();
    }


    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        // Generate a subscription ID
        $subscriptionId = uniqid('', true);
        $subscribeFrame = $this->protocol->getSubscribeFrame($destination, $subscriptionId, $ackMode);
        $this->callbacks[$subscriptionId] = $callback;
        $this->stream->write($subscribeFrame);
        $this->stream->on('message', function (BaseFrame $frame) use ($subscriptionId) {
            if ($frame['subscription'] === $subscriptionId) {
                $this->callbacks[$subscriptionId](
                    new Frame($frame),
                    new AckResolver($this->stream, $this->protocol, $frame)
                );
            }
        });
        return $subscriptionId;
    }


    public function connect(): PromiseInterface
    {
        $deferred     = new Deferred();
        $connectFrame = $this->protocol->getConnectFrame($this->login, $this->passcode, [], $this->host);
        $this->stream->write($connectFrame);

        $connectListener = function (BaseFrame $frame) use ($deferred) {
            $deferred->resolve($this);
        };
        $connectErrorListener = static function (BaseFrame $frame) use ($deferred) {
            $deferred->reject(new ConnectionException($frame->getBody()));
        };
        $this->stream->once('connect', function (BaseFrame $frame) use ($connectListener, $connectErrorListener) {
            $this->stream->removeListener('error', $connectErrorListener);
            $connectListener($frame);
        });
        $this->stream->once('error', static function (BaseFrame $frame) use ($connectListener, $connectErrorListener) {
            $this->stream->removeListener('connect', $connectListener);
            $connectErrorListener($frame);
        });

        return $deferred->promise();
    }


    private function setupEvents(): void
    {
        $this->protocol = new Protocol\Protocol(null, Protocol\Version::VERSION_1_1);
        $this->parser   = new Parser();
        $this->stream->on('data', function ($data) {
            $this->parser->addData($data);
            while ($frame = $this->parser->nextFrame()) {
                if ($frame instanceof BaseFrame) {
                    // What kind of frame?
                    switch ($frame->getCommand()) {
                        case 'CONNECTED':
                            $this->stream->emit('connect', [$frame]);
                            break;
                        case 'ERROR':
                            $this->stream->emit('error', [$frame]);
                            break;
                        case 'MESSAGE':
                            $this->stream->emit('message', [$frame]);
                            break;
                    }
                }
            };
        });
    }
}
