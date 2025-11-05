<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Lookup;

class Langauge
{
    /**
     * Preselect this one by default if no languages were chosen yet (ie. crafting a new dialog agent)
     */
    public const string DEFAULT = 'en-GB';


    /**
     * I arbitrarily sourced this, due to inaction
     *
     * https://learn.microsoft.com/en-us/graph/search-concept-acceptlanguage-header
     */
    public const array AVAILABLE = [
        'af' => 'Afrikaans',
        'am' => 'Amharic',
        'ar-sa' => 'Arabic (Saudi Arabia)',
        'as' => 'Assamese',
        'az-Latn' => 'Azerbaijani (Latin)',
        'be' => 'Belarusian',
        'bg' => 'Bulgarian',
        'bn-BD' => 'Bangla (Bangladesh)',
        'bn-IN' => 'Bangla (India)',
        'bs' => 'Bosnian (Latin)',
        'ca' => 'Catalan Spanish',
        'ca-ES-valencia' => 'Valencian',
        'cs' => 'Czech',
        'cy' => 'Welsh',
        'da' => 'Danish',
        'de' => 'German (Germany)',
        'de-de' => 'German (Germany)',
        'el' => 'Greek',
        'en-GB' => 'English (United Kingdom)',
        'en-US' => 'English (United States)',
        'es' => 'Spanish (Spain)',
        'es-ES' => 'Spanish (Spain)',
        'es-US' => 'Spanish (United States)',
        'es-MX' => 'Spanish (Mexico)',
        'et' => 'Estonian',
        'eu' => 'Basque',
        'fa' => 'Persian',
        'fi' => 'Finnish',
        'fil-Latn' => 'Filipino',
        'fr' => 'French (France)',
        'fr-FR' => 'French (France)',
        'fr-CA' => 'French (Canada)',
        'ga' => 'Irish',
        'gd-Latn' => 'Scottish Gaelic',
        'gl' => 'Galician',
        'gu' => 'Gujarati',
        'ha-Latn' => 'Hausa (Latin)',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hr' => 'Croatian',
        'hu' => 'Hungarian',
        'hy' => 'Armenian',
        'id' => 'Indonesian',
        'ig-Latn' => 'Igbo',
        'is' => 'Icelandic',
        'it' => 'Italian (Italy)',
        'it-it' => 'Italian (Italy)',
        'ja' => 'Japanese',
        'ka' => 'Georgian',
        'kk' => 'Kazakh',
        'km' => 'Khmer',
        'kn' => 'Kannada',
        'ko' => 'Korean',
        'kok' => 'Konkani',
        'ku-Arab' => 'Central Kurdish',
        'ky-Cyrl' => 'Kyrgyz',
        'lb' => 'Luxembourgish',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'mi-Latn' => 'Maori',
        'mk' => 'Macedonian',
        'ml' => 'Malayalam',
        'mn-Cyrl' => 'Mongolian (Cyrillic)',
        'mr' => 'Marathi',
        'ms' => 'Malay (Malaysia)',
        'mt' => 'Maltese',
        'nb' => 'Norwegian (Bokmål)',
        'ne' => 'Nepali (Nepal)',
        'nl' => 'Dutch (Netherlands)',
        'nl-BE' => 'Dutch (Netherlands)',
        'nn' => 'Norwegian (Nynorsk)',
        'nso' => 'Sesotho sa Leboa',
        'or' => 'Odia',
        'pa' => 'Punjabi (Gurmukhi)',
        'pa-Arab' => 'Punjabi (Arabic)',
        'pl' => 'Polish',
        'prs-Arab' => 'Dari',
        'pt-BR' => 'Portuguese (Brazil)',
        'pt-PT' => 'Portuguese (Portugal)',
        'qut-Latn' => 'K’iche’',
        'quz' => 'Quechua (Peru)',
        'ro' => 'Romanian (Romania)',
        'ru' => 'Russian',
        'rw' => 'Kinyarwanda',
        'sd-Arab' => 'Sindhi (Arabic)',
        'si' => 'Sinhala',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sq' => 'Albanian',
        'sr-Cyrl-BA' => 'Serbian (Cyrillic, Bosnia and Herzegovina)',
        'sr-Cyrl-RS' => 'Serbian (Cyrillic, Serbia)',
        'sr-Latn-RS' => 'Serbian (Latin, Serbia)',
        'sv' => 'Swedish (Sweden)',
        'sw' => 'Kiswahili',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'tg-Cyrl' => 'Tajik (Cyrillic)',
        'th' => 'Thai',
        'ti' => 'Tigrinya',
        'tk-Latn' => 'Turkmen (Latin)',
        'tn' => 'Setswana',
        'tr' => 'Turkish',
        'tt-Cyrl' => 'Tatar (Cyrillic)',
        'ug-Arab' => 'Uyghur',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz-Latn' => 'Uzbek (Latin)',
        'vi' => 'Vietnamese',
        'wo' => 'Wolof',
        'xh' => 'isiXhosa',
        'yo-Latn' => 'Yoruba',
        'zh-Hans' => 'Chinese (Simplified)',
        'zh-Hant' => 'Chinese (Traditional)',
        'zu' => 'isiZulu',
    ];
}
