<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Translator\ModelToArray\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly;

/**
 * @covers \Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly::__invoke
 */
class LinkOnlyTest extends TestCase
{
    /**
     * @dataProvider getTestInvokeData
     *
     * @param Folder $folder
     * @param array  $expected
     */
    public function testInvoke(Folder $folder, array $expected): void
    {
        self::assertSame($expected, call_user_func(new LinkOnly(), $folder));
    }

    /**
     * @return iterable
     */
    public function getTestInvokeData(): iterable
    {
        yield 'folder without codes' => [
            (new Folder())
                ->setId(1)
                ->setName('Test 1'),
            [
                'id'    => 1,
                'name'  => 'Test 1',
                'title' => 'Test 1',
                'codes' => [],
                '_links'   => [
                    'view' => '/homepage/1',
                ],
            ]
        ];

        yield 'folder with codes' => [
            (new Folder())
                ->setId(2)
                ->setName('Test 2')
                ->setCodes(
                    new GenericCollection(
                        FolderCode::class,
                        [
                            (new FolderCode())->setCode('A'),
                            (new FolderCode())->setCode('B'),
                        ]
                    )
                ),
            [
                'id'    => 2,
                'name'  => 'Test 2',
                'title' => 'Test 2 (A,B)',
                'codes' => ['A', 'B'],
                '_links'   => [
                    'view' => '/homepage/2',
                ],
            ]
        ];
    }
}
