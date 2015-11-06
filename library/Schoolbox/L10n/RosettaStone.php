<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\L10n;


use ArrayObject;
use OutOfBoundsException;

class RosettaStone
{
    /** @var Term[] */
    protected $dictionary;


    /**
     * @param array|ArrayObject $dictionary
     */
    public function __construct($dictionary)
    {
        foreach ($dictionary as $term => $data) {
            $this->dictionary[$term] = new Term($data['singular'], $data['plural'], $data['capitalise']);
        }
    }


    /**
     * @param string $term
     * @param bool   $plural
     * @param bool   $capital
     * @return string
     */
    public function translate($term, $plural = false, $capital = false)
    {
        return $this->__get($term)->translate($plural, $capital);
    }


    /**
     * @return array
     */
    public function getAllTerms()
    {
        return array_keys($this->dictionary);
    }


    /**
     * This is for Twig access to translated words.
     * Example usages:
     *   {{ l10n.news }} to return 'news'
     *   {{ l10n.news.proper }} to return 'News'
     *   {{ l10n.calendar.plural }} to return 'calendars'
     *   {{ l10n.resource.properPlural }} to return 'Resources'
     *
     * @param $term string
     * @return Term
     * @link http://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
     */
    function __get($term)
    {
        if (array_key_exists($term, $this->dictionary)) {
            return $this->dictionary[$term];
        }

        throw new OutOfBoundsException(sprintf('Term "%s" not in localisation table', $term), E_USER_WARNING);

    }


}