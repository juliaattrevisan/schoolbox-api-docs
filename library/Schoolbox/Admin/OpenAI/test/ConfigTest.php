<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Admin\OpenAI\test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Editor\OpenAI\Helper;

class ConfigTest extends TestCase
{
    /**
     * Test that generateInitConfig returns the correct Azure config structure for all modules.
     */
    public function testGenerateInitConfigReturnsCorrectStructureForAzure()
    {
        $result = Config::generateInitConfig(Helper::ENDPOINT_TYPE_AZURE);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertArrayHasKey($module, $result);
            $this->assertEquals(Config::CONFIG_STRUCT_AZURE, $result[$module]);
        }
    }

    /**
     * Test that generateInitConfig returns the correct OpenAI config structure for all modules.
     */
    public function testGenerateInitConfigReturnsCorrectStructureForOpenAI()
    {
        $result = Config::generateInitConfig(Helper::ENDPOINT_TYPE_OPENAI);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertArrayHasKey($module, $result);
            $this->assertEquals(Config::CONFIG_STRUCT_OPENAI, $result[$module]);
        }
    }

    /**
     * Test that generateInitConfig returns an empty array for an unknown provider.
     */
    public function testGenerateInitConfigReturnsEmptyArrayForUnknownProvider()
    {
        $result = Config::generateInitConfig('UnknownProvider');
        $this->assertEquals([], $result);
    }

    /**
     * Test that mergeConfig overwrites values and removes invalid keys for Azure provider.
     */
    public function testMergeConfigOverwritesAndRemovesInvalidKeysForAzure()
    {
        $input = [];
        foreach (Config::ALL_MODULES as $module) {
            $input[$module] = [
                'provider' => 'Wrong',
                'enabled' => true,
                'open_ai_key' => 'abc',
                'resource_name' => 'res',
                'deployment_id' => 'dep',
                'invalid_key' => 'should be removed'
            ];
        }
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_AZURE);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged[$module]['provider']);
            $this->assertTrue($merged[$module]['enabled']);
            $this->assertEquals('abc', $merged[$module]['open_ai_key']);
            $this->assertEquals('res', $merged[$module]['resource_name']);
            $this->assertEquals('dep', $merged[$module]['deployment_id']);
            $this->assertArrayNotHasKey('invalid_key', $merged[$module]);
            $this->assertCount(count(Config::CONFIG_STRUCT_AZURE), $merged[$module]);
        }
    }

    /**
     * Test that mergeConfig overwrites values and removes invalid keys for OpenAI provider.
     */
    public function testMergeConfigOverwritesAndRemovesInvalidKeysForOpenAI()
    {
        $input = [];
        foreach (Config::ALL_MODULES as $module) {
            $input[$module] = [
                'provider' => 'Wrong',
                'open_ai_key' => 'xyz',
                'model' => 'gpt-4o',
                'extra' => 'should be removed'
            ];
        }
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged[$module]['provider']);
            $this->assertEquals('xyz', $merged[$module]['open_ai_key']);
            $this->assertEquals('gpt-4o', $merged[$module]['model']);
            $this->assertArrayNotHasKey('extra', $merged[$module]);
            $this->assertCount(count(Config::CONFIG_STRUCT_OPENAI), $merged[$module]);
        }
    }

    /**
     * Test that mergeConfig fills missing keys with defaults for OpenAI provider.
     */
    public function testMergeConfigFillsMissingKeysWithDefaults()
    {
        $input = [];
        foreach (Config::ALL_MODULES as $module) {
            $input[$module] = [
                'open_ai_key' => 'partial',
            ];
        }
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertEquals('partial', $merged[$module]['open_ai_key']);
            $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged[$module]['provider']);
            $this->assertEquals(Config::NOT_ACTIVE, $merged[$module]['model']);
        }
    }

    /**
     * Test that mergeConfig returns an empty array for an unknown provider.
     */
    public function testMergeConfigReturnsEmptyArrayForUnknownProvider()
    {
        $input = [];
        foreach (Config::ALL_MODULES as $module) {
            $input[$module] = ['open_ai_key' => 'irrelevant'];
        }
        $merged = Config::mergeConfig($input, 'UnknownProvider');
        $this->assertEquals([], $merged);
    }

    /**
     * Test that mergeConfig fills all modules with defaults when given an empty config.
     */
    public function testMergeConfigHandlesEmptyConfig()
    {
        $merged = Config::mergeConfig([], Helper::ENDPOINT_TYPE_OPENAI);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertEquals(Config::CONFIG_STRUCT_OPENAI, $merged[$module]);
        }
    }

    /**
     * Test that mergeConfig always sets the provider key to the selected provider, regardless of input.
     */
    public function testMergeConfigAlwaysSetsProviderCorrectly()
    {
        $input = [];
        foreach (Config::ALL_MODULES as $module) {
            $input[$module] = [
                'provider' => 'SomeOtherProvider',
                'open_ai_key' => 'key',
                'model' => 'gpt-4o',
            ];
        }
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged[$module]['provider']);
        }
    }

    /**
     * Test that mergeConfig fills missing modules with default config for the provider.
     */
    public function testMergeConfigWithMissingModules()
    {
        $input = [
            Config::ALL_MODULES[0] => [
                'open_ai_key' => 'onlyone',
                'model' => 'gpt-4o',
            ]
        ];
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('onlyone', $merged[Config::ALL_MODULES[0]]['open_ai_key']);
        foreach (array_slice(Config::ALL_MODULES, 1) as $module) {
            $this->assertEquals(Config::CONFIG_STRUCT_OPENAI, $merged[$module]);
        }
    }

    /**
     * Test that mergeConfig ignores extra modules not in ALL_MODULES.
     */
    public function testMergeConfigWithExtraModulesIgnored()
    {
        $input = [
            'notAModule' => [
                'open_ai_key' => 'shouldBeIgnored',
                'model' => 'gpt-4o',
            ]
        ];
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertArrayNotHasKey('notAModule', $merged);
        foreach (Config::ALL_MODULES as $module) {
            $this->assertEquals(Config::CONFIG_STRUCT_OPENAI, $merged[$module]);
        }
    }


    /**
     * Test that mergeConfig does not mutate the input array.
     */
    public function testMergeConfigDoesNotMutateInput()
    {
        $input = [];
        foreach (Config::ALL_MODULES as $module) {
            $input[$module] = [
                'open_ai_key' => 'immutable',
                'model' => 'gpt-4o',
            ];
        }
        $copy = $input;
        Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals($copy, $input);
    }

    /**
     * Test mergeConfig with a realistic Azure JSON payload for all modules.
     */
    public function testMergeConfigWithExampleJsonForAzure()
    {
        $json = '{"chat":{"enabled":true,"open_ai_key":"test key","resource_name":"","deployment_id":""},"lessonplan":{"enabled":true,"open_ai_key":"test key","resource_name":"","deployment_id":""}}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        foreach (\Schoolbox\Admin\OpenAI\Config::ALL_MODULES as $module) {
            $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged[$module]['provider']);
            $this->assertTrue($merged[$module]['enabled']);
            $this->assertEquals('test key', $merged[$module]['open_ai_key']);
            $this->assertEquals('', $merged[$module]['resource_name']);
            $this->assertEquals('', $merged[$module]['deployment_id']);
        }
    }

    /**
     * Test mergeConfig with a realistic OpenAI JSON payload for all modules.
     */
    public function testMergeConfigWithExampleJsonForOpenAI()
    {
        $json = '{"chat":{"provider":"OpenAI","open_ai_key":"test key","model":"gpt-4.1-mini"},"lessonplan":{"provider":"OpenAI","open_ai_key":"","model":"gpt-4.1"}}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('test key', $merged['chat']['open_ai_key']);
        $this->assertEquals('gpt-4.1-mini', $merged['chat']['model']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['chat']['provider']);
        $this->assertEquals('', $merged['lessonplan']['open_ai_key']);
        $this->assertEquals('gpt-4.1', $merged['lessonplan']['model']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['lessonplan']['provider']);
    }

    /**
     * Test mergeConfig with a partial Azure JSON payload (some keys missing, some present).
     */
    public function testMergeConfigWithPartialJsonForAzure()
    {
        $json = '{"chat":{"enabled":false,"open_ai_key":"partial-key"},"lessonplan":{"resource_name":"lesson-resource"}}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        $this->assertFalse($merged['chat']['enabled']);
        $this->assertEquals('partial-key', $merged['chat']['open_ai_key']);
        $this->assertEquals('', $merged['chat']['resource_name']);
        $this->assertEquals('', $merged['chat']['deployment_id']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['chat']['provider']);
        $this->assertEquals('', $merged['lessonplan']['open_ai_key']);
        $this->assertEquals('lesson-resource', $merged['lessonplan']['resource_name']);
        $this->assertEquals('', $merged['lessonplan']['deployment_id']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['lessonplan']['provider']);
    }

    /**
     * Test mergeConfig with empty JSON objects for Azure modules (should fill with defaults).
     */
    public function testMergeConfigWithEmptyJsonForAzure()
    {
        $json = '{"chat":{},"lessonplan":{}}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        foreach (["chat", "lessonplan"] as $module) {
            $this->assertEquals(\Schoolbox\Admin\OpenAI\Config::CONFIG_STRUCT_AZURE, $merged[$module]);
        }
    }

    /**
     * Test mergeConfig with a missing module in OpenAI JSON payload (should fill with defaults for missing module).
     */
    public function testMergeConfigWithMissingModuleJsonForOpenAI()
    {
        $json = '{"chat":{"open_ai_key":"only-key","model":"gpt-4o"}}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('only-key', $merged['chat']['open_ai_key']);
        $this->assertEquals('gpt-4o', $merged['chat']['model']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['chat']['provider']);
        $this->assertEquals(\Schoolbox\Admin\OpenAI\Config::CONFIG_STRUCT_OPENAI, $merged['lessonplan']);
    }
}
