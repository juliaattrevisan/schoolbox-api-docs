<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress\Html\ToText;
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
     * Maximum length of a description as returned by this hydrator.
     */
    private const DESCRIPTION_LENGTH = 300;

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
    public function accepts(RawResult $match): bool
    {
        return $match->type === 'news';
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $articleIds      = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($articleIds)) {
            return [];
        }

        // Filter out articles which the user doesn't have permission to read
        $permittedArticleIds = $this->articleResolver->findArticlesForUser($viewer, News\Model::STATE_PUBLIC, false);
        if (empty($permittedArticleIds)) {
            return [];
        }

        // Find the given articles
        $articles = $this->newsMapper->findByColumn('id', array_intersect($articleIds, $permittedArticleIds));

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
                        // Should probably be a frontend responsibility?
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
                        $description,
                        $article->getAuthor(),
                        // Links: leave blank for now
                        // TODO fill in context
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
            $truncatedBlurb = $this->truncateAroundKeyword(
                $keyword,
                (new ToText($article->getBlurb()))->getText(),
                self::DESCRIPTION_LENGTH,
            );
            if ($truncatedBlurb) {
                return $truncatedBlurb;
            }
        }
        if ($article->getBody()) {
            return $this->truncateAroundKeyword(
                $keyword,
                (new ToText($article->getBody()))->getText(),
                self::DESCRIPTION_LENGTH,
            );
        }

        return null;
    }

    /**
     * Truncates `$content` around the first occurrence of `$keyword`, to a max
     * of `$maxChars / 2` characters either side.
     *
     * If this truncation slices characters off the start/end of `$content`,
     * show truncation marks (...) before/after the truncated content.
     *
     * @param string $keyword  The search keyword.
     * @param string $content  The content to truncate.
     * @param int    $maxChars The max character count to include in the
     *                         description: allows for an equal number of
     *                         characters before and after the keyword.
     *
     * @return string|null
     */
    private function truncateAroundKeyword(string $keyword, string $content, int $maxChars): ?string
    {
        // As always, encoding is UTF-8
        $encoding = 'UTF-8';

        // Is $keyword there? Don't truncate anything if it's not
        $strpos = mb_stripos($content, $keyword, 0, $encoding);
        if ($strpos !== false) {
            // How many chars before and after the keyword match?
            $charsBefore = (int)($maxChars / 2);
            $charsAfter  = $maxChars - $charsBefore;

            // Determine whether to add truncation marks (...)
            $keywordStrlen = mb_strlen($keyword, $encoding);
            $contentStrlen = mb_strlen($content, $encoding);

            // Show marks before if the first character is after the start
            $cutStart       = $strpos - $charsBefore;
            $startTruncated = true;
            if ($cutStart < 0) {
                // Reduce `$maxChars` so that no more than half of `$maxChars`
                // are after the keyword
                $maxChars       += $cutStart;
                $cutStart       = 0;
                $startTruncated = false;
            }

            // Show marks after if the last character is before the end
            $finishTruncated = true;
            $cutFinish       = $strpos + $keywordStrlen + $charsAfter;
            if ($cutFinish > $contentStrlen) {
                $finishTruncated = false;
            }

            // And truncate
            $truncated = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    mb_strcut($content, $cutStart, $maxChars, $encoding),
                )
            );
            if ($startTruncated) {
                $truncated = '...' . $truncated;
            }
            if ($finishTruncated) {
                $truncated = $truncated . '...';
            }
            return $truncated;
        }
        return null;
    }
}
