<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\IClient;
use Schoolbox\User;

/**
 * Guardrail check to flag disallowed content categories using OpenAI's Moderations API.
 * https://openai.github.io/openai-guardrails-js/ref/checks/moderation/
 */
class Moderation implements ICheck
{
    /**
     * The default moderation categories to use if an agent does not specify a separate configuration
     * https://platform.openai.com/docs/guides/moderation#content-classifications
     */
    private const array DEFAULT_CATEGORIES = [
        "sexual",
        "sexual/minors",
        "hate",
        "hate/threatening",
        "harassment",
        "harassment/threatening",
        "self-harm",
        "self-harm/intent",
        "self-harm/instructions",
        "violence",
        "violence/graphic",
        "illicit",
        "illicit/violent",
    ];

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'Moderation';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Flags text containing disallowed content categories';
    }

    /**
     * Constructor
     *
     * @param IClient $client            An AI provider client, must point to the Moderations endpoint.
     * @param ?User   $user              The user making the request, if any.
     * @param ?string $feature           An identifier for the feature being requested.
     * @param ?string $sessionId         The session ID against which this request should be logged.
     * @param array<string> $categories The list of categories which should be flagged.
     */
    public function __construct(
        private IClient $client,
        private ?User $user = null,
        private ?string $feature = null,
        private ?string $sessionId = null,
        private array $categories = self::DEFAULT_CATEGORIES,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function check(string $inputText): Outcome
    {
        $categoryResults = $this->callModerationAPI($inputText);

        $flaggedCategories = [];
        foreach ($this->categories as $category) {
            $isFlagged = $categoryResults[$category] ?? false;
            if ($isFlagged) {
                $flaggedCategories[] = $category;
            }
        }

        /* Only trigger if the requested categories are flagged */
        $isTrigger = \count($flaggedCategories) > 0;

        return new Outcome(
            $isTrigger,
            ['flaggedCategories' => $flaggedCategories]
        );
    }

    /**
     * Call the OpenAI moderation API
     *
     * @param string $inputText The text to analyse
     * @return array<string,bool> A list of the categories, and whether they are flagged or not
     * @throws SchoolboxException If the response couldn't be parsed as expected
     */
    private function callModerationAPI(string $inputText): array
    {
        $payload = [
            /**
             * This model is fixed and must be used for the moderations API (unless OpenAI creates another model I guess)
             * Notably this means the model in the config for the feature is NOT used here.
             */
            'model' => 'omni-moderation-latest',
            'input' => $inputText,
        ];

        /* For now, create a separate log here with the relevant info */
        $log = $this->client->createLog(
            $payload,
            $inputText,
            $this->user,
            $this->feature,
            $this->sessionId,
            null, // TODO: should probably have a log format so we can show these on the AI Usage Log
        );

        $response = $this->client->createModerations($payload, $log);
        $data = json_decode($response->getBody()->getContents(), true);

        $results = $data['results'] ?? null;
        if (!isset($results) || !\is_array($results) || 0 === \count($results)) {
            throw new SchoolboxException('No moderation results returned');
        }

        /* The first (and only) item is the moderation outcome */
        return $results[0]['categories'];
    }
}
