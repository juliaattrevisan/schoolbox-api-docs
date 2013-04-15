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
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Scrub {

    /**
     * Cleans plain text of all nasty stuff
     * Can be used on input to database or output
     *
     * @param  $text
     * @return string
     */
    static public function text($text) {
        /* This in built function encode html chars
           * the ENT_QUOTES converts all talking marks
           */
        $text = htmlentities($text, ENT_QUOTES, "UTF-8");

        /* Get rid of any possible bad chars
           * $text = scrubHTML($text);
           */
        return $text;
    }

    /**
     * Turns html into plaintext.
     * Note: output will be quite unpredictable if used on input that contains tables or copious formatting.
     * @static
     * @param $html
     * @return string
     */
    static public function html2text($html) {

        $text = html_entity_decode( // 4: unconvert entities (3 -> 4 is just for normalising any rogue characters)
            htmlentities( // 3: convert all entites, including quotes, into UTF-8
                preg_replace( // 2: all recurring whitespaces (including &nbsp;) to be trimmed
                    '/(\s|&nbsp;)+/i',
                    ' ',
                    strip_tags( // 1: remove all remaining tags from input
                        preg_replace( // 0: replace all text-style block-level elements with newlines
                            '#</(p|h[1-5]|br)>#i',
                            "\n",
                            $html
                        )
                    )
                ),
                ENT_QUOTES,
                "UTF-8"
            )
        );

        return $text;
    }

    /**
     * Convert smart quotes to apostrophes/double quotes
     *
     * @static
     * @param $text
     * @return mixed
     */
    static public function smartQuotes($text) {
        $search = array(chr(145), chr(146), chr(147), chr(148), chr(151));
        $replace = array("'", "'", '"', '"', '-');

        $data = str_replace($search, $replace, $text);

        $search = array("&#8220;", "&#8221;", "&#8216;", "&#8217;", "&#8243;");
        $replace = array("&quot;", "&quot;", "&#39;", "&#39;", "&quot;");

        return str_replace($search, $replace, $data);
    }

    /**
     * Cleans crap out of input using HTMLPurifier
     *
     * @param  $html
     *
     * @return mixed
     */
    static public function html($html) {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('Cache.SerializerPath', Cfg::get('xml_dir') . '/../purifier/');

        $config->set('AutoFormat.Linkify', true);

        // Allow Iframe
        $config->set('HTML.SafeIframe', true);
        $safeIframeSources = array(
            'www.youtube.com/embed/',
            'player.vimeo.com/video/',
            'www.slideshare.net/slideshow/embed_code/',
            '(www|edu).glogster.com/glog(/|.php)',
            'wikieducator.org/',
            'www.google.com(.[a-z]{2})?/maps',
            'maps.google.com(.[a-z]{2})?/',
            'docs.google.com(.[a-z]{2})?/',
            'www.tiki-toki.com/timeline/embed/',
            'www.thinglink.com/card/',
            'www.facebook.com/plugins/',
            'padlet.com/embed/',
            'linoit.com/users/linoit-com/canvases/',
            'www.showme.com/sma/embed',

        );


        $config->set('URI.SafeIframeRegexp', '%^https?://(' . implode('|', $safeIframeSources) . ')%');

        $config->set('Output.FlashCompat', true);
        $config->set('HTML.FlashAllowFullScreen', true);


        $config->set(
            'Attr.AllowedFrameTargets', array(
                '_blank',
                '_self',
                '_parent',
                '_top'
            )
        );

        //
        // Allow embeds and objects, allowing more properties than standard
        // HTMLPurifier definition allows.
        // Include modules this way because adding modules doesn't work in a
        // logical manner.
        $config->set('HTML.DefinitionID',  'HTML + Extended Embeds + Extended Objects');
        $config->set('HTML.DefinitionRev', 1);
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $max = $config->get('HTML.MaxImgLength');

            //
            // Definition of customised elements, copied directly out of modules.
            $embed = $def->addElement(
                'embed', 'Inline', 'Empty', 'Common',
                array(
                    'src*' => 'URI#embedded',
                    'type' => 'ContentType',
                    'width' => 'Pixels#' . $max,
                    'height' => 'Pixels#' . $max,
                    'allowscriptaccess' => 'Enum#never',
                    'allownetworking' => 'Enum#internal',
                    'flashvars' => 'Text',
                    'wmode' => 'Enum#window,transparent,opaque',
                    'name' => 'ID',
                )
            );
            $embed->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeEmbed();

            $object = $def->addElement(
                'object',
                'Inline',
                'Optional: param | Flow | #PCDATA',
                'Common',
                array(
                    // While technically not required by the spec, we're forcing
                    // it to this value.
                    'type' => 'ContentType',
                    'width' => 'Pixels#' . $max,
                    'height' => 'Pixels#' . $max,
                    'data' => 'URI#embedded',
                    'classid' => 'Text',
                )
            );
            $object->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeObject();

            $param = $def->addElement(
                'param', false, 'Empty', false,
                array(
                    'id' => 'ID',
                    'name*' => 'Text',
                    'value' => 'Text',
                )
            );
            $param->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeParam();
            $def->info_injector[]         = new Alaress_HTMLPurifier_Injector_SafeObject();
        }

        $purifier = HTMLPurifier::instance();

        /* HTML Purifier hack to prevent authentication information from being stripped out of URL */
        $registry = HTMLPurifier_URISchemeRegistry::instance();
        $registry->register('http', new Alaress_HTMLPurifier_URIScheme_Http);

        return $purifier->purify($html, $config);
    }
}
