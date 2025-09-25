<?php

namespace Schoolbox\Config\API;

use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\API\Token\JwtParser\InstanceRegistryIssued;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class AuthorizationHelper
{
    /**
     * Get the Authorization header for API requests
     *
     * @param array|null $serverParams Server parameters from request, if null will use $_SERVER
     * @return string|null
     */
    public static function getAuthorizationHeader(?array $serverParams = null): ?string
    {
        $serverParams = $serverParams ?? $_SERVER;

        // Check for Authorization header in server params
        return $serverParams['HTTP_AUTHORIZATION'] ?? null;
    }

    /**
     * Extract Bearer token from Authorization header
     *
     * @param array|null $serverParams Server parameters from request, if null will use $_SERVER
     * @return string|null
     */
    public static function getBearerToken(?array $serverParams = null): ?string
    {
        $authHeader = self::getAuthorizationHeader($serverParams);

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if the current request is from an Instance Registry token
     *
     * @param array $serverParams
     * @param InstanceRegistryIssued $jwtParser
     * @return bool
     */
    public static function isInstanceRegistryTokenRequest(array $serverParams, InstanceRegistryIssued $jwtParser): bool
    {
        $jwt = self::getBearerToken($serverParams);
        if (empty($jwt)) {
            return false;
        }

        try {
            // Use the InstanceRegistryIssued parser to validate if this is an Instance Registry token
            return $jwtParser->accepts($jwt);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if config contains write-only data (passwords, secrets, keys)
     *
     * @param Config $config
     * @return bool
     */
    public static function isWriteOnlyConfig(Config $config): bool
    {
        // Check if field is a password
        if ($config->getFieldId() === ConfigMapper::FIELD_PASSWORD) {
            return true;
        }

        // Check if config name contains sensitive keywords
        $name = strtolower($config->getName());
        return str_contains($name, 'key') || str_contains($name, 'secret');
    }

    /**
     * Convert Config object to API output array
     *
     * @param Config $config
     * @return array
     */
    public static function configToArray(Config $config): array
    {
        $result = [
            'name' => $config->getName(),
            'title' => $config->getTitle(),
            'description' => $config->getDescription()
        ];

        // Only include value if it's not a write-only config
        if (!self::isWriteOnlyConfig($config)) {
            $result['value'] = $config->getValue();
        }

        return $result;
    }

    /**
     * Check if user can access this config based on token type
     * Hidden configs are restricted for superusers but not for Instance Registry tokens
     *
     * @param Config $config
     * @param array $serverParams
     * @param InstanceRegistryIssued $jwtParser
     * @return bool
     */
    public static function canAccessConfig(Config $config, array $serverParams, InstanceRegistryIssued $jwtParser): bool
    {
        // Instance Registry tokens can access everything
        if (self::isInstanceRegistryTokenRequest($serverParams, $jwtParser)) {
            return true;
        }

        // Superusers cannot access hidden configs
        return !$config->getHidden();
    }

    /**
     * Check if user can edit this config based on token type
     * Hidden and non-editable configs are restricted for superusers but not for Instance Registry tokens
     *
     * @param Config $config
     * @param array $serverParams
     * @param InstanceRegistryIssued $jwtParser
     * @return bool
     */
    public static function canEditConfig(Config $config, array $serverParams, InstanceRegistryIssued $jwtParser): bool
    {
        // Instance Registry tokens can edit everything
        if (self::isInstanceRegistryTokenRequest($serverParams, $jwtParser)) {
            return true;
        }

        // Superusers cannot edit hidden or non-editable configs
        return !$config->getHidden() && $config->getEditable();
    }

    /**
     * Validate and serialize config value using the existing field strategy system
     *
     * @param mixed $value
     * @param Config $config
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @return string
     * @throws \UnexpectedValueException
     */
    public static function validateAndSerializeConfigValue($value, Config $config, FieldStrategyFactory $fieldStrategyFactory): string
    {
        $fieldId = $config->getFieldId();
        if ($fieldId === null) {
            // No field validation, just basic sanitization
            return trim((string)$value);
        }

        try {
            $valueSerializer = $fieldStrategyFactory->makeValueSerializer($fieldId);
            return $valueSerializer->serializeValue($value, $config);
        } catch (\UnexpectedValueException $e) {
            // Re-throw with more context
            throw new \UnexpectedValueException("Validation failed for config '{$config->getName()}': " . $e->getMessage());
        }
    }
}
