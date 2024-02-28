<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Feedback;

use Alaress\Lumberjack\Logger;
use Schoolbox\Learning\Quiz\Attempt\Model;

class SuspiciousLogger
{
    /**
     * Sometimes students' marks will have computed incorrectly as being zero,
     * but we don't know where/why it happens.
     *
     * @param string $attemptLearnosityReference
     * @param array $sanitisedScore
     * @param array $postData
     * @return void
     */
    public function logSanitisedScore(string $attemptLearnosityReference, array $sanitisedScore, array $postData): void
    {
        $suspicious = false;

        /* Students getting 0 is considered suspicious; can't tell
         * if they really got zero, or it was computed to be zero
         */
        if (0.00 === (float)$sanitisedScore['score']) {
            $suspicious = true;
        }

        if ($suspicious) {
            $logger = new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity_suspicious_scores');

            ob_start();
            var_dump($postData);
            $obPostData = ob_get_contents();
            ob_end_clean();

            $logger->debug(sprintf(
                'Attempt %s received (float)%s via data payload %s',
                $attemptLearnosityReference,
                $sanitisedScore['score'],
                $obPostData
            ));
        }
    }


    /**
     * Sometimes students' marks will have computed incorrectly as being zero,
     * but we don't know where/why it happens.
     *
     * @param string $attemptLearnosityReference
     * @param float|null $mark
     * @param float|null $maxMark
     * @param float|null $normalisedMark
     * @param string|null $humanReadableMark
     * @return void
     */
    public function logZeroScore(
        string $attemptLearnosityReference,
        ?float $mark,
        ?float $maxMark,
        ?float $normalisedMark,
        ?string $humanReadableMark
    ): void {
        $suspicious = false;

        /* Students getting 0 is considered suspicious; can't tell
         * if they really got zero, or it was computed to be zero
         */
        if (0.00 === (float)$mark) {
            $suspicious = true;
        }

        if (0.00 === (float)$maxMark) {
            $suspicious = true;
        }

        if (0.00 === (float)$normalisedMark) {
            $suspicious = true;
        }

        /* If the first chars of a human readable mark is '0 / ' then its suspicious.
         * There are equivalents for other mark schemes like Letter Grade, Percentage etc.
         * so maybe add those as we encounter them
         */
        if (str_starts_with((string)$humanReadableMark, '0 /')) {
            $suspicious = true;
        }

        if ($suspicious) {
            $logger = new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity_suspicious_scores');

            $logger->debug(sprintf(
                'Attempt %s received (float)%s/(float)%s (norm: %s; hr: %s)',
                $attemptLearnosityReference,
                $mark,
                $maxMark,
                $normalisedMark,
                $humanReadableMark
            ));
        }
    }


    /**
     * Log an arbitrary array $data with the given context prefix so you know what it's about,
     * storing the json representation of $data in a log message.
     *
     * @param array $data
     * @param Model $attempt
     * @param string $context
     * @return void
     * @throws \JsonException
     */
    public function logPayload(string $context, array $data, \Schoolbox\Learning\Quiz\Attempt\Model $attempt): void
    {
        $logger = new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity_init_payload');

        /* Arguably you could use output buffering and var dump it as-is
         * but this should be enough for learnosity's diagnosing
         */
        $obPayload = json_encode($data, JSON_THROW_ON_ERROR);

        $logger->debug(sprintf(
            '%s: attempt %s (learnosity reference %s) had data: %s',
            $context,
            $attempt->getId(),
            $attempt->getLearnosityReference(),
            $obPayload
        ));
    }
}
