<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress\Image\Transformation\Storage;
use Aura\Router\Generator;
use Schoolbox\Comms\News;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\Hydrator\IHydrator;
use Schoolbox\Url\Uri;
use Schoolbox\User;

/**
 * Hydrate a set of raw news results into a set of full news results.
 *
 * Restrictions on displaying news results:
 * - any article a user may view may display in the user's search results:
 *   an article's viewability is defined in
 *   `\Schoolbox\Comms\News\Audience\ArticleResolver::findArticlesForUser`
 */
class Hydrator implements IHydrator
{
    /**
     * Constructor
     *
     * @param News\Mapper $newsMapper     Used for fetching news for hydration.
     * @param Generator   $routeGenerator Used for building routes to news articles.
     */
    public function __construct(
        private News\Mapper $newsMapper,
        private Generator $routeGenerator,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function accepts(RawResult $match): bool
    {
        return $match->type === 'news';
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $articles        = $this->newsMapper->findByColumn(
            'id',
            array_map(
                fn(RawResult $match) => $match->id,
                $acceptedMatches,
            )
        );

        // And map them to an actual article
        $fullResults = array_map(
            function (RawResult $match) use ($articles) {
                $article = $articles->getBy('id', $match->id);
                if ($article instanceof News\Model) {
                    return new FullResult(
                        $match->id,
                        $match->title,
                        (new Uri())
                            ->withPath(
                                $this->routeGenerator->generate(
                                    'commsNews.getDetail',
                                    ['articleId' => $article->getId()],
                                )
                            )
                            ->withQuery(http_build_query(['ref' => 'search']))
                            ->__toString(),
                        $match->type,
                        $match->relevance,
                        $match->date,
                        // Meta looks like: x days ago in $category
                        // TODO fill in metadata
                        null,
                        $article->getFeatureImage()
                            ? (new Uri())
                                ->withPath('/storage/image.php')
                                ->withQuery(
                                    http_build_query([
                                        'hash' => $article->getFeatureImage()->getHash(),
                                        'size' => Storage::TRANSFORM_SQUARE64,
                                    ])
                                )->__toString()
                            : null,
                        'news',
                        // Description will be a truncated form of the article:
                        // if there's a match in the content then somewhere
                        // around that
                        // TODO fill in description
                        null,
                        $article->getAuthor(),
                        // Context and links: leave blank for now
                    );
                }
                return null;
            },
            $matches,
        );
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
