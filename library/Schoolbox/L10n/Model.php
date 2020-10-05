<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\L10n;

class Model
{
    /** @var string */
    protected $term;
    /** @var string */
    protected $title;
    /** @var string */
    protected $singularForm;
    /** @var string */
    protected $pluralForm;
    /** @var bool */
    protected $capitaliseFirst;
    /** @var string */
    protected $indefiniteArticle;

    /**
     * @param string $value
     * @return self
     */
    public function setTerm($value)
    {
        $this->term = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getTerm()
    {
        return $this->term;
    }

    /**
     * @param string $value
     * @return self
     */
    public function setTitle($value)
    {
        $this->title = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /**
     * @param string $value
     * @return self
     */
    public function setSingularForm($value)
    {
        $this->singularForm = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getSingularForm()
    {
        return $this->singularForm;
    }


    /**
     * @param string $value
     * @return self
     */
    public function setPluralForm($value)
    {
        $this->pluralForm = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getPluralForm()
    {
        return $this->pluralForm;
    }

    /**
     * @param bool $value
     * @return self
     */
    public function setCapitaliseFirst($value)
    {
        $this->capitaliseFirst = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCapitaliseFirst()
    {
        return $this->capitaliseFirst;
    }

    /**
     * @return string
     */
    public function getIndefiniteArticle()
    {
        return $this->indefiniteArticle;
    }

    /**
     * @param string $indefiniteArticle
     * @return static
     */
    public function setIndefiniteArticle($indefiniteArticle)
    {
        $this->indefiniteArticle = $indefiniteArticle;
        return $this;
    }

    // Helper function to pluralise/capitalise the term as required

    /**
     * @param bool $plural
     * @param bool $capital
     * @return string
     */
    public function translate($plural = false, $capital = false)
    {
        $local = ($plural ? $this->pluralForm : $this->singularForm);

        if ($capital) {
            $local = ucwords($local);
            if (!$this->capitaliseFirst) {
                $local = lcfirst($local);
            }
        }

        return $local;
    }

    /**
     * Helper for translating an indefinite article.
     *
     * @param bool $capital
     * @return string
     */
    public function translateIndefiniteArticle($capital = false)
    {
        $local = $this->getIndefiniteArticle();
        if ($capital) {
            $local = ucwords($local);
        }
        return $local;
    }

    // Twig sugar for translate

    /**
     * Default behaviour is singular, lowercase
     * @return string
     */
    public function __toString()
    {
        return $this->translate(false, false);
    }

    /**
     * @return string
     */
    public function proper()
    {
        return $this->translate(false, true);
    }

    /**
     * @return string
     */
    public function plural()
    {
        return $this->translate(true, false);
    }

    /**
     * @return string
     */
    public function properPlural()
    {
        return $this->translate(true, true);
    }


    /**
     * Convenience method for fetching all forms.
     *
     * @return array
     */
    public function allForms(): array
    {
        return [
            'singular'       => $this->translate(false, false),
            'plural'         => $this->translate(true, false),
            'properSingular' => $this->translate(false, true),
            'properPlural'   => $this->translate(true, true),
        ];
    }
}
