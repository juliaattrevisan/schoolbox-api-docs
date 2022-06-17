<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

/**
 * Extracts all the .summative-report rules from the slab of css
 * in Admin Skin Config. These are per-instance customisations
 * that schools can make on their own.
 */
class CustomStyleExtractor
{
    /**
     * This css class is in a parent element that encompasses the entire report
     * semantics. You should be making rules like
     *
     * .summative-report .introduction p { ... }
     *
     * which we can then extract with a regex. It allows schools' rules to have
     * increased specificity and thus override our existing html/css
     */
    private const STYLE_PREFIX = '.summative-report';


    /**
     * Extracts all relevant summative report styles from the config var
     * contents in Admin Skin Config. You can pass it an arbitrary slab
     * of text though, and it will do its best to find summative report
     * styles contained therein (it should be a stylesheet, not something
     * random like an obama ipsum)
     *
     * @param string $skinConfig
     * @return string
     */
    public function extract(string $skinConfig): string
    {
        /* In the given slab of css rules $skinConfig,
         * go looking for anything that follows the format
         *
         * .summative-report anything { anything }
         * .summative-report[^{]*{.*}
         */

        /* For everything that matches, pile it into a stack
         */

        /* implode it into a string
         */

        /* Return it, because then it gets rammed into the bottom of an existing inline stylesheet somewhere
         */
        return '';
    }
}