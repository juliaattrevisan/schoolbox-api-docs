<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mail;

use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class CoreContext
{
    /**
     * Mapping of skin config keys to their corresponding corner radius values, as CSS custom properties cannot be used in email templates,
     */
    private const SKIN_CORNER_RADIUS_VALUES = [
        'var(--sbx-border-radius-none)' => '0',
        'var(--sbx-border-radius-s)' => '0.25rem',
        'var(--sbx-border-radius-m)' => '0.5rem',
        'var(--sbx-border-radius-l)' => '0.75rem',
    ];

    /**
     * When Sonar UI is disabled, these Sonar skin config keys should fall back to legacy skin config values or hardcoded defaults,
     * as the email templates still use these Sonar skin config keys but the legacy skin config values still need to be used while Sonar UI is disabled.
     * This will be removed once Sonar UI is enabled for all schools and the legacy skin config values are no longer used anywhere in the codebase.
     */
    private const DISABLED_SONAR_SKIN_CONFIG_FALLBACKS = [
        'skin_colour_menu_background' => ['config' => 'skin_colour_navigation_background'],
        'skin_colour_menu_foreground' => ['config' => 'skin_colour_navigation_foreground'],
        'skin_colour_accent' => ['config' => 'skin_colour_content_ui_submit_background'],
        'skin_colour_accent_text' => ['config' => 'skin_colour_content_ui_submit_foreground'],
        'skin_container_corner_radius' => ['value' => '0'],
        'skin_field_corner_radius' => ['value' => '0'],
    ];

    /** @var array */
    protected $context;

    /**
     * @param IConfigLoader $conf
     */
    public function __construct(IConfigLoader $conf)
    {
        $required = array(
            'title',
            'school_name',
            'school_address',
            'site_hostname',
            'site_school_code',
            'site_domain',
            'forgotten_password',
            'feedback_url',
            'feedback_text',
            'read_more',
            'email_banner_image',
            'digest_email_intro'
        );

        foreach ($required as $key) {
            $this->context['config'][$key] = $conf->get($key);
        }

        $fqdn = trim(
            $this->context['config']['site_hostname'] . "." . $this->context['config']['site_domain'],
            '.'
        );
        $this->context['config']['fqdn'] = $fqdn;

        // Create a fully-qualified CDN prefix
        $prefixGenerator = new PrefixGenerator($conf);
        $this->context['config']['fqdn_cdn_prefix'] = $prefixGenerator->generateWithBaseUrl('https://' . $fqdn);
        $this->context['config']['year']            = date('Y');
        $this->context['config']['padding']         = 4;

        // feedback url
        $this->context['config']['feedback_url_object'] = new \Url($conf->get('feedback_url'), $fqdn);

        // Extract skin builder config
        $skinConfig = [
            'skin_colour_navigation_foreground',
            'skin_colour_navigation_background',
            'skin_colour_navigation_hover',
            'skin_colour_offcanvas_foreground',
            'skin_colour_offcanvas_background',
            'skin_colour_offcanvas_hover',
            'skin_colour_off_canvas',
            'skin_colour_content_ui_foreground',
            'skin_colour_content_ui_background',
            'skin_colour_content_ui_hover',
            'skin_colour_content_ui_selected',
            'skin_colour_content_ui_submit_foreground',
            'skin_colour_content_ui_submit_background',
            'skin_colour_accent_foreground',
            'skin_colour_accent_background',
            'skin_colour_accent_hover',
            'skin_colour_logo_background',
            'skin_colour_body_bg',
            // add new Sonar UI colours/settings, eventually those above should be replaced by these
            'skin_colour_menu_background',
            'skin_colour_menu_foreground',
            'skin_colour_accent',
            'skin_colour_accent_text',
            'skin_colour_active',
            'skin_container_corner_radius',
            'skin_field_corner_radius',
        ];
        $advancedUiEnabled = (bool) $conf->get(ConfigMapper::NAME_SONAR_UI_TOGGLE);
        foreach ($skinConfig as $skinConfigKey) {
            $this->context['skinConfig'][$skinConfigKey] = $this->resolveSkinConfigValue(
                $skinConfigKey,
                $this->getSkinConfigValue($conf, $skinConfigKey, $advancedUiEnabled)
            );
        }
    }

    /**
     * Email templates use Sonar skin config keys, but legacy skin config values still need to be used
     * while Sonar UI is disabled.
     */
    private function getSkinConfigValue(IConfigLoader $conf, string $key, bool $advancedUiEnabled)
    {
        if ($advancedUiEnabled || !array_key_exists($key, self::DISABLED_SONAR_SKIN_CONFIG_FALLBACKS)) {
            return $conf->get($key);
        }

        $fallback = self::DISABLED_SONAR_SKIN_CONFIG_FALLBACKS[$key];
        if (array_key_exists('config', $fallback)) {
            return $conf->get($fallback['config']);
        }

        return $fallback['value'];
    }

    /**
     * Resolve values to the actual values, as CSS custom properties cannot be used in email templates,
     * so we need to convert them to their actual values.
     */
    private function resolveSkinConfigValue(string $key, $value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($key === 'skin_container_corner_radius' || $key === 'skin_field_corner_radius') {
            return self::SKIN_CORNER_RADIUS_VALUES[$value] ?? $value;
        }

        return $value;
    }

    public function getContext()
    {
        return $this->context;
    }
}
