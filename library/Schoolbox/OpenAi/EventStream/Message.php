<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\EventStream;

/**
 * Class representation of a message from an event stream.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events#event_stream_format
 */
class Message
{
    /**
     * Constructor
     *
     * @param string      $data      The message's data: may be JSON or text depending on the sender.
     * @param string|null $eventType The event type of the message, if any.
     * @param string|null $id        The message's ID, if any: if set, the client should record this ID and send it when
     *                               retrying the request, in the `Last-Event-ID` header.
     * @param int|null    $retry     If set: the client should wait this many milliseconds before retrying the request.
     */
    public function __construct(
        public string $data,
        public ?string $eventType = null,
        public ?string $id = null,
        public ?int $retry = null,
    ) {
    }

    /**
     * Build a message from a string, as extracted from an event stream.
     *
     * @param string $message
     * @return self
     */
    public static function fromString(string $message): self
    {
        $data      = '';
        $id        = null;
        $eventType = null;
        $retry     = null;
        $parts     = explode("\n", trim($message));
        foreach ($parts as $part) {
            if (!str_contains($part, ':')) {
                $field = $part;
                $value = '';
            } else {
                [$field, $value] = explode(':', $part, 2);
                if ($field === '') {
                    continue;
                }
            }

            // Trim single leading space from $value as per
            // https://html.spec.whatwg.org/multipage/server-sent-events.html#event-stream-interpretation
            $valueLeadingChar = substr($value, 0, 1);
            if ($valueLeadingChar === ' ') {
                $value = substr($value, 1);
            }

            if ($field === 'data') {
                $data .= $value;
            }
            if ($field === 'event') {
                $eventType = $value;
            }
            if ($field === 'id') {
                $id = $value;
            }
            if ($field === 'retry') {
                $retry = intval($value);
            }
        }
        return new self($data, $eventType, $id, $retry);
    }

    /**
     * Convert a message to a string, ready for outputting in an event stream.
     *
     * @return string
     */
    public function __toString(): string
    {
        $fields = [];
        if (isset($this->eventType)) {
            $fields[] = sprintf('event: %s', $this->eventType);
        }
        if (isset($this->id)) {
            $fields[] = sprintf('id: %s', $this->id);
        }
        if (isset($this->retry)) {
            $fields[] = sprintf('retry: %d', $this->retry);
        }
        $fields[] = sprintf('data: %s', $this->data);
        return implode("\n", $fields) . "\n\n";
    }
}
