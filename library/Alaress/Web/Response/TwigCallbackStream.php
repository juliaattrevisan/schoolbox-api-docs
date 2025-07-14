<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response;

use Closure;
use GuzzleHttp\Psr7\PumpStream;
use Psr\Http\Message\StreamInterface;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * `StreamInterface` implementation for efficiently outputting large data sets
 * which are then passed through Twig.
 *
 * For example, text logs to be rendered as HTML may have each line parsed into
 * data, and then rendered in Twig: attempting to render the entire data set in
 * one go has proven to be memory-inefficient (see SA-413), therefore each line
 * must be rendered individually, and buffered after rendering.
 */
class TwigCallbackStream implements StreamInterface
{
    /**
     * Has the header already been rendered?
     *
     * It must only be rendered once, before the first call of
     * `$this->dataCallback`.
     */
    private bool $headerRendered = false;

    /**
     * Has the footer already been rendered?
     *
     * It must only be rendered once, after the last call of
     * `$this->dataCallback`; after that, no more data will be output by this
     * stream.
     */
    private bool $footerRendered = false;

    /**
     * The number of times `$this->dataCallback` has been called: however, if
     * that callable returns `false` in its `increment_counter` key, this will
     * not be incremented.
     */
    private int $counter = 0;

    /**
     * Internal stream: all `StreamInterface` method calls are passed through
     * to this stream.
     */
    private PumpStream $internalStream;

    /**
     * Constructor
     *
     * @param Environment $twig                  Twig environment, for rendering templates.
     * @param string      $template              The template to render for each return of `$dataCallback`.
     *                                           Returns either: an array with data to pass into `$twig->render`,
     *                                           with additional keys `increment_counter` (if set and `false`, do not
     *                                           increment `$this->counter`) and `render_item` (if set and `false`, do
     *                                           not render at all); or `false` if the callback will return no more data
     *                                           (i.e. EOF)
     * @param Closure     $dataCallback          The callback for generating data to pass into `$twig->render`.
     *                                           `int $counter` added as the current result counter.
     * @param array       $dataCallbackArguments A list of arguments to pass into `$dataCallback`: will also have
     * @param string|null $headerTemplate        The template to render before the first `$dataCallback`.
     * @param string|null $footerTemplate        The template to render after the last `$dataCallback`.
     * @param array       $headerFooterData      Data to pass to `$twig->render` when rendering either `$headerTemplate`
     *                                           or `$footerTemplate`.
     */
    public function __construct(
        private Environment $twig,
        private string $template,
        private Closure $dataCallback,
        private array $dataCallbackArguments,
        private ?string $headerTemplate = null,
        private ?string $footerTemplate = null,
        private array $headerFooterData = [],
    ) {
        $this->internalStream = new PumpStream(
            Closure::fromCallable([$this, 'internalStreamCallback'])
        );
    }

    /**
     * @return false|string
     * @throws TwigError if something goes wrong rendering a template
     */
    private function internalStreamCallback(): string|false
    {
        // Render Heading
        if (!$this->headerRendered && isset($this->headerTemplate)) {
            $this->headerRendered = true;
            return $this->twig->render($this->headerTemplate, $this->headerFooterData);
        }

        // Render content
        $dataCallbackArguments            = $this->dataCallbackArguments;
        $dataCallbackArguments['counter'] = $this->counter;
        while ($data = call_user_func_array($this->dataCallback, $dataCallbackArguments)) {
            // Should we increment counter?
            if (!isset($data['increment_counter']) || !$data['increment_counter']) {
                $this->counter++;
            }

            // Should we render this item?
            if (!isset($data['render_item']) || $data['render_item']) {
                return $this->twig->render($this->template, $data);
            }
        }

        // Render Footer only once
        if (!$this->footerRendered && isset($this->footerTemplate)) {
            $this->footerRendered = true;
            return $this->twig->render($this->footerTemplate, $this->headerFooterData);
        }

        // EOF
        return false;
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return $this->internalStream->__toString();
    }

    /**
     * @inheritdoc
     */
    public function close(): void
    {
        $this->internalStream->close();
    }

    /**
     * @inheritdoc
     */
    public function detach()
    {
        return $this->internalStream->detach();
    }

    /**
     * @inheritdoc
     */
    public function getSize(): ?int
    {
        return $this->internalStream->getSize();
    }

    /**
     * @inheritdoc
     */
    public function tell(): int
    {
        return $this->internalStream->tell();
    }

    /**
     * @inheritdoc
     */
    public function eof(): bool
    {
        return $this->internalStream->eof();
    }

    /**
     * @inheritdoc
     */
    public function isSeekable(): bool
    {
        return $this->internalStream->isSeekable();
    }

    /**
     * @inheritdoc
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->internalStream->seek($offset, $whence);
    }

    /**
     * @inheritdoc
     */
    public function rewind(): void
    {
        $this->internalStream->rewind();
    }

    /**
     * @inheritdoc
     */
    public function isWritable(): bool
    {
        return $this->internalStream->isWritable();
    }

    /**
     * @inheritdoc
     */
    public function write(string $string): int
    {
        return $this->internalStream->write($string);
    }

    /**
     * @inheritdoc
     */
    public function isReadable(): bool
    {
        return $this->internalStream->isReadable();
    }

    /**
     * @inheritdoc
     */
    public function read(int $length): string
    {
        return $this->internalStream->read($length);
    }

    /**
     * @inheritdoc
     */
    public function getContents(): string
    {
        return $this->internalStream->getContents();
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(?string $key = null)
    {
        return $this->internalStream->getMetadata($key);
    }
}
