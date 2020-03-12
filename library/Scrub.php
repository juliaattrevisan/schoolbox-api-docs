<?php

/**
 * Scrub
 *
 * Noob filter. Note that these methods are particularly aggressive
 * and can, on occasion, actually introduce undesirables into your
 * output. For example, apostrophes (single quotes) can sometimes
 * appear as &#0039 or something like that.
 *
 * This is intended as a global singleton class
 *
 * Originally floating functions scrubTEXT() and scrubHTML().
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Scrub
{

    /**
     * Singleton HTMLPurifier instance used by this class.
     *
     * @var HTMLPurifier
     */
    protected static $htmlpurifier_instance;

    /**
     * Cleans plain text of all nasty stuff
     * Can be used on input to database or output
     *
     * @param  $text
     * @return string
     */
    static public function text($text)
    {
        //
        // Don't think ENT_IGNORE is a problem here.
        // This is used to escape output as text or as an attribute - HTML tags
        // are escaped regardless.
        return htmlentities($text, ENT_IGNORE | ENT_QUOTES, "UTF-8");
    }

    /**
     * Scrubs text for XML output.
     *
     * @param  $text
     * @return string
     */
    static public function xml($text)
    {
        //
        // Don't think ENT_IGNORE is a problem here.
        // This is used to escape output as text or as an attribute - HTML tags
        // are escaped regardless.
        return htmlspecialchars($text, ENT_QUOTES);
    }

    /**
     * Turns html into plaintext.
     * Note: output will be quite unpredictable if used on input that contains tables or copious formatting.
     * @static
     * @param $html
     * @return string
     */
    static public function html2text($html)
    {
        // 0: replace all text-style block-level elements with newlines
        $text = preg_replace('#</(p|h[1-5]|br)>#i', "\n", $html);
        // 1: remove all remaining tags from input
        $text = strip_tags($text);
        // 2: all recurring whitespaces (including &nbsp;) to be trimmed
        $text = preg_replace('/(\s|&nbsp;)+/i', ' ', $text);
        // 3: unconvert entities (3 -> 4 is just for normalising any rogue characters)
        return html_entity_decode($text, ENT_QUOTES);
    }

    /**
     * Convert smart quotes to apostrophes/double quotes
     *
     * @static
     * @param $text
     * @return mixed
     */
    static public function smartQuotes($text)
    {
        $search = array(chr(145), chr(146), chr(147), chr(148), chr(151));
        $replace = array("'", "'", '"', '"', '-');

        $data = str_replace($search, $replace, $text);

        $search = array("&#8220;", "&#8221;", "&#8216;", "&#8217;", "&#8243;");
        $replace = array("&quot;", "&quot;", "&#39;", "&#39;", "&quot;");

        return str_replace($search, $replace, $data);
    }

    /**
     * Retrieves the singleton HTMLPurifier instance with our special config,
     * creating it if it doesn't already exist.
     *
     * @param string|null $uriBase If provided, this will be used to make URL absolute
     * @return HTMLPurifier
     */
    public static function getHtmlPurifierInstance(?string $uriBase = null): HTMLPurifier
    {
        if (!(self::$htmlpurifier_instance instanceof HTMLPurifier)) {

            $config = HTMLPurifier_Config::createDefault();

            /* http://htmlpurifier.org/phorum/read.php?3,6724
             */
            $config->set('CSS.AllowTricky', true);
            // Trusted style properties are:
            // position, top/left/right/bottom, z-index
            $config->set('CSS.Trusted', true);

            $config->set('Cache.SerializerPath', Config::get('xml_dir') . '/../purifier/');
            $config->set('AutoFormat.Linkify', true);

            // Allow Iframe
            $config->set('HTML.SafeIframe', true);
            $safeIframeSources = array(
                'www.youtube.com/',
                'player.vimeo.com/video/',
                'www.slideshare.net/slideshow/embed_code/',
                '(?:www|edu).glogster.com/glog(?:/|.php)',
                'wikieducator.org/',
                'www.google.com(?:.[a-z]{2})?/maps',
                'maps.google.com(?:.[a-z]{2})?/',
                'mapsengine.google.com(?:.[a-z]{2})?/',
                'docs.google.com(?:.[a-z]{2})?/',
                'www.tiki-toki.com/timeline/embed/',
                'www.thinglink.com/card/',
                'www.facebook.com/plugins/',
                'padlet.com/embed/',
                'linoit.com/users/',
                'www.showme.com/sma/embed',
                'www.sunsmart.com.au/uvalert/',
                'skydrive.live.com/embed',
                '(?:www\\.)?studyboost.com/',
                'prezi.com/embed/',
                '(?:www\\.)?spiderscribe.net/app/',
                '(?:www\\.)?etv.org.nz',
                'vhss-d.oddcast.com/vhss_editors/voki_player.swf',
                'e.issuu.com/embed.html',
                'webplayer.clickview.com.au/home/ev/',
                'online.clickview.com.au/MyLibrary/PlayEmbed',
                'online.clickview.com.au/Share/PlayEmbed',
                'www.khanacademy.org/embed_video',
                'cdn.iframe.ly/api',
                'vine.co/',
                'widgets.itunes.apple.com/',
                'embed-ssl.ted.com/',
                'giphy.com/embed/',
            );

            // Add any additional iframe sources as configured
            foreach (explode("\n", Config::get('safe_iframe_source_list')) as $source) {
                $source = trim($source);
                if (strlen($source) > 0) {
                    $safeIframeSources[] = preg_quote($source, '%');
                }
            }

            $config->set('URI.SafeIframeRegexp', '%^(?:https?:|)//(?:' . implode('|', $safeIframeSources) . ')%');
            $config->set('Output.FlashCompat', true);
            $config->set('HTML.FlashAllowFullScreen', true);

            $config->set('Attr.EnableID', true);

            $config->set(
                'Attr.AllowedFrameTargets', array(
                    '_blank',
                    '_self',
                    '_parent',
                    '_top'
                )
            );

            // Don't allow classes that affect layout in Foundation
            $config->set('Attr.ForbiddenClasses', ['row', 'column', 'island',]);

            $config->set(
                'URI.AllowedSchemes', array(
                    'http'    => true,
                    'https'   => true,
                    'mailto'  => true,
                    'ftp'     => true,
                    'ed2k'    => true,
                    'magnet'  => true,
                    'feed'    => true,
                    'dvcplay' => true,
                    'data'    => true,
                )
            );

            // Make url absolute, when fqdn is provided
            if ($uriBase) {
                $config->set('URI.MakeAbsolute', true);
                $config->set('URI.Base', $uriBase);
            }

            //
            // Allow embeds and objects, allowing more properties than standard
            // HTMLPurifier definition allows.
            // Include modules this way because adding modules doesn't work in a
            // logical manner.
            $config->set('HTML.DefinitionID', 'HTML + Extended Embeds + Extended Objects');
            $config->set('HTML.DefinitionRev', 1);

            if ($def = $config->maybeGetRawHTMLDefinition()) {
                $max = $config->get('HTML.MaxImgLength');

                /* Allow SchoolboxResource data elements
                 *
                 * data-schoolboxresource-width and -height vars are for mejs initial
                 * state rendering upon load ><
                 */
                $def->addAttribute('img', 'data-schoolboxresource-filesystem', 'Text');
                $def->addAttribute('img', 'data-schoolboxresource-type', 'Text');
                $def->addAttribute('img', 'data-schoolboxresource-thumbnail', 'Text');
                $def->addAttribute('img', 'data-schoolboxresource-source', 'Text');
                $def->addAttribute('img', 'data-schoolboxresource-width', 'Text');
                $def->addAttribute('img', 'data-schoolboxresource-height', 'Text');
                $def->addAttribute('img', 'data-schoolboxresource-fullwidth', 'Text');
                $def->addAttribute('img', 'usemap', 'Text');
                $def->addAttribute('div', 'data-oembed-url', 'Text');
                $def->addAttribute('a', 'data-iframely-url', 'Text');
                $def->addAttribute('a', 'data-template', 'Text');
                $def->addAttribute('a', 'data-portal', 'Text');
                $def->addAttribute('blockquote', 'data-id', 'Text');

                //
                // Definition of customised elements, copied directly out of modules.
                $embed = $def->addElement(
                    'embed', 'Inline', 'Empty', 'Common',
                    array(
                        'src*'              => 'URI#embedded',
                        'type'              => 'ContentType',
                        'width'             => 'Pixels#' . $max,
                        'height'            => 'Pixels#' . $max,
                        'allowscriptaccess' => 'Enum#never',
                        'allownetworking'   => 'Enum#internal',
                        'flashvars'         => 'Text',
                        'wmode'             => 'Enum#window,transparent,opaque',
                        'name'              => 'ID',
                    )
                );
                $embed->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeEmbed();

                // Add map tag
                $map = $def->addElement(
                    'map',   // name
                    'Block',  // content set
                    'Flow', // allowed children
                    'Common', // attribute collection
                    array( // attributes
                        'name' => 'CDATA',
                        'id' => 'ID',
                        'title' => 'CDATA',
                    )
                );
                $map->excludes = array('map' => true);

                // Add area tag
                $area = $def->addElement(
                    'area',   // name
                    'Block',  // content set
                    'Empty', // don't allow children
                    'Common', // attribute collection
                    array( // attributes
                        'name' => 'CDATA',
                        'id' => 'ID',
                        'alt' => 'Text',
                        'coords' => 'CDATA',
                        'accesskey' => 'Character',
                        'nohref' => new HTMLPurifier_AttrDef_Enum(array('nohref')),
                        'href' => 'URI',
                        'shape' => new HTMLPurifier_AttrDef_Enum(array('rect','circle','poly','default')),
                        'tabindex' => 'Number',
                        'target' => new HTMLPurifier_AttrDef_Enum(array('_blank','_self','_target','_top'))
                    )
                );
                $area->excludes = array('area' => true);

                $object = $def->addElement(
                    'object',
                    'Inline',
                    'Optional: param | Flow | #PCDATA',
                    'Common',
                    array(
                        // While technically not required by the spec, we're forcing
                        // it to this value.
                        'type'    => 'ContentType',
                        'width'   => 'Pixels#' . $max,
                        'height'  => 'Pixels#' . $max,
                        'data'    => 'URI#embedded',
                        'classid' => 'Text',
                    )
                );
                $object->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeObject();

                $param = $def->addElement(
                    'param', false, 'Empty', false,
                    array(
                        'id'    => 'ID',
                        'name*' => 'Text',
                        'value' => 'Text',
                    )
                );
                $param->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeParam();
                $def->info_injector[] = new Alaress_HTMLPurifier_Injector_SafeObject();
            }

            self::$htmlpurifier_instance = new HTMLPurifier($config);

            /* HTML Purifier hack to prevent authentication information from being stripped out of URL */
            $registry = HTMLPurifier_URISchemeRegistry::instance();
            $registry->register('http', new Alaress_HTMLPurifier_URIScheme_Http);
        }
        return self::$htmlpurifier_instance;
    }

    /**
     * Mangles HTML-purified DOM - this is needed for things that HTMLPurifier
     * can't do, such as adding new nodes.
     *
     * Currently:
     * * adds "allowfullscreen=true" to iframes
     *
     * @param string $html
     * @return string
     */
    static protected function htmlDomModify($html)
    {
        // Don't bother parsing anything if $html is empty
        if (strlen($html) === 0) {
            return $html;
        }

        // Now try loading into a document
        $domDocument = new DOMDocument();
        // Silence noisy DomDocument errors
        $xmlErrors = libxml_use_internal_errors(true);
        if ($domDocument->loadHTML('<?xml encoding="UTF-8">'.$html)) {
            $touched = false;

            // Mangle iframes: add allowfullscreen, and wrap in div
            foreach ($domDocument->getElementsByTagName('iframe') as $iframe) {
                /** @var DOMElement $iframe */
                $touched = true;
                $iframe->setAttribute('allowfullscreen', 'true');
            }

            // I only want the fragment content, but saveHTML loads doc into a
            // full HTML document with doctype, body, etc.
            if ($touched) {
                /** @var DOMElement $body */
                $body = $domDocument->getElementsByTagName('body')->item(0);
                if ($body) {
                    // I don't want any attributes interfering with the regexp
                    foreach ($body->attributes as $attribute) {
                        $body->removeAttribute($attribute->name);
                    }
                    // And get rid of the actual body tag
                    $html = preg_replace(
                        ['|^<body\\s*>|i', '|</body\\s*>$|i'],
                        '',
                        $domDocument->saveHTML($body)
                    );
                }
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($xmlErrors);
        return $html;
    }

    /**
     * Cleans crap out of input using HTMLPurifier.
     * Now caches previous calls in Redis!
     *
     * @param  string $html
     *
     * @return mixed
     */
    static public function html($html)
    {
        return Alaress\Redis::getInstance()->cacheFunctionCall(
            'scrub.html',
            function ($param) {
                return self::htmlDomModify(self::getHtmlPurifierInstance()->purify($param));
            },
            "$html",
            Config::get('scrub_html_cache_timeout')
        );
    }

    /**
     * Variant of html
     * @param string $html
     * @param string $domain
     * @return string
     */
    static public function htmlAbsoluteUri(string $html, string $domain): string
    {
        return Alaress\Redis::getInstance()->cacheFunctionCall(
            'scrub.htmlAbsoluteUri',
            function ($params) {
                $purifier = self::getHtmlPurifierInstance($params[1]);
                return self::htmlDomModify($purifier->purify($params[0]));
            },
            ["$html", $domain],
            Config::get('scrub_html_cache_timeout')
        );
    }
}
