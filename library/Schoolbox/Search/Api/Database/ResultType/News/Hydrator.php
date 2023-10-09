<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress\Html\ToText;
use Aura\Router\Generator;
use Schoolbox\Comms\News;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * Hydrate a set of raw news results into a set of full news results.
 *
 * Restrictions on displaying news results:
 * - any article a user may view may display in the user's search results:
 *   an article's viewability is defined in
 *   `\Schoolbox\Comms\News\Audience\ArticleResolver::findArticlesForUser`
 */
class Hydrator extends BaseHydrator
{
    /**
     * This hydrator's base type.
     */
    public const BASE_TYPE = 'news';

    /**
     * Constructor
     *
     * @param News\Mapper                   $newsMapper      Used for fetching news for hydration.
     * @param Generator                     $routeGenerator  Used for building routes to news articles.
     * @param News\Audience\ArticleResolver $articleResolver Used for determining user access to news articles.
     */
    public function __construct(
        private News\Mapper $newsMapper,
        private Generator $routeGenerator,
        private News\Audience\ArticleResolver $articleResolver,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $articleIds      = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($articleIds)) {
            return [];
        }

        // Filter out articles depending on the viewer's attributes:
        // * superusers view all articles
        // * users with the news service permission view all articles
        $filterArticleIds = true;
        if ($viewer->isSuperuser()) {
            $filterArticleIds = false;
        } else {
            $viewerServices = $viewer->getAccessList();
            foreach ($viewerServices as $service) {
                if ($service->getService() === UserAccess::SERVICE_NEWS) {
                    $filterArticleIds = false;
                    break;
                }
            }
        }

        if ($filterArticleIds) {
            // Filter out articles which the user doesn't have permission to read
            $newsFeed            = new News\Lists\UserFeed($this->newsMapper, $this->articleResolver);
            $permittedArticleIds = $newsFeed->find($viewer, News\Model::STATE_PUBLIC, false);

            // In addition: find any articles which are in a folder the user has
            // permission to read, via having read permissions via their role
            $permittedArticleIds = array_values(
                array_unique(
                    array_merge(
                        $permittedArticleIds,
                        $this->newsMapper->findArticleIdsForRole($viewer->getRole(), $articleIds),
                    )
                )
            );

            if (empty($permittedArticleIds)) {
                return [];
            }

            $articleIds = array_intersect($articleIds, $permittedArticleIds);
        }

        // Find the given articles
        $articles = $this->newsMapper->findByColumn('id', $articleIds);

        // And map them to a full result
        $fullResults = array_map(
            function (RawResult $match) use ($articles, $keyword) {
                $article = $articles->getBy('id', $match->id);
                if ($article instanceof News\Model) {
                    // The article may have been found if the keyword matched a
                    // HTML attribute or its value in the blurb or body.
                    // Filter out matches which were found to match the keyword
                    // on the query, but were found to not match either:
                    // * the title
                    // * the generated description
                    $description = $this->generateDescriptionForArticle($keyword, $article);
                    if (!isset($description) && !str_contains($match->title, $keyword)) {
                        return null;
                    }
                    return new FullResult(
                        $match,
                        $match->id,
                        $match->title,
                        $this->buildBaseUrl()
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
                        $article->getPublishAt(),
                        null,
                        "Posted {$article->getPublishAt()->getRelativeTime()} by {$article->getAuthor()->getFullName()}",
                        $article->getFeatureImage()
                            ? $this->buildStorageImageUrl($article->getFeatureImage())->__toString()
                            : null,
                        'news',
                        $description,
                        $article->getAuthor(),
                        [],
                        [
                            [
                                'title' => 'Save For Later',
                                'icon' => 'icon-news',
                                'url' => $this->buildBaseUrl()
                                    ->withPath("/news/saved/{$article->getId()}/add")
                                    ->__toString(),
                            ],
                            [
                                'title' => 'Statistics',
                                'icon' => 'icon-progress',
                                'url' => $this->buildBaseUrl()
                                    ->withPath("/news/statistics/{$article->getId()}")
                                    ->__toString(),
                            ]
                        ]
                    );
                }
                return null;
            },
            $matches,
        );
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * Generate a description for the given article, by finding the text near
     * the keyword in:
     * * the blurb first, if there is one
     * * the body otherwise
     *
     * @param string     $keyword The search keyword.
     * @param News\Model $article The article to generate a description for.
     * @return string|null
     */
    private function generateDescriptionForArticle(string $keyword, News\Model $article): ?string
    {
        // Find keyword in blurb, then body
        if ($article->getBlurb()) {
            $blurbText = (new ToText($article->getBlurb()))->getText();
            $truncatedBlurb = $this->truncateAroundKeyword($keyword, $blurbText) ?? $this->truncate($blurbText);
            if ($truncatedBlurb) {
                return $truncatedBlurb;
            }
        }
        if ($article->getBody()) {
            $bodyText = (new ToText($article->getBody()))->getText();
            return $this->truncateAroundKeyword($keyword, $bodyText) ?? $this->truncate($bodyText);
        }

        return null;
    }
}
