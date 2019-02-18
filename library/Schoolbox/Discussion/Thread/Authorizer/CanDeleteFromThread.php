<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Alaress\Authorizer\Authorizer as BaseAuthorizer;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Discussion\Thread\Model;
use Schoolbox\Comms\News;
use Schoolbox\User;

/**
 * Authorization rule for discussion threads.
 *
 * This rule checks where the thread is in use, and verifies whether the
 * requesting user has permission to delete content from that place.
 */
class CanDeleteFromThread implements IAuthorizationRule
{
    /**
     * @var BaseAuthorizer
     */
    private $baseAuthorizer;


    /**
     * @var News\Mapper
     */
    private $articleMapper;


    /**
     * Constructor
     *
     * @param BaseAuthorizer $baseAuthorizer
     * @param News\Mapper    $articleMapper
     */
    public function __construct(BaseAuthorizer $baseAuthorizer, News\Mapper $articleMapper)
    {
        $this->baseAuthorizer = $baseAuthorizer;
        $this->articleMapper  = $articleMapper;
    }


    /**
     * @param array     $params
     * @param User|null $user
     */
    public function authorize(array $params = [], User $user = null)
    {
        // Extract thread from params
        $thread = $params['thread'] ?? null;
        if (!$thread instanceof Model) {
            throw new \InvalidArgumentException(sprintf('Thread param not an instance of "%s"', Model::class));
        }

        // Search for the thread in all the places it could be.
        // Right now: that's only comms.
        // (A thread should only be used in one place, but it's possible to
        //  check multiples.)
        $articles = $this->articleMapper->findByColumn('discussion_thread_id', $thread->getId());
        foreach ($articles as $article) {
            try {
                // Return if user is authorized to view article
                $this->baseAuthorizer->authorizePathForUser(
                    'commsNews.postDelete',
                    ['articleId' => $article->getId(), 'article' => $article],
                    $user
                );
                return;
            } catch (Failure\Base $e) {
                // Permission denied: try the next article
                continue;
            }
        }

        throw new Failure\Unauthorized();
    }
}
