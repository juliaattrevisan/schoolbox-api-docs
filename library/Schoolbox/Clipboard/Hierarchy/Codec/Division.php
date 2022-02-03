<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy\Codec;

/**
 * Combines Clipboard's Category and Subcategory nodes into
 * the schoolbox Division equivalent
 */
class Division
{

    /**
     * Combine two disparate data points into one
     *
     * There's no reliable decoding since category/subcategory strings
     * could contain separators and this Division data is user-facing
     *
     * @param string $category
     * @param string $subcategory
     * @param string $activity
     * @return string
     */
    public function encode(string $category, string $subcategory, string $activity): string
    {
        /* Optional Category and Optional Subcategory
         *
         * TODO: Figure out what the default should be (or if this is even allowed)
         *  because the api docs suggest both category and subcategory are optional
         */
        if ('' === $category && '' === $subcategory) {
            return $activity;
        }

        /* Optional Category; use Subcategory alone
         */
        if ('' === $category) {
            return $subcategory;
        }

        /* Optional Subcategory; use Category alone
         */
        if ('' === $subcategory) {
            return $category;
        }

        /* Expected usage: both Category and Subcategory exist
         * Concat the two data
         */
        return sprintf('%s-%s', $category, $subcategory);
    }
}
