<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\L10n;

use OutOfBoundsException;

class RosettaStone
{
    /** @var Model[] */
    protected $dictionary;

    /**
     * RosettaStone constructor.
     * @param Model[] $terms
     */
    public function __construct($terms = [])
    {
        foreach ($terms as $term) {
            $this->addTerm($term);
        }
    }

    /**
     * Adds a term to this stone's dictionary.
     *
     * @param Model $term
     * @return $this
     */
    public function addTerm(Model $term)
    {
        $this->dictionary[$term->getTerm()] = $term;
        return $this;
    }

    /**
     * @param string $term
     * @param bool   $plural
     * @param bool   $capital
     * @return string
     * @throws OutOfBoundsException
     */
    public function translate($term, $plural = false, $capital = false)
    {
        return $this->__get($term)->translate($plural, $capital);
    }

    /**
     * Gets the indefinite article for this term.
     *
     * @param string $term
     * @param bool   $capital
     * @return string
     * @throws OutOfBoundsException
     */
    public function indefiniteArticle($term, $capital = false)
    {
        return $this->__get($term)->translateIndefiniteArticle($capital);
    }

    /**
     * Attempt to translate a sentence that contains tokens.
     *
     * Tokens will be wrapped in {:token}, as they are in UPS, they may have modifiers too:
     * {|plural} and/or {|capital}.
     *
     * (Sentence tokenization is a stop-gap till we have proper i18n.)
     *
     * @param string $sentence
     * @return string
     */
    public function translateSentence($sentence)
    {
        // TODO inject tokenizer at time of construct
        $tokenizer = new Tokenizer($this);
        return $tokenizer->parseToken([], $sentence);
    }

    /**
     * @return array
     */
    public function getAllTerms()
    {
        return array_keys($this->dictionary);
    }

    /**
     * Return all terms as an array for use in the frontend
     * See @js/terminology/l10n.ts
     * @return array
     */
    public function getAllTermsArray()
    {
        return array_map(fn($term) => $this->dictionary[$term]->toArray(), $this->getAllTerms());
    }

    /**
     * This is for Twig access to translated words.
     * Example usages:
     *   {{ locale.news }} to return 'news'
     *   {{ locale.news.proper }} to return 'News'
     *   {{ locale.calendar.plural }} to return 'calendars'
     *   {{ locale.resource.properPlural }} to return 'Resources'
     *
     * @param string $term
     * @return Model
     * @throws OutOfBoundsException
     */
    public function __get($term)
    {
        if (!$this->__isset($term)) {
            throw new OutOfBoundsException(sprintf('Term "%s" not in localisation table', $term), E_USER_WARNING);
        }

        return $this->dictionary[$term];
    }

    /**
     * @param string $term
     * @return bool
     */
    public function __isset($term)
    {
        return array_key_exists($term, $this->dictionary);
    }
}
