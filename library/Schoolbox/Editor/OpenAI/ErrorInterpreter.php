<?php

namespace Schoolbox\Editor\OpenAI;

/**
 * Translate nerd words into something vaguely understandable
 * for the average human.
 */
class ErrorInterpreter
{
    /**
     * A stock-standard statement about something generic going wrong that isn't easily
     * repaired by users or superusers; we'd have to dig to help them
     */
    private const DEFAULT_PHRASE = 'There was an AI services error; please try again. If the problem persists, consult your system administrator.';


    /**
     * Translate these nerdy words via a regex, I guess, for versatility
     */
    private const MAPPING = [
        '/does not support Structured Outputs/' => self::DEFAULT_PHRASE,
        '/Invalid schema for/' => self::DEFAULT_PHRASE,
        '/Incorrect API key/' => 'AI services are not configured correctly; consult your system administrator.'
    ];


    /**
     * Return the first translated error message that seems to match.
     *
     * @param string $input
     * @return string
     */
    public function interpret(string $input): string
    {
        foreach (self::MAPPING as $pattern => $message) {
            if (preg_match($pattern, $input)) {
                return $message;
            }
        }

        /* A novel error
         */
        return $input;
    }
}
