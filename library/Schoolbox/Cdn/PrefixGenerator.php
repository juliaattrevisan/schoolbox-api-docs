<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Cdn;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * CDN prefix generator.
 */
class PrefixGenerator
{
    /**
     * The default prefix: not a CDN at all, it references the same host as all
     * other Schoolbox requests.
     */
    private const DEFAULT_PREFIX = '/static';

    /**
     * Config is required for determining:
     * * whether assets are served from CDN
     * * if so, the CDN URL
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Generates a CDN prefix based on Schoolbox's current configuration.
     *
     * This value must be prefixed to all requests for static assets, so that
     * static assets are served from either:
     * * a configured CDN (with or without a replacement for the site version)
     * * the `/static` folder on the same host that serves Schoolbox itself
     *
     * @return string
     */
    public function generate(): string
    {
        if (!$this->config->get(ConfigMapper::NAME_SITE_STATIC_ASSET_SERVE_FROM_CDN)) {
            return self::DEFAULT_PREFIX;
        }
        return str_replace(
            '{site_version}',
            $this->config->get(ConfigMapper::NAME_SITE_VERSION),
            $this->config->get(ConfigMapper::NAME_SITE_STATIC_ASSET_PREFIX),
        );
    }
}
