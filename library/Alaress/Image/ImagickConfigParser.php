<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image;

/**
 * Helper class to read and parse ImageMagick configuration from XML file
 */
class ImagickConfigParser
{
    /**
     * Attempt these configuration locations, in order.
     */
    private const IMAGICK_CONFIG_LOCATIONS = [
        '/etc/ImageMagick-6/policy.xml',
        '/etc/ImageMagick-7/policy.xml',
    ];

    /**
     * Parse ImageMagick's policy XML file for resource limits
     *
     * @return string[]
     */
    public function parseImageMagickResourceLimits(): array
    {
        // Map resource limits (domain="resource") from name to value
        $resourceLimitMap  = [];

        // This location could be different,
        // so attempt to load from multiple locations?
        foreach (self::IMAGICK_CONFIG_LOCATIONS as $location) {
            if (file_exists($location)) {
                $imageMagickConfig = simplexml_load_string(file_get_contents($location));
                foreach ($imageMagickConfig->xpath('policy') as $policyTag) {
                    $policyTagAttributes = $policyTag->attributes();
                    $isResourcePolicyTag = false;
                    $name                = $value = null;
                    foreach ($policyTagAttributes as $attributeName => $attributeValue) {
                        if ($attributeName === 'domain' && (string)$attributeValue === 'resource') {
                            $isResourcePolicyTag = true;
                        } elseif ($attributeName === 'name') {
                            $name = (string)$attributeValue;
                        } elseif ($attributeName === 'value') {
                            $value = (string)$attributeValue;
                        }
                    }
                    if ($isResourcePolicyTag) {
                        $resourceLimitMap[$name] = $value;
                    }
                }
                break;
            }
        }
        return $resourceLimitMap;
    }
}
