<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Redirect\Test;

use Alaress\Database\Connector\IConnector;
use External\Canvas\Importer\CanvasDb;
use External\Canvas\Redirect\AbstractRedirect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Folder;

#[CoversClass(AbstractRedirect::class)]
class AbstractRedirectTest extends TestCase
{
    #[DataProvider('invalidHomepageRedirectTargetProvider')]
    public function testInvalidHomepageRedirectTargetDoesNotLookUpCanvasCourse(?string $target): void
    {
        $canvasDb = new CanvasDb($this->createConfig($target));
        self::assertSame($target, $canvasDb->code_redirect_homepages);

        $folderMapper = $this->createStub(Folder\Mapper::class);
        $folderMapper->method('getDatabase')->willReturn($this->createStub(IConnector::class));
        $redirect = new AbstractRedirect('', $canvasDb, $folderMapper);

        self::assertNull(
            $redirect->getCanvasCourseId(
                $this->createFolder('Mathematics', '12MAT'),
                $this->createFolder('2026 Classes', '2026')
            )
        );
    }

    public static function invalidHomepageRedirectTargetProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'unknown value' => ['unknown'],
        ];
    }

    private function createConfig(?string $target): IConfigLoader
    {
        $values = [
            ConfigMapper::NAME_CANVAS_SISSOURCEID_TEMPLATE => '{folderCode}',
            ConfigMapper::NAME_CANVAS_CODE_REDIRECT_HOMEPAGES => $target,
        ];
        $config = $this->createStub(IConfigLoader::class);
        $config->method('get')->willReturnCallback(
            static fn (string $name): mixed => $values[$name] ?? null
        );

        return $config;
    }

    private function createFolder(string $name, string $code): Folder\Model
    {
        $folder = (new Folder\Model())
            ->setName($name)
            ->setCodes(new GenericCollection(Folder\Code\Model::class));
        $folder->getCodes()->add(
            (new Folder\Code\Model())
                ->setFolder($folder)
                ->setCode($code)
        );

        return $folder;
    }
}
