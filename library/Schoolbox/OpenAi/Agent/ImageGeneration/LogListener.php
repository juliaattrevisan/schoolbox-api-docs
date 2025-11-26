<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\ImageGeneration;

use Schoolbox\Editor\OpenAI\Log\Model;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\Listener\IListener;
use Schoolbox\OpenAi\LogHandler\ResponseMessage;
use UnexpectedValueException;

/**
 * Log listener for image generation requests.
 *
 * If a message is a finished response, then extract the output text out of it
 * and add it to the log.
 */
class LogListener implements IListener
{
    /**
     * @inheritdoc
     */
    public function updateLog(Model $log, Message $outputMessage, Message $rawMessage): Model
    {
        if (in_array($outputMessage->eventType, ResponseMessage::EVENT_TYPES_FINISHED)) {
            $outputTextParts = [];
            try {
                $decodedMessage = $outputMessage->getDataAsArray();
            } catch (UnexpectedValueException) {
                // Not valid data: nothing can be done.
                // Could always inspect the full stream data to figure it out?
            }
            foreach (($decodedMessage['response']['output']) ?? [] as $output) {
                foreach (($output['content'] ?? []) as $content) {
                    if ($content['type'] === 'output_text' && isset($content['text'])) {
                        $outputTextParts[] = $content['text'];
                    }
                }
            }
            $outputText = !empty($outputTextParts)
                ? implode("\n\n", $outputTextParts)
                : null;
            $log->setOutputText($outputText);
        }
        return $log;
    }
}
