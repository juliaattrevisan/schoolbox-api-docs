<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */
namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;
use TextFormatter;
use Scrub;


/**
 * Class BlurbDecorator
 *
 * Add blurb-related parts, such as the blurb itself, and any Read More link behaviours
 *
 * @package Schoolbox\Comms\News
 */
class BlurbDecorator implements IDecorator
{

    /**
     * @var string
     */
    private string $readmore;


    /**
     * @var bool
     */
    private bool $commentsAllowed;


    /**
     * @var array
     */
    private array $attachmentCounts;


    /**
     * @param string $readmore
     * @param bool   $commentsAllowed
     * @param array  $attachmentCounts
     */
    public function __construct(string $readmore, bool $commentsAllowed, array $attachmentCounts)
    {
        $this->readmore = $readmore;
        $this->commentsAllowed = $commentsAllowed;
        $this->attachmentCounts = $attachmentCounts;
    }


    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if (!$model instanceof Model) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        if (strip_tags($model->getBlurb()) === '') {
            $json['blurb'] = TextFormatter::cutSentence(Scrub::html2text($model->getBody()), 300);
        }

        /* Attachments aren't actually hydrated in $model, but we got the counts
         * for use in a metadata elsewhere, so we can reuse that fact here by
         * artificially representing the number of files without having the actual
         * files themselves with all their mimes etc.
         *
         * Alternatively, implement this pseudocode
         * $article->setAttachedFiles($this->getArticleMapper()->getJoinMapper('files')->findByColumn('article_id', $article->getId()));
         */
        if ($this->attachmentCounts[$model->getId()]) {
            $model->getAttachedFiles()->add(true);
        }

        /* Decide if a Read More link is available; it depends on characteristics
         * of the article and some admin config vars
         */
        $json['readmore'] = [
            'string' => $this->readmore,
            'available' => (new ValidHasReadMore())->isSatisfiedBy($model, $this->readmore, $this->commentsAllowed)
        ];

        return $json;
    }
}
