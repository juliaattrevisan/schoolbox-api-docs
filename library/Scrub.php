<?php

use GuzzleHttp\Client;
use League\CommonMark;
use Proxy\Server as ProxyServer;
use Schoolbox\Iframely\EmbedReplacer;

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
     * Singleton CommonMark instance used by this class in `linkifyText`.
     *
     * @var CommonMark\MarkdownConverterInterface|null
     */
    private static ?CommonMark\MarkdownConverterInterface $linkifyConverter = null;


    /**
     * Singleton CommonMark instance used by this class in `markdown`.
     *
     * @var CommonMark\MarkdownConverterInterface|null
     */
    private static ?CommonMark\MarkdownConverterInterface $markdownConverter = null;


    /**
     * Singleton Iframely embed replacer used by this class in `html` and
     * `htmlAbsoluteUri`.
     */
    private static ?EmbedReplacer $embedReplacer = null;


    /**
     * Cleans plain text of all nasty stuff
     * Can be used on input to database or output
     *
     * @param string $text
     * @return string
     */
    public static function text($text)
    {
        //
        // Don't think ENT_IGNORE is a problem here.
        // This is used to escape output as text or as an attribute - HTML tags
        // are escaped regardless.
        return htmlentities($text, ENT_IGNORE | ENT_QUOTES, 'UTF-8');
    }


    /**
     * Scrubs text for XML output.
     *
     * @param string $text
     * @return string
     */
    public static function xml($text)
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
     * @param string $html
     * @return string
     */
    public static function html2text($html)
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
     * @param string $text
     * @return string
     */
    public static function smartQuotes($text)
    {
        $search  = [chr(145), chr(146), chr(147), chr(148), chr(151)];
        $replace = ["'", "'", '"', '"', '-'];

        $data = str_replace($search, $replace, $text);

        $search  = ['&#8220;', '&#8221;', '&#8216;', '&#8217;', '&#8243;'];
        $replace = ['&quot;', '&quot;', '&#39;', '&#39;', '&quot;'];

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
            $serializerPath = Config::get('purifier_cache_dir');
            if (!is_dir($serializerPath)) {
                // Don't need to care if this failed,
                // because HTMLPurifier will fail later if it did.
                /** @noinspection MkdirRaceConditionInspection */
                mkdir($serializerPath, 0775, true);
            }

            $config = HTMLPurifier_Config::createDefault();

            /* http://htmlpurifier.org/phorum/read.php?3,6724
             */
            $config->set('CSS.AllowTricky', true);
            // Trusted style properties are:
            // position, top/left/right/bottom, z-index
            $config->set('CSS.Trusted', true);

            $config->set('Cache.SerializerPath', $serializerPath);
            $config->set('AutoFormat.Linkify', true);

            // Allow Iframe
            $config->set('HTML.SafeIframe', true);
            $safeIframeSources = [
                'www.youtube.com/',
                'www.youtube-nocookie.com/embed/',
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
                'web.microsoftstream.com/embed/',
                'onedrive.live.com/embed/',
                'sway.com/',
                'flipgrid.com/',
                'spark.adobe.com/',
            ];

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
                'Attr.AllowedFrameTargets',
                [
                    '_blank',
                    '_self',
                    '_parent',
                    '_top',
                ]
            );

            // Don't allow classes that affect layout in Foundation
            $config->set('Attr.ForbiddenClasses', ['row', 'column', 'island',]);

            $config->set(
                'URI.AllowedSchemes',
                [
                    'http'    => true,
                    'https'   => true,
                    'mailto'  => true,
                    'ftp'     => true,
                    'ed2k'    => true,
                    'magnet'  => true,
                    'feed'    => true,
                    'dvcplay' => true,
                    'data'    => true,
                    'tel'     => true,
                    'webcal'  => true,
                ]
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
                $def->addAttribute('iframe', 'allow', 'Text');
                $def->addAttribute('iframe', 'allowfullscreen', 'Bool');


                //
                // Definition of customised elements, copied directly out of modules.
                $embed = $def->addElement(
                    'embed',
                    'Inline',
                    'Empty',
                    'Common',
                    [
                        'src*'              => 'URI#embedded',
                        'type'              => 'ContentType',
                        'width'             => 'Pixels#' . $max,
                        'height'            => 'Pixels#' . $max,
                        'allowscriptaccess' => 'Enum#never',
                        'allownetworking'   => 'Enum#internal',
                        'flashvars'         => 'Text',
                        'wmode'             => 'Enum#window,transparent,opaque',
                        'name'              => 'ID',
                    ]
                );
                $embed->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeEmbed();

                // Add map tag
                $map = $def->addElement(
                    'map',   // name
                    'Block',  // content set
                    'Flow', // allowed children
                    'Common', // attribute collection
                    [
                        // attributes
                        'name'  => 'CDATA',
                        'id'    => 'ID',
                        'title' => 'CDATA',
                    ]
                );
                $map->excludes = ['map' => true];

                // Add area tag
                $area = $def->addElement(
                    'area',   // name
                    'Block',  // content set
                    'Empty', // don't allow children
                    'Common', // attribute collection
                    [
                        // attributes
                        'name'      => 'CDATA',
                        'id'        => 'ID',
                        'alt'       => 'Text',
                        'coords'    => 'CDATA',
                        'accesskey' => 'Character',
                        'nohref'    => new HTMLPurifier_AttrDef_Enum(['nohref']),
                        'href'      => 'URI',
                        'shape'     => new HTMLPurifier_AttrDef_Enum(['rect', 'circle', 'poly', 'default']),
                        'tabindex'  => 'Number',
                        'target'    => new HTMLPurifier_AttrDef_Enum(['_blank', '_self', '_target', '_top']),
                    ]
                );
                $area->excludes = ['area' => true];

                $object = $def->addElement(
                    'object',
                    'Inline',
                    'Optional: param | Flow | #PCDATA',
                    'Common',
                    [
                        // While technically not required by the spec, we're forcing
                        // it to this value.
                        'type'    => 'ContentType',
                        'width'   => 'Pixels#' . $max,
                        'height'  => 'Pixels#' . $max,
                        'data'    => 'URI#embedded',
                        'classid' => 'Text',
                    ]
                );
                $object->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeObject();

                $param = $def->addElement(
                    'param',
                    false,
                    'Empty',
                    false,
                    [
                        'id'    => 'ID',
                        'name*' => 'Text',
                        'value' => 'Text',
                    ]
                );
                $param->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeParam();
                $def->info_injector[] = new Alaress_HTMLPurifier_Injector_SafeObject();
            }

            self::$htmlpurifier_instance = new HTMLPurifier($config);

            /* HTML Purifier hack to prevent authentication information from being stripped out of URL */
            $registry = HTMLPurifier_URISchemeRegistry::instance();
            $registry->register('http', new Alaress_HTMLPurifier_URIScheme_Http());
        }
        return self::$htmlpurifier_instance;
    }


    /**
     * Create a cut-down Markdown converter which does little more than parse
     * links out of text.
     *
     * @return CommonMark\MarkdownConverterInterface
     */
    private static function getLinkifyingMarkdownConverter(): CommonMark\MarkdownConverterInterface
    {
        if (!self::$linkifyConverter instanceof CommonMark\MarkdownConverterInterface) {
            $environment = new CommonMark\Environment\Environment(
                [
                    'html_input'         => CommonMark\Util\HtmlFilter::STRIP,
                    'allow_unsafe_links' => false,
                    'max_nesting_level'  => 10,
                ],
            );
            // Need to add InlinesOnly, as adding only Autolink causes an error
            $environment->addExtension(new CommonMark\Extension\InlinesOnly\InlinesOnlyExtension());
            $environment->addExtension(new CommonMark\Extension\Autolink\AutolinkExtension());
            self::$linkifyConverter = new CommonMark\MarkdownConverter($environment);
        }
        return self::$linkifyConverter;
    }


    /**
     * Escapes and then linkifies (adds anchor tags to text that looks like a
     * link) to text.
     *
     * @param string $text
     * @return string
     */
    public static function linkifyText(string $text): string
    {
        return self::getLinkifyingMarkdownConverter()->convertToHtml($text);
    }


    /**
     * Create a cut-down Markdown converter which does little more than parse
     * links out of text.
     *
     * @return CommonMark\MarkdownConverterInterface
     */
    private static function getMarkdownConverter(): CommonMark\MarkdownConverterInterface
    {
        if (!self::$markdownConverter instanceof CommonMark\MarkdownConverterInterface) {
            $environment = new CommonMark\Environment\Environment(
                [
                    'html_input'         => CommonMark\Util\HtmlFilter::STRIP,
                    'allow_unsafe_links' => false,
                    'max_nesting_level'  => 10,
                ]
            );
            $environment->addExtension(new CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
            $environment->addExtension(new CommonMark\Extension\Autolink\AutolinkExtension());
            $environment->addExtension(new CommonMark\Extension\Strikethrough\StrikethroughExtension());
            self::$markdownConverter = new CommonMark\MarkdownConverter($environment);
        }
        return self::$markdownConverter;
    }


    /**
     * Supports markdown
     *
     * @param string|null $text
     * @return string
     */
    public static function markdown(?string $text): string
    {
        return self::getMarkdownConverter()->convertToHtml((string)$text)->getContent();
    }


    /**
     * Cleans crap out of input using HTMLPurifier.
     * Now caches previous calls in Redis!
     *
     * @param  string $html
     * @return mixed
     */
    public static function html(string $html)
    {
        return Alaress\Redis::getCacheInstance()->cacheFunctionCall(
            'scrub.html',
            function ($param) {
                $purified = self::getHtmlPurifierInstance()->purify($param);
                return self::buildEmbedReplacer()->replace($purified);
            },
            $html,
            Config::get('scrub_html_cache_timeout')
        );
    }


    /**
     * Variant of html
     *
     * @param string $html
     * @param string $domain
     * @return string
     */
    public static function htmlAbsoluteUri(string $html, string $domain): string
    {
        return Alaress\Redis::getCacheInstance()->cacheFunctionCall(
            'scrub.htmlAbsoluteUri',
            function ($params) {
                $purifier = self::getHtmlPurifierInstance($params[1]);
                $purified = $purifier->purify($params[0]);
                return self::buildEmbedReplacer()->replace($purified);
            },
            [$html, $domain],
            Config::get('scrub_html_cache_timeout')
        );
    }


    /**
     * Strips unsafe characters from a file name.
     *
     * Excludes non-ASCII alphanumeric digits, "-" and "_": replaces with "_".
     * (Note this excludes "." as well: this is not suited for including
     *  file extensions.)
     *
     * @param string $filename
     * @return string
     */
    public static function filename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z\d\-_]/', '_', $filename);
    }

    /**
     * Build the Iframely embed replacer.
     *
     * @return EmbedReplacer
     */
    private static function buildEmbedReplacer(): EmbedReplacer
    {
        if (!self::$embedReplacer) {
            // This should be injected, but since Scrub uses static methods
            // I'd have to rewrite the whole thing to not be a static class.
            // For now: just create a concrete HTTP client.
            $httpClient = new Client(['curl' => ProxyServer::getInstance()->buildCurlOptions()]);
            self::$embedReplacer = new EmbedReplacer($httpClient);
        }
        return self::$embedReplacer;
    }
}
