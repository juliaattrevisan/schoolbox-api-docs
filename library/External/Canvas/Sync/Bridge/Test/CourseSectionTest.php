<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Test;

use External\Canvas\Sync\Bridge\CourseSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;

#[CoversClass(CourseSection::class)]
class CourseSectionTest extends TestCase
{
    #[DataProvider('sisSourceIdTemplateProvider')]
    public function testFormatSisSourceIdTemplate(string $template, string $expected): void
    {
        $parent = $this->createFolder('2026 Classes');
        $folder = $this->createFolder('12 Mathematics Advanced', 'MAIN/12MAT');

        self::assertSame(
            $expected,
            CourseSection::formatSisSourceIdTemplate($template, $folder, $parent)
        );
    }

    public static function sisSourceIdTemplateProvider(): array
    {
        return [
            'folder code' => [
                '{folderCode}',
                '12MAT',
            ],
            'default concatenated folder and parent code' => [
                '{folderCode}{parentCode}',
                '12MAT2026',
            ],
            'parent code before folder code' => [
                '{parentCode} {folderCode}',
                '2026 12MAT',
            ],
            'folder code before parent code' => [
                '{folderCode}_{parentCode}',
                '12MAT_2026',
            ],
            'folder name' => [
                '{folderName}',
                '12 Mathematics Advanced',
            ],
        ];
    }

    private function createFolder(string $name, ?string $code = null): Folder\Model
    {
        $folder = (new Folder\Model())
            ->setName($name)
            ->setCodes(new GenericCollection(Folder\Code\Model::class));

        if ($code !== null) {
            $folder->getCodes()->add(
                (new Folder\Code\Model())
                    ->setFolder($folder)
                    ->setCode($code)
            );
        }

        return $folder;
    }
}
