<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\Filesystem\Test;

use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Attachment\Filesystem\UsermediaController;

class UsermediaControllerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestValidData')]
    public function testValid(?string $mimeFilter)
    {
        new UsermediaController(false, $mimeFilter);
        self::assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvalidData')]
    public function testInvalid(?string $mimeFilter)
    {
        $this->expectException(OutOfRangeException::class);
        new UsermediaController(false, $mimeFilter);
    }

    public static function getTestValidData(): iterable
    {
        return [
            'null'  => [null],
            'star' => ['*/*'],
            'the lot' => ['image/*,audio/*,video/*'],
        ];
    }

    public static function getTestInvalidData(): iterable
    {
        return [
            'image' => ['image/*'],
            'jpg' => ['image/jpg'],
            'the lot spaces (notCSV)' => ['image/*, audio/*, video/*'],
            'doc' => ['document/*'],
            'exe' => ['application/exe'],
            'dummy' => ['test/fail'],

        ];
    }
}
