<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mail;


use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\IConfigLoader;
use Zend\Uri\Uri;

class CoreContext {

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
            'site_location',
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

        $this->context['config']['fqdn'] = trim(
            $this->context['config']['site_hostname'] . "." . $this->context['config']['site_domain'],
            '.'
        );

        // Create a fully-qualified CDN prefix
        $prefixGenerator = new PrefixGenerator($conf);
        $cdnPrefix       = $prefixGenerator->generate();
        $cdnPrefixUrl    = (new Uri($cdnPrefix))->makeRelative($this->context['config']['fqdn']);
        $this->context['config']['fqdn_cdn_prefix'] = $cdnPrefixUrl->toString();

        $this->context['config']['year'] = date('Y');
        $this->context['config']['padding'] = 4;

        // I'm reasonably sure that these logos are not used anymore.
        // I can find no reference to `config.logo` in twig templates.
        // TODO remove the old logo images completely
//        $site = $this->context['config']['site_location'];
//
//        $possibleLogos = array(
//            'email-logo',
//            "$site-logo",
//            'logo',
//            'hercules',
//        );
//
//        foreach ($possibleLogos as $filename) {
//            if (file_exists(SITE_root . "/www/images/$site/$filename.png")) {
//                $this->context['config']['logo'] = $filename;
//                break;
//            }
//        }
//
//        if (!isset($this->context['config']['logo'])) {
//            $this->context['config']['logo'] = 'logo';
//        }

        // feedback url
        $this->context['config']['feedback_url_object'] = new \Url($conf->get('feedback_url'));

        // Extract skin builder colors
        $skinColors = [
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
        ];
        foreach ($skinColors as $skinColor) {
            $this->context['colours'][$skinColor] = $conf->get($skinColor);
        }
    }

    public function getContext()
    {
        return $this->context;
    }
}
