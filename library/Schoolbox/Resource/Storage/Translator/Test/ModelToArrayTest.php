<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Translator\Test;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\Translator\ModelToArray;

/**
 * @covers \Schoolbox\Resource\Storage\Translator\ModelToArray
 */
class ModelToArrayTest extends TestCase
{
    /**
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public function getTestInvokeData(): iterable
    {
        // Mock filesystem: throws an exception on asking for file's size
        // This will be caught and cause getSize to return null
        /** @var Filesystem|MockObject $filesystem */
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
             ->method('getSize')
             ->willThrowException(new RuntimeException());

        $emptyResponse = [
            'hash'   => null,
            'mime'   => '',
            'type'   => null,
            'size'   => null,
            '_links' => [],
        ];
        yield 'empty fields' => [
            (new Model())
                ->setFilesystem($filesystem),
            $emptyResponse,
        ];

        // test with:
        // audio
        // video
        // image
    }


    /**
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\Resource\Storage\Translator\ModelToArray::__invoke
     * @param Model $input
     * @param array $expected
     */
    public function testInvoke(Model $input, array $expected): void
    {
        self::assertSame($expected, (new ModelToArray())($input));
    }
}
