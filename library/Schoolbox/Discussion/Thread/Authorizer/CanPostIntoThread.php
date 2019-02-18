<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Alaress\Authorizer\Authorizer as BaseAuthorizer;
use Schoolbox\Comms\News;
use Schoolbox\User;

/**
 * Authorization rule for discussion threads.
 *
 * This rule checks where the thread is in use, and verifies whether the
 * requesting user has permission to post into that place.
 */
class CanPostIntoThread extends CanActUponThread
{
    /**
     * @var BaseAuthorizer
     */
    private $baseAuthorizer;


    /**
     * Constructor
     *
     * @param BaseAuthorizer $baseAuthorizer
     * @param News\Mapper    $articleMapper
     */
    public function __construct(BaseAuthorizer $baseAuthorizer, News\Mapper $articleMapper)
    {
        parent::__construct($articleMapper);
        $this->baseAuthorizer = $baseAuthorizer;
    }


    /**
     * If I can view a news article, I can post into it, unless comments are
     * closed.
     *
     * @param  News\Model $article
     * @param  User|null  $user
     * @return bool
     */
    protected function validateAgainstCommsArticle(News\Model $article, ?User $user): bool
    {
        if (!$article->getCommentsAllowed()) {
            return false;
        }
        return $this->baseAuthorizer->isPathAuthorizedForUser(
            'commsNews.getDetail',
            ['articleId' => $article->getId(), 'article' => $article],
            $user
        );
    }
}
