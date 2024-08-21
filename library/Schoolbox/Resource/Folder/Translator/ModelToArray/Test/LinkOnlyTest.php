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

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly::__invoke)]
class LinkOnlyTest extends TestCase
{
    /**
     *
     * @param Folder   $folder
     * @param LinkOnly $translator
     * @param array    $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(Folder $folder, LinkOnly $translator, array $expected): void
    {
        self::assertSame($expected, call_user_func($translator, $folder));
    }

    /**
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        yield 'folder without codes' => [
            (new Folder())
                ->setId(1)
                ->setName('Test 1'),
            new LinkOnly(0),
            [
                'id'     => 1,
                'name'   => 'Test 1',
                'title'  => 'Test 1',
                'codes'  => [],
                'parent' => null,
                '_links' => [
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
            new LinkOnly(0),
            [
                'id'     => 2,
                'name'   => 'Test 2',
                'title'  => 'Test 2 (A,B)',
                'codes'  => ['A', 'B'],
                'parent' => null,
                '_links' => [
                    'view' => '/homepage/2',
                ],
            ]
        ];

        // Test ancestry
        $folderWithAncestry = (new Folder())
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
            )
            ->setParent(
                (new Folder())
                    ->setId(3)
                    ->setName('Test 3')
                    ->setCodes(
                        new GenericCollection(
                            FolderCode::class,
                            [
                                (new FolderCode())->setCode('A3'),
                                (new FolderCode())->setCode('B3'),
                            ]
                        )
                    )->setParent(
                        (new Folder())
                            ->setId(5)
                            ->setName('Test 5')
                            ->setCodes(
                                new GenericCollection(
                                    FolderCode::class,
                                    [
                                        (new FolderCode())->setCode('A5'),
                                    ]
                                )
                            )
                    )
            );

        yield 'folder with ancestry: ancestor count 0' => [
            $folderWithAncestry,
            new LinkOnly(0),
            [
                'id'     => 2,
                'name'   => 'Test 2',
                'title'  => 'Test 2 (A,B)',
                'codes'  => ['A', 'B'],
                'parent' => ['id' => 3],
                '_links' => [
                    'view' => '/homepage/2',
                ],
            ],
        ];

        yield 'folder with ancestry: ancestor count 1' => [
            $folderWithAncestry,
            new LinkOnly(1),
            [
                'id'     => 2,
                'name'   => 'Test 2',
                'title'  => 'Test 2 (A,B)',
                'codes'  => ['A', 'B'],
                'parent' => [
                    'id'     => 3,
                    'name'   => 'Test 3',
                    'title'  => 'Test 3 (A3,B3)',
                    'codes'  => ['A3', 'B3'],
                    'parent' => ['id' => 5],
                    '_links' => [
                        'view' => '/homepage/3',
                    ],
                ],
                '_links' => [
                    'view' => '/homepage/2',
                ],
            ],
        ];

        $fullTranslation = [
            'id'     => 2,
            'name'   => 'Test 2',
            'title'  => 'Test 2 (A,B)',
            'codes'  => ['A', 'B'],
            'parent' => [
                'id'     => 3,
                'name'   => 'Test 3',
                'title'  => 'Test 3 (A3,B3)',
                'codes'  => ['A3', 'B3'],
                'parent' => [
                    'id'     => 5,
                    'name'   => 'Test 5',
                    'title'  => 'Test 5 (A5)',
                    'codes'  => ['A5'],
                    'parent' => null,
                    '_links' => [
                        'view' => '/homepage/5',
                    ],
                ],
                '_links' => [
                    'view' => '/homepage/3',
                ],
            ],
            '_links' => [
                'view' => '/homepage/2',
            ],
        ];
        yield 'folder with ancestry: ancestor count 2' => [
            $folderWithAncestry,
            new LinkOnly(2),
            $fullTranslation,
        ];
        yield 'folder with ancestry: ancestor count 999' => [
            $folderWithAncestry,
            new LinkOnly(999),
            $fullTranslation,
        ];
    }
}
