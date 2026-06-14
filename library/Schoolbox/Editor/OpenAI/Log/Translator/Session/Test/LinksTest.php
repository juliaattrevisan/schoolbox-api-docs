<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Session\Test;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use PHPUnit\Framework\TestCase;
use Schoolbox\Editor\OpenAI\Log\Translator\Session\Links;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\User;

class LinksTest extends TestCase
{
    public function testSessionLogsRouteIsReturnedWhenAuthorized(): void
    {
        $session = (new Model())->setId('4e5947d2-1f2a-4d2c-9586-08d68e81d8f0');
        $currentUser = new User();

        $authorizer = $this->createMock(Authorizer::class);
        $authorizer
            ->expects(self::once())
            ->method('isPathAuthorizedForUser')
            ->with(
                'openAIUsage.getSessionLogs',
                ['sessionId' => '4e5947d2-1f2a-4d2c-9586-08d68e81d8f0'],
                $currentUser
            )
            ->willReturn(true);

        $routeGenerator = $this->createMock(Generator::class);
        $routeGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(
                'openAIUsage.getSessionLogs',
                ['sessionId' => '4e5947d2-1f2a-4d2c-9586-08d68e81d8f0']
            )
            ->willReturn('/adminv2/aiLog/session/4e5947d2-1f2a-4d2c-9586-08d68e81d8f0/logs');

        $translator = new Links($routeGenerator, $authorizer, $currentUser);

        self::assertSame(
            ['getSessionLogs' => '/adminv2/aiLog/session/4e5947d2-1f2a-4d2c-9586-08d68e81d8f0/logs'],
            $translator($session)
        );
    }


    public function testSessionLogsRouteIsNullWhenUnauthorized(): void
    {
        $session = (new Model())->setId('4e5947d2-1f2a-4d2c-9586-08d68e81d8f0');
        $currentUser = new User();

        $authorizer = $this->createMock(Authorizer::class);
        $authorizer
            ->expects(self::once())
            ->method('isPathAuthorizedForUser')
            ->with(
                'openAIUsage.getSessionLogs',
                ['sessionId' => '4e5947d2-1f2a-4d2c-9586-08d68e81d8f0'],
                $currentUser
            )
            ->willReturn(false);

        $routeGenerator = $this->createMock(Generator::class);
        $routeGenerator
            ->expects(self::never())
            ->method('generate');

        $translator = new Links($routeGenerator, $authorizer, $currentUser);

        self::assertSame(['getSessionLogs' => null], $translator($session));
    }
}
