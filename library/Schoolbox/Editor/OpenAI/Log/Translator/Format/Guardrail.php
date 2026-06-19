<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Format;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Session\Log\Model;

/**
 * Converts a stored AI log entry which uses the Guardrail format into a structured array that can be used by the frontend
 * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts
 */
class Guardrail implements IStrategy
{
    /**
     * @inheritDoc
     */
    public static function getLogFormat(): LogFormat
    {
        return LogFormat::GUARDRAIL;
    }

    /**
     * @inheritDoc
     * @return array{type:'request',data:string,createdAt:\Alaress_Date_Format|null}
     */
    public function translateInput(Model $log): array
    {
        return [
            'type' => 'request',
            /** Input text shouldn't be NULL, but technically could be in the DB - handle just in case */
            'data' => $log->getInputText() ?? '',
            'createdAt' => $log->getCreatedAt(),
        ];
    }

    /**
     * @inheritDoc
     * @return array{type:'error',data:string,createdAt:\Alaress_Date_Format|null}|array{type:'guardrail',data:array,createdAt:\Alaress_Date_Format|null}
     */
    public function translateOutput(Model $log): array
    {
        $outputText = $log->getOutputText();
        $data = null;

        if (!empty($outputText)) {
            try {
                $data = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // noop; leave null for below
            }
        }

        if (null === $data) {
            return [
                'type' => 'error',
                'data' => 'Unable to retrieve guardrail result',
                'createdAt' => $log->getCreatedAt(),
            ];
        }

        return [
            'type' => 'guardrail',
            'data' => $data,
            'createdAt' => $log->getCreatedAt(),
        ];
    }
}
