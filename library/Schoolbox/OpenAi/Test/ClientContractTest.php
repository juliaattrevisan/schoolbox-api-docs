<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Test;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoolbox\OpenAi\Client;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\IClient;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;

/**
 * Contract tests for AI client logging APIs.
 */
class ClientContractTest extends TestCase
{
    /**
     * createLog requires the fields needed to create a valid ai_session and ai_session_log row.
     */
    public function testCreateLogRequiresSessionFeatureAndId(): void
    {
        foreach ([IClient::class, Client::class] as $className) {
            $parameters = $this->getMethodParameters($className, 'createLog');

            $this->assertRequiredParameterType($parameters['feature'], Feature::class);
            $this->assertRequiredStringParameter($parameters['aiSessionId']);
        }
    }

    /**
     * findOrCreate requires the feature needed by the ai_session schema.
     */
    public function testFindOrCreateRequiresFeature(): void
    {
        $parameters = $this->getMethodParameters(SessionMapper::class, 'findOrCreate');

        $this->assertRequiredParameterType($parameters['feature'], Feature::class);
    }

    /**
     * @param class-string $className
     * @return array<string, \ReflectionParameter>
     */
    private function getMethodParameters(string $className, string $methodName): array
    {
        $parameters = [];
        foreach ((new ReflectionMethod($className, $methodName))->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        return $parameters;
    }

    private function assertRequiredStringParameter(\ReflectionParameter $parameter): void
    {
        $this->assertRequiredParameterType($parameter, 'string');
    }

    private function assertRequiredParameterType(\ReflectionParameter $parameter, string $type): void
    {
        $this->assertFalse($parameter->isOptional());
        $this->assertFalse($parameter->allowsNull());
        $this->assertSame($type, (string)$parameter->getType());
    }
}
