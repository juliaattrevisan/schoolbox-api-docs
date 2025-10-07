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
            $this->assertEquals(Config::CONFIG_STRUCT_AZURE, $result);
    }

    /**
     * Test that generateInitConfig returns the correct OpenAI config structure for all modules.
     */
    public function testGenerateInitConfigReturnsCorrectStructureForOpenAI()
    {
        $result = Config::generateInitConfig(Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals(Config::CONFIG_STRUCT_OPENAI, $result);
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
        $input = [
            'provider' => 'Wrong',
            'enabled' => true,
            'open_ai_key' => 'abc',
            'resource_name' => 'res',
            'deployment_id' => 'dep',
            'invalid_key' => 'should be removed'
        ];
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_AZURE);

        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['provider']);
        $this->assertTrue($merged['enabled']);
        $this->assertEquals('abc', $merged['open_ai_key']);
        $this->assertEquals('res', $merged['resource_name']);
        $this->assertEquals('dep', $merged['deployment_id']);
        $this->assertArrayNotHasKey('invalid_key', $merged);
        $this->assertCount(count(Config::CONFIG_STRUCT_AZURE), $merged);

    }

    /**
     * Test that mergeConfig overwrites values and removes invalid keys for OpenAI provider.
     */
    public function testMergeConfigOverwritesAndRemovesInvalidKeysForOpenAI()
    {
        $input = [
            'provider' => 'Wrong',
            'open_ai_key' => 'xyz',
            'model' => 'gpt-4o',
            'extra' => 'should be removed'
        ];

        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['provider']);
        $this->assertEquals('xyz', $merged['open_ai_key']);
        $this->assertEquals('gpt-4o', $merged['model']);
        $this->assertArrayNotHasKey('extra', $merged);
        $this->assertCount(count(Config::CONFIG_STRUCT_OPENAI), $merged);
    }

    /**
     * Test that mergeConfig fills missing keys with defaults for OpenAI provider.
     */
    public function testMergeConfigFillsMissingKeysWithDefaults()
    {
        $input = [
            'open_ai_key' => 'partial',
        ];

        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('partial', $merged['open_ai_key']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['provider']);
        $this->assertEquals(Config::NOT_ACTIVE, $merged['model']);
    }

    /**
     * Test that mergeConfig returns an empty array for an unknown provider.
     */
    public function testMergeConfigReturnsEmptyArrayForUnknownProvider()
    {
        $input = ['open_ai_key' => 'irrelevant'];
        $merged = Config::mergeConfig($input, 'UnknownProvider');
        $this->assertEquals([], $merged);
    }

    /**
     * Test that mergeConfig fills all modules with defaults when given an empty config.
     */
    public function testMergeConfigHandlesEmptyConfig()
    {
        $merged = Config::mergeConfig([], Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals(Config::CONFIG_STRUCT_OPENAI, $merged);
    }

    /**
     * Test that mergeConfig always sets the provider key to the selected provider, regardless of input.
     */
    public function testMergeConfigAlwaysSetsProviderCorrectly()
    {
        $input = [
            'provider' => 'SomeOtherProvider',
            'open_ai_key' => 'key',
            'model' => 'gpt-4o',
        ];
        $merged = Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['provider']);
    }

    /**
     * Test that mergeConfig does not mutate the input array.
     */
    public function testMergeConfigDoesNotMutateInput()
    {
        $input = [
            'open_ai_key' => 'immutable',
            'model' => 'gpt-4o',
        ];
        $copy = $input;
        Config::mergeConfig($input, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals($copy, $input);
    }

    /**
     * Test mergeConfig with a realistic Azure JSON payload for all modules.
     */
    public function testMergeConfigWithExampleJsonForAzureChat()
    {
        $json = '{"enabled":true,"open_ai_key":"test key","resource_name":"","deployment_id":""}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['provider']);
        $this->assertTrue($merged['enabled']);
        $this->assertEquals('test key', $merged['open_ai_key']);
        $this->assertEquals('', $merged['resource_name']);
        $this->assertEquals('', $merged['deployment_id']);
    }

    /**
     * Test mergeConfig with a realistic Azure JSON payload for all modules.
     */
    public function testMergeConfigWithExampleJsonForAzureLP()
    {
        $json = '{"enabled":true,"open_ai_key":"test key","resource_name":"","deployment_id":""}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['provider']);
        $this->assertTrue($merged['enabled']);
        $this->assertEquals('test key', $merged['open_ai_key']);
        $this->assertEquals('', $merged['resource_name']);
        $this->assertEquals('', $merged['deployment_id']);
    }

    /**
     * Test mergeConfig with a realistic OpenAI JSON payload for all modules.
     */
    public function testMergeConfigWithExampleJsonForOpenAIChat()
    {
        $json = '{"provider":"OpenAI","open_ai_key":"test key","model":"gpt-4.1-mini"}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('test key', $merged['open_ai_key']);
        $this->assertEquals('gpt-4.1-mini', $merged['model']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['provider']);
    }

    /**
     * Test mergeConfig with a realistic OpenAI JSON payload for all modules.
     */
    public function testMergeConfigWithExampleJsonForOpenAILP()
    {
        $json = '{"provider":"OpenAI","open_ai_key":"","model":"gpt-4.1"}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('', $merged['open_ai_key']);
        $this->assertEquals('gpt-4.1', $merged['model']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['provider']);
    }

    /**
     * Test mergeConfig with a partial Azure JSON payload (some keys missing, some present).
     */
    public function testMergeConfigWithPartialJsonForAzureChat()
    {
        $json = '{"enabled":false,"open_ai_key":"partial-key"}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        $this->assertFalse($merged['enabled']);
        $this->assertEquals('partial-key', $merged['open_ai_key']);
        $this->assertEquals('', $merged['resource_name']);
        $this->assertEquals('', $merged['deployment_id']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['provider']);
    }

    /**
     * Test mergeConfig with a partial Azure JSON payload (some keys missing, some present).
     */
    public function testMergeConfigWithPartialJsonForAzureLP()
    {
        $json = '{"resource_name":"lesson-resource"}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        $this->assertFalse($merged['enabled']);
        $this->assertEquals('', $merged['open_ai_key']);
        $this->assertEquals('lesson-resource', $merged['resource_name']);
        $this->assertEquals('', $merged['deployment_id']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_AZURE, $merged['provider']);
    }

    /**
     * Test mergeConfig with empty JSON objects for Azure modules (should fill with defaults).
     */
    public function testMergeConfigWithEmptyJsonForAzure()
    {
        $json = '{}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_AZURE);
        $this->assertEquals(\Schoolbox\Admin\OpenAI\Config::CONFIG_STRUCT_AZURE, $merged);
    }

    /**
     * Test mergeConfig with a missing module in OpenAI JSON payload (should fill with defaults for missing module).
     */
    public function testMergeConfigWithMissingModuleJsonForOpenAI()
    {
        $json = '{"open_ai_key":"only-key","model":"gpt-4o"}';
        $config = json_decode($json, true);
        $merged = \Schoolbox\Admin\OpenAI\Config::mergeConfig($config, Helper::ENDPOINT_TYPE_OPENAI);
        $this->assertEquals('only-key', $merged['open_ai_key']);
        $this->assertEquals('gpt-4o', $merged['model']);
        $this->assertEquals(Helper::ENDPOINT_TYPE_OPENAI, $merged['provider']);
        $this->assertNotEquals(\Schoolbox\Admin\OpenAI\Config::CONFIG_STRUCT_OPENAI, $merged);
    }
}
