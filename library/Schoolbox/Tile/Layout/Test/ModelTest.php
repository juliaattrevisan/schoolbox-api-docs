<?php

namespace Schoolbox\Tile\Layout\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Tile\Layout\Model;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class ModelTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerColumnGapAndWidth')]
    public function testColumnGapAndWidthUsePaddingAsFlexGap(
        int $columnCount,
        int $columnPadding,
        float $expectedGap,
        float $expectedTileWidth
    ): void {
        $layout = (new Model())
            ->setColumnCount($columnCount)
            ->setColumnPadding($columnPadding);

        $this->assertEqualsWithDelta($expectedGap, $layout->getColumnGapPercentage(), 0.000001);
        $this->assertEqualsWithDelta($expectedTileWidth, $layout->getColumnTileWidthPercentage(), 0.000001);
    }

    public static function providerColumnGapAndWidth(): array
    {
        return [
            'three columns with ten percent padding' => [
                3,
                10,
                3.3333333333333335,
                31.11111111111111,
            ],
            'zero padding keeps default column widths' => [
                3,
                0,
                0,
                33.333333333333336,
            ],
            'single column has no edge gap' => [
                1,
                10,
                10,
                100,
            ],
            'invalid column count falls back to default' => [
                0,
                10,
                3.3333333333333335,
                31.11111111111111,
            ],
        ];
    }

    public function testTileListTemplateRendersColumnGapAndWidthAsUsableCssValues(): void
    {
        $layout = (new Model())
            ->setId(33)
            ->setLayoutType(Model::LAYOUT_TYPE_COLUMN)
            ->setColumnCount(3)
            ->setColumnPadding(10)
            ->setAspectRatioX(16)
            ->setAspectRatioY(9);

        $html = $this->getTwig()->render('@tile/sub/tileList.twig', [
            'layout' => $layout,
            'tileList' => [],
            'editable' => true,
            'CDN_PREFIX' => '',
            'config' => [
                'site_version' => 'test',
            ],
            'RETURN_URL' => [
                'name' => 'return',
                'previous' => [
                    'encoded' => 'previous',
                ],
            ],
        ]);

        $this->assertStringContainsString('column-gap: 3.3333333333333%;', $html);
        $this->assertStringContainsString('row-gap: 0;', $html);
        $this->assertStringContainsString('width: 31.111111111111%;', $html);
        $this->assertStringContainsString('padding-bottom: 17.5%;', $html);
        $this->assertStringContainsString('data-column-gap-percentage="3.3333333333333"', $html);
        $this->assertStringNotContainsString('\\25', $html);
        $this->assertStringNotContainsString('\\2E', $html);
    }

    private function getTwig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 2) . '/templates', 'tile');

        $twig = new Environment($loader);
        $twig->addFunction(new TwigFunction('routeLookup', static fn (): string => ''));

        return $twig;
    }
}
