<?php

namespace Schoolbox\Editor\OpenAI;

/**
 * Translate nerd words into something vaguely understandable
 * for the average human.
 */
class ErrorInterpreter
{
    /**
     * Translate these nerdy words via a regex, I guess, for versatility
     */
    private const MAPPING = [
        '/Invalid schema for/' => 'There was an AI services error; please try again. If the problem persists, consult your system administrator.',
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
