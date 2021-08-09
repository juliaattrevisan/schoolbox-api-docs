<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Version;

use Schoolbox\IConfigLoader;
use Schoolbox\InstanceRegistry\InstanceRegistry;

class Version
{
    public IConfigLoader $config;

    public InstanceRegistry $instanceRegistry;

    public Section $section;

    public const SITE_ENVIRONMENT_CLIENT_HOSTED = 'client-hosted'; // Hosted on client's infrastructure, regardless
                                                                   //  if physically onprem or in their cloud.
    public const SITE_ENVIRONMENT_CLOUD_SASS    = 'cloud-sass';    // Hosted on Schoolbox's AWS infrastructure.

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config           = $config;
        $this->instanceRegistry = new InstanceRegistry($this->config);
        $this->section          = new Section($this->config->get('site_version'));
    }

    /**
     * get a complete representation of this schoolbox instances version.
     *
     * @return string
     * @example these would look something like:
     *          - 21.0.2-1
     *          - 20.2.11-1
     *          - 20.1.8-2
     *          - 20.0.12-1
     *          - 19.1.12-3
     *          - 18.1.8-1
     *
     */
    public function getFull(): string
    {
        return $this->section->getFull();
    }

    /**
     * get the major version section of this running schoolbox instance.
     *
     * @return string
     * @example these would look something like:
     *          - 21.0
     *          - 20.2
     *          - 20.1
     *          - 20.0
     *          - 19.1
     *          - 18.1
     *
     */
    public function getMajor(): string
    {
        return $this->section->getMajor();
    }

    /**
     * get the minor version section of this running schoolbox instance.
     *
     * @return string
     * @example these would look something like:
     *          - .4-1
     *          - .11-1
     *          - .8-2
     *
     */
    public function getMinor(): string
    {
        return $this->section->getMinor();
    }

    /**
     * is this schoolbox instance client hosted?
     *
     * @return boolean
     */
    public function isClientHosted(): bool
    {
        return $this->config->get('site_environment') === self::SITE_ENVIRONMENT_CLIENT_HOSTED;
    }

    /**
     * is this schoolbox instance cloud hosted?
     *
     * @return bool
     */
    public function isCloudHosted(): bool
    {
        return $this->config->get('site_environment') === self::SITE_ENVIRONMENT_CLOUD_SASS;
    }

    public function showUpgradeReminder(): bool
    {
        /**
         * if the instance is not client hosted(cloud hosted), they dont need the reminder
         */
        if (!$this->isClientHosted()) {
            return false;
        }

        /**
         * if the instance is the same or ahead of the current latest release,
         * then they dont need the reminder
         */
        $latestMajorVersion = $this->instanceRegistry->getLatestVersionMajor();
        if ($this->getMajor() >= $latestMajorVersion) {
            return false;
        }

        /**
         * it looks like someone has already seen and dismissed this upgrade reminder.
         * let's not bombard them anymore until there is a new major version released
         */
        if ($this->config->get('dismiss_version') == $latestMajorVersion) {
            return false;
        }

        return true;
    }
}
