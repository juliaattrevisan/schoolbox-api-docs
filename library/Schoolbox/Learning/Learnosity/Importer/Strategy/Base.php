<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

class Base
{
    /**
     * Constructor.
     *
     * $domain is used to replace relative urls with absolute urls
     * Anything that allowed a cke will potentially have relative urls
     *
     * @param string $domain
     */
    public function __construct(protected string $domain)
    {
    }


    /**
     * Prepares an arbitrary question richtext $question contents
     *    * negates attacks
     *    * replaces relative urls with absolute urls
     *
     * @param string $question
     * @return string
     */
    protected function produceQuestionText(string $question): string
    {
        return \Scrub::htmlAbsoluteUri($question, $this->domain);
    }


    /**
     * Decompiles $answer's serialized value
     *
     * Sometimes there's broken data which means it can't succeed
     *
     * @param array $answer
     * @return mixed
     * @throws \RuntimeException
     */
    protected function decompileSerializedAnswer(array $answer): mixed
    {
        // Skip if the compiled answer is not a string at all
        $compiledAnswer = $answer['answer'] ?? null;
        if (!is_string($compiledAnswer)) {
            throw new \RuntimeException(sprintf('answer is not a string (type: %s)', gettype($compiledAnswer)));
        }

        /* Note that in old data, unserialize could return a variety of possibilities
         * It's possible that $answer['answer'] might be:
         *
         *     - actually null
         *     - a string 'N;' due to json stringify breaking somehow
         *     - empty string ''
         *     - something else entirely
         */
        try {
            $decompiledAnswer = unserialize($compiledAnswer, [
                'allowed_classes' => true
            ]);
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (\ErrorException $e) {
            // This is not redundant for our purposes:
            // Schoolbox's CLI context catches notices raised by invalid data
            // and raises an `\ErrorException`.
            throw new \RuntimeException(sprintf('answer could not deserialize (%s)', $e->getMessage()));
        }

        // Some busted data may deserialize as null or false, or another
        // non-iterable value: skip those questions as well
        if (!isset($decompiledAnswer)) {
            throw new \RuntimeException('answer deserialized to null.');
        }

        if (false === $decompiledAnswer) {
            throw new \RuntimeException('answer deserialized to false.');
        }

        if (!is_iterable($decompiledAnswer)) {
            throw new \RuntimeException('answer deserialized to a non-iterable value.');
        }

        return $decompiledAnswer;
    }


    /**
     * Returns $answer's value as provided; it doesn't need deserializing,
     * just some trimming
     *
     * @param array $answer
     * @return mixed
     */
    protected function decompileNonserializedAnswer(array $answer): mixed
    {
        return trim((string)$answer['answer']);
    }
}
