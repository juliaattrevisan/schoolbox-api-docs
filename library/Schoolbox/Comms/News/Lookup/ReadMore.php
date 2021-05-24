<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Lookup;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\ValidHasReadMore;

/**
 * Class ReadMore
 *
 * A lookup for a set of articles; knows when an article should present a Read More
 * link to the user based on the contents of the article and some config settings
 *
 * @package Schoolbox\Comms\News\Lookup
 */
class ReadMore
{

    /**
     * @var string
     */
    private string $readMoreText;


    /**
     * @var bool
     */
    private bool $commentsIsEnabled;


    /**
     * ReadMore constructor.
     *
     * @param string $readMoreText
     * @param bool   $commentsIsEnabled
     */
    public function __construct(string $readMoreText, bool $commentsIsEnabled)
    {
        $this->readMoreText = $readMoreText;
        $this->commentsIsEnabled = $commentsIsEnabled;
    }


    /**
     * Build a lookup of Read More links for each article id, which aren't json but
     * Model instances that don't comprehend the Read More status like this
     *
     * Generates Read More strings for a set of articles
     *
     * @param Collection $articles
     * @return array
     */
    public function generate(Collection $articles): array
    {
        $readMores = [];

        foreach ($articles as $article) {
            $readMores[$article->getId()] = [
                'string' => $this->readMoreText,
                'available' => (new ValidHasReadMore())->isSatisfiedBy($article, $this->readMoreText, $this->commentsIsEnabled)
            ];
        }

        return $readMores;
    }


    /**
     * Get the array representation for a specific article
     *
     * @param Model $article
     * @return array
     */
    public function generateForArticle(Model $article): array
    {
        $readMores = $this->generate((new Collection())->add($article));

        return $readMores[$article->getId()] ?? [
            'string' => '',
            'available' => false,
        ];
    }
}
