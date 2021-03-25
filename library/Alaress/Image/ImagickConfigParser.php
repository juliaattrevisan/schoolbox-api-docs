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
     * Parse ImageMagick's policy XML file for resource limits
     *
     * @return string[]
     */
    public function parseImageMagickResourceLimits(): array
    {
        // Map resource limits (domain="resource") from name to value
        $resourceLimitMap  = [];
        $imageMagickConfig = simplexml_load_string(file_get_contents("/etc/ImageMagick-6/policy.xml"));
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
        return $resourceLimitMap;
    }
}
