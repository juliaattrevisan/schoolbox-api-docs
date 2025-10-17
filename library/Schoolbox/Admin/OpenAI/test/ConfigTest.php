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
    // test \Schoolbox\Admin\OpenAI\Config::migrate ensure that if AI is set, that it migrates the config correctly
    // open AI, Azure, with a mix of api key set, and model both set and not set

    // test \Schoolbox\Admin\OpenAI\Config::migrate to ensure if AI is off, that the config still exists, with initial values, and provider is off

    // test \Schoolbox\Admin\OpenAI\Config::isEnabled with different providers, and off to ensure it is correctly handeling the enabled state

    // test \Schoolbox\Admin\OpenAI\Config::decode to ensure it decodes json correctly, and handles invalid json gracefully

    // test \Schoolbox\Admin\OpenAI\Config::decode to ensure it returns the expected ai config structure
}