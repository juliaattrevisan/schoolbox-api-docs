<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\SkinConfig\Helper;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class HelperTest extends TestCase
{
    public function testBuildPreviewDataIncludesDropShadowCssVariable(): void
    {
        $dropShadowConfig = (new Config())
            ->setId(100)
            ->setName('skin_container_drop_shadow')
            ->setValue('none')
            ->setFieldId(ConfigMapper::FIELD_SELECT)
            ->setOptions((string) json_encode([
                'customProperty' => '--sbx-config-container-drop-shadow',
                'select' => [
                    [
                        'name' => 'None',
                        'value' => 'none',
                    ],
                    [
                        'name' => 'Medium',
                        'value' => 'var(--sbx-shadow-m)',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

        $configMapper = $this->createStub(ConfigMapper::class);
        $configMapper
            ->method('findByColumnList')
            ->willReturn([$dropShadowConfig]);

        $configLoader = $this->createMock(IConfigLoader::class);
        $configLoader
            ->method('get')
            ->with('skin_sonar_custom_css')
            ->willReturn('');

        $previewData = (new Helper($configMapper, $configLoader))->buildPreviewData([
            'toggle' => [
                'enabled' => true,
            ],
            'sonar' => [
                'Drop Shadow' => [
                    [
                        'name' => null,
                        'options' => [
                            [
                                'id' => 100,
                                'value' => 'var(--sbx-shadow-m)',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'var(--sbx-shadow-m)',
            $previewData['cssVariables']['--sbx-config-container-drop-shadow']
        );
    }
}
