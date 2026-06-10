<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent;

/**
 * Helper class for extracting relevant data out of a response.
 */
class ResponseParser
{
    /**
     * Extract content text out of the response.
     *
     * The content text will be somewhere in `$responseData['output']`:
     * * each of these is a `ResponseOutputItem` of some sort:
     *   we're interested in `ResponseOutputMessage` outputs only
     *   (`type` = 'message')
     * * from `ResponseOutputMessage` outputs: we just want their content, which
     *   should be `ResponseOutputText` (`type` = 'output_text')
     * * from the `ResponseOutputText`, we want the `text` value
     *
     * @see https://developers.openai.com/api/reference/resources/responses/methods/retrieve
     * @see https://developers.openai.com/api/reference/resources/responses#(resource)%20responses%20%3E%20(model)%20response_output_item%20%3E%20(schema)
     *
     * @param array $responseData
     * @return mixed
     */
    public function extractContentText(array $responseData): mixed
    {
        foreach ($responseData['output'] ?? [] as $output) {
            if (($output['type'] ?? '') !== 'message') {
                continue;
            }
            if (isset($output['content'])) {
                foreach ($output['content'] as $content) {
                    if (($content['type'] ?? '') !== 'output_text') {
                        continue;
                    }
                    if (isset($content['text'])) {
                        return $content['text'];
                    }
                }
            }
        }
        return null;
    }
}
