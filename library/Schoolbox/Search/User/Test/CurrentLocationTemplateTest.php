<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\User\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\User;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class CurrentLocationTemplateTest extends TestCase
{
    public function testStaffLinkUsesEventStaffUserId(): void
    {
        $twig = $this->createTwig();
        $twig->addFunction(new TwigFunction('routeLookupIfAuthorized', function (string $route, array $params): string {
            $this->assertSame('searchUser.getProfile', $route);

            return "/search/user/{$params['userId']}";
        }));

        $viewedStudent = (new User())->setId(25);

        $html = $twig->render('currentLocation.twig', [
            'fieldName' => 'Current Location',
            'user' => $viewedStudent,
            'currentEvents' => [
                [
                    'periodCancelled' => false,
                    'location' => 'Room 1',
                    'periodName' => 'Period 1',
                    'title' => 'Mathematics',
                    'staff' => [
                        123 => 'Example Teacher',
                    ],
                    'startTime' => ['timeOnly' => '9:00am'],
                    'endTime' => ['timeOnly' => '10:00am'],
                ],
            ],
        ]);

        self::assertStringContainsString('href="/search/user/123"', $html);
        self::assertStringNotContainsString('href="/search/user/25"', $html);
    }

    public function testStaffNameIsPlainTextWhenRouteIsNotAuthorized(): void
    {
        $twig = $this->createTwig();
        $twig->addFunction(new TwigFunction('routeLookupIfAuthorized', function (): string {
            return '';
        }));

        $html = $twig->render('currentLocation.twig', [
            'fieldName' => 'Current Location',
            'user' => (new User())->setId(25),
            'currentEvents' => [
                [
                    'periodCancelled' => false,
                    'location' => 'Room 1',
                    'periodName' => 'Period 1',
                    'title' => 'Mathematics',
                    'staff' => [
                        123 => 'Example Teacher',
                    ],
                    'startTime' => ['timeOnly' => '9:00am'],
                    'endTime' => ['timeOnly' => '10:00am'],
                ],
            ],
        ]);

        self::assertStringContainsString('Example Teacher', $html);
        self::assertStringNotContainsString('<a href=', $html);
    }

    private function createTwig(): Environment
    {
        return new Environment(
            new FilesystemLoader([
                __DIR__ . '/../templates/field',
            ])
        );
    }
}
