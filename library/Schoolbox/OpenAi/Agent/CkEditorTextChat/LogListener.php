<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\CkEditorTextChat;

use Html2Text\Html2Text;
use Schoolbox\OpenAi\Session\Log;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\IListener;
use UnexpectedValueException;

/**
 * Log listener for CKEditor text chat requests.
 *
 * If a received message is a finished response, then extract the output text
 * out of it and add it to the log.
 */
class LogListener implements IListener
{
    /**
     * Constructor
     *
     * @param Html2Text $html2Text HTML to text converter: required for converting the HTML response to plain text, for
     *                             storing in the log.
     */
    public function __construct(private Html2Text $html2Text)
    {
    }

    /**
     * @inheritdoc
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model {
        // Chat completions only returns chunked output, and never returns a
        // summary.
        // Need to reconstitute output text from the chunks.
        try {
            $decodedMessage = $rawMessage->getDataAsArray();
            foreach (($decodedMessage['choices'] ?? []) as $choice) {
                $content = $choice['delta']['content'] ?? null;
                if (isset($content)) {
                    $log->setOutputText($log->getOutputText() . $content);
                }
            }
        } catch (UnexpectedValueException) {
            // Not valid data: nothing can be done.
            // However the stream may return a `[DONE]` event:
            // that needs to be handled, and used to convert the content into
            // plain text (it's HTML)
        }

        if ($rawMessage->data === '[DONE]') {
            // The stream has finished:
            // make a best effort to convert HTML to plain text.
            // This assumes that the output:
            // * is actually Markdown
            // * contains actual HTML within ```html``` blocks

            // First up, remove the markdown wrappers:
            // assume anything within is HTML
            // If there's no markdown: assume the whole thing is HTML
            $htmlParts = [];
            if (preg_match_all('/```html(?P<html>.*?)```/s', $log->getOutputText(), $matches)) {
                $htmlParts = $matches['html'];
            } else {
                $htmlParts[] = $log->getOutputText();
            }

            // Now, for each HTML part: get its text only
            $outputTextParts = [];
            foreach ($htmlParts as $html) {
                // Then: use HTML2Text to remove all tags and header content
                $this->html2Text->setHtml($html);
                $outputTextParts[] = $this->html2Text->getText();
            }

            // Finally: reconstitute the output text
            $outputText = !empty($outputTextParts)
                ? implode("\n\n", $outputTextParts)
                : null;
            $log->setOutputText($outputText);
        }
        return $log;
    }

    /**
     * The text chat agent doesn't make non-streaming requests, so just return
     * the log as is.
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        return $log;
    }
}
