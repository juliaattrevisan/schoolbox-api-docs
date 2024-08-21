<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox\Translator\Test;

use Alaress\Authorizer\Authorizer;
use Alaress\HTMLPurifier\Adapter\MockAdapter;
use Aura\Router\RouterContainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Textbox\Model;
use Schoolbox\Resource\Textbox\Translator\ModelToArray;
use Schoolbox\User;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Textbox\Translator\ModelToArray::__invoke)]
class ModelToArrayTest extends TestCase
{
    /**
     *
     * @param Model $textbox
     * @param bool  $allowUpdate
     * @param bool  $allowDelete
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(Model $textbox, bool $allowUpdate, bool $allowDelete, array $expected): void
    {
        self::assertSame(
            $expected,
            call_user_func($this->createTranslator($allowUpdate, $allowDelete), $textbox)
        );
    }


    /**
     * Data privider for `testInvoke`.
     *
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        yield 'create authorized, delete authorized' => [
            (new Model())
                ->setId(1)
                ->setBody('<p>Text box 1</p>')
                ->setHeading('Text box A')
                ->setCategory((new FolderModel())->setId(1)),
            true,
            true,
            [
                'id' => 1,
                'heading' => 'Text box A',
                'body' => '<p>Text box 1</p>',
                'folder' => [
                    'id' => 1,
                ],
                '_links' => [
                    'update' => '/textbox/update/1',
                    'delete' => '/textbox/update/1',
                ]
            ]
        ];

        yield 'create unauthorized, delete unauthorized' => [
            (new Model())
                ->setId(2)
                ->setBody('<p>Text box 2</p>')
                ->setHeading('Text box B')
                ->setCategory((new FolderModel())->setId(2)),
            false,
            false,
            [
                'id' => 2,
                'heading' => 'Text box B',
                'body' => '<p>Text box 2</p>',
                'folder' => [
                    'id' => 2,
                ],
                '_links' => [
                    'update' => null,
                    'delete' => null,
                ]
            ]
        ];

        yield 'create authorized, delete unauthorized' => [
            (new Model())
                ->setId(3)
                ->setBody('<p>Text box 3</p>')
                ->setHeading('Text box C')
                ->setCategory((new FolderModel())->setId(3)),
            true,
            false,
            [
                'id' => 3,
                'heading' => 'Text box C',
                'body' => '<p>Text box 3</p>',
                'folder' => [
                    'id' => 3,
                ],
                '_links' => [
                    'update' => '/textbox/update/3',
                    'delete' => null,
                ]
            ]
        ];

        yield 'create unauthorized, delete authorized' => [
            (new Model())
                ->setId(4)
                ->setBody('<p>Text box 4</p>')
                ->setHeading('Text box D')
                ->setCategory((new FolderModel())->setId(4)),
            false,
            true,
            [
                'id' => 4,
                'heading' => 'Text box D',
                'body' => '<p>Text box 4</p>',
                'folder' => [
                    'id' => 4,
                ],
                '_links' => [
                    'update' => null,
                    'delete' => '/textbox/update/4',
                ]
            ]
        ];
    }

    /**
     * Build the testable instance of `ModelToArray`.
     *
     * Requires setup of a test router and authorizer.
     *
     * @param bool $allowUpdate
     * @param bool $allowDelete
     * @return ModelToArray
     */
    private function createTranslator(bool $allowUpdate, bool $allowDelete): ModelToArray
    {
        $testRouter = new RouterContainer();
        $testRouter->getMap()->route('textbox.updateTextbox', '/textbox/update/{id}');
        $testRouter->getMap()->route('textbox.deleteTextbox', '/textbox/update/{id}');

        /** @var Authorizer|MockObject $testAuthorizer */
        $testAuthorizer = $this->createMock(Authorizer::class);
        $testAuthorizer
            ->method('isPathAuthorizedForUser')
            ->willReturnCallback(function (string $route) use ($allowUpdate, $allowDelete): bool {
                if ($route === 'textbox.updateTextbox') {
                    return $allowUpdate;
                }
                if ($route === 'textbox.deleteTextbox') {
                    return $allowDelete;
                }
                return false;
            });

        return new ModelToArray(
            function (FolderModel $folder) {
                return ['id' => $folder->getId()];
            },
            $testRouter->getGenerator(),
            $testAuthorizer,
            new User(),
            new MockAdapter()
        );
    }
}
