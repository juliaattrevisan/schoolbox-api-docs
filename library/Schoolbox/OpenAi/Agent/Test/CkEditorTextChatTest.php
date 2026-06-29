<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\Test;

use Html2Text\Html2Text;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\OpenAi\Agent\CkEditorTextChat;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\IClient;
use Schoolbox\OpenAi\Session\Log\Model as LogModel;
use Schoolbox\User;

/**
 * Unit tests for the CKEditor text chat OpenAI agent.
 */
class CkEditorTextChatTest extends TestCase
{
    /**
     * Direct OpenAI CKEditor chat requests are stored by OpenAI so they appear in platform logs.
     */
    public function testOpenAiChatCompletionPayloadIsStoredForPlatformLogs(): void
    {
        $payload = null;
        $user = $this->createMock(User::class);
        $client = $this->createMock(IClient::class);

        $client->expects($this->once())
            ->method('createLog')
            ->with(
                $this->callback(function (array $createdPayload) use (&$payload): bool {
                    $payload = $createdPayload;
                    return true;
                }),
                'Please improve this paragraph.',
                $user,
                Feature::CHAT,
                'ckeditor-session',
                null,
            )
            ->willReturn(new LogModel());

        $client->expects($this->once())
            ->method('createChatCompletionStream')
            ->willReturn([]);

        $agent = new CkEditorTextChat(
            $client,
            $this->buildConfig(Helper::ENDPOINT_TYPE_OPENAI),
            new Html2Text(),
        );

        $agent->requestStream(
            $user,
            [
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => 'Please improve this paragraph.',
                    ],
                ],
            ],
            'ckeditor-session',
        );

        $this->assertIsArray($payload);
        $this->assertTrue($payload['store'] ?? false);
    }

    /**
     * Azure chat completion requests do not receive OpenAI-only storage parameters.
     */
    public function testAzureChatCompletionPayloadDoesNotIncludeOpenAiStoreParameter(): void
    {
        $payload = null;
        $user = $this->createMock(User::class);
        $client = $this->createMock(IClient::class);

        $client->expects($this->once())
            ->method('createLog')
            ->with(
                $this->callback(function (array $createdPayload) use (&$payload): bool {
                    $payload = $createdPayload;
                    return true;
                }),
                'Please improve this paragraph.',
                $user,
                Feature::CHAT,
                'ckeditor-session',
                null,
            )
            ->willReturn(new LogModel());

        $client->expects($this->once())
            ->method('createChatCompletionStream')
            ->willReturn([]);

        $agent = new CkEditorTextChat(
            $client,
            $this->buildConfig(Helper::ENDPOINT_TYPE_AZURE),
            new Html2Text(),
        );

        $agent->requestStream(
            $user,
            [
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => 'Please improve this paragraph.',
                    ],
                ],
            ],
            'ckeditor-session',
        );

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('store', $payload);
    }

    /**
     * Build the minimum AI configuration required by the CKEditor text chat agent.
     */
    private function buildConfig(string $provider): array
    {
        return [
            Config::FIELD_PROVIDER => ['value' => $provider],
            Helper::ENDPOINT_TYPE_OPENAI => [Config::FIELD_MODEL => ['value' => 'gpt-5.4-nano']],
            Helper::ENDPOINT_TYPE_AZURE => [Config::FIELD_DEPLOYMENT_ID => ['value' => 'schoolbox-gpt']],
            Helper::ENDPOINT_TYPE_GENERAL => [Config::FIELD_SYSTEM_PROMPT => ['value' => '']],
            Config::SECTION_GENERAL => [],
        ];
    }
}
