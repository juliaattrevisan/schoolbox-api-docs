<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Psr\Log\LoggerInterface;
use Schoolbox\Search\Index;

/**
 * Base index builder.
 */
abstract class BaseBuilder implements IBuilder
{
    /**
     * Regular expression for normalising space characters: sequences of space
     * characters are replaced with actual spaces.
     */
    private const NORMALISE_SPACE_REGEXP = '/\s+/';

    /**
     * Attempt to separate indices in their field, so that searching across
     * multiple conjoined indices does not happen.
     */
    private const INDEX_SEPARATOR = ' | ';

    /**
     * Strategy for adding an index from HTML content.
     */
    protected const INDEX_STRATEGY_HTML = 'html';

    /**
     * Strategy for adding an index from standard text content.
     */
    protected const INDEX_STRATEGY_TEXT = 'text';

    /**
     * Maximum length of the fulltext index.
     *
     * Note that text fields store a maximum number of bytes, not characters.
     */
    private const MAX_FULLTEXT_BYTE_LENGTH = 2 ** 24;

    /**
     * Constructor
     *
     * @param IConnector      $connector Database connection
     * @param LoggerInterface $logger    Logger
     */
    public function __construct(protected IConnector $connector, protected LoggerInterface $logger)
    {
    }

    /**
     * Helper function for building an index object.
     *
     * @param string      $identifier
     * @param string|null $title
     * @param string      $type
     * @param string|null $dateString
     * @param bool        $isRelevanceDateAffected
     * @param int|null    $createdBy
     * @param int|null    $folderId
     * @return Index\Model
     */
    protected function buildIndex(
        string $identifier,
        ?string $title,
        string $type,
        ?string $dateString,
        bool $isRelevanceDateAffected,
        ?int $createdBy,
        ?int $folderId,
    ): Index\Model {
        return (new Index\Model())
            ->setIdentifier($identifier)
            ->setTitle($title ?? '')
            ->setType($type)
            ->setDate(isset($dateString) ? Alaress_Date_Format::newDatabaseDate($dateString) : null)
            ->setIsRelevanceDateAffected($isRelevanceDateAffected)
            ->setCreatedBy($createdBy)
            ->setFolderId($folderId)
            ->setFulltextIndex('');
    }

    /**
     * Helper function for adding fulltext content to the index.
     *
     * This content will be searchable with a
     * `MATCH (column) AGAINST (:keyword)` query.
     *
     * @param Index\Model            $index     The index to add the content to.
     * @param string|null            $content   The content to add to the index.
     * @param self::INDEX_STRATEGY_* $strategy  The strategy to normalise
     *                                          `$content` with.
     * @return Index\Model
     */
    protected function addFulltextIndex(Index\Model $index, ?string $content, string $strategy): Index\Model
    {
        if (!isset($content)) {
            return $index;
        }
        $content = $this->normaliseContent($content, $strategy);
        if ($content === '') {
            return $index;
        }

        $currentContent = $index->getFulltextIndex();
        $content        = $currentContent === ''
            ? $content
            : ($currentContent . self::INDEX_SEPARATOR . $content);

        if (strlen($content) > self::MAX_FULLTEXT_BYTE_LENGTH) {
            $this->logger->notice(
                sprintf(
                    'Full text index for "%s:%s" would exceed max of %d bytes: not adding',
                    $index->getType(),
                    $index->getIdentifier(),
                    self::MAX_FULLTEXT_BYTE_LENGTH,
                )
            );
            return $index;
        }
        return $index->setFulltextIndex($content);
    }

    /**
     * Normalise `$content` according to `$strategy`.
     *
     * @param string                 $content
     * @param self::INDEX_STRATEGY_* $strategy
     * @return string
     */
    private function normaliseContent(string $content, string $strategy): string
    {
        return match ($strategy) {
            self::INDEX_STRATEGY_TEXT => $this->normaliseAsText($content),
            self::INDEX_STRATEGY_HTML => $this->normaliseAsHtml($content),
        };
    }

    /**
     * Normalise `$content` as if it were HTML:
     * * strip tags
     * * decode special chars
     * * then normalise as if it were plain text
     *
     * @param string $content
     * @return string
     */
    private function normaliseAsHtml(string $content): string
    {
        return $this->normaliseAsText(htmlspecialchars_decode(strip_tags($content)));
    }

    /**
     * Normalise `$content` as if it were plain text: this involves just
     * normalising multiple spaces to single spaces.
     *
     * @param string $content
     * @return string
     */
    private function normaliseAsText(string $content): string
    {
        return trim(
            preg_replace(
                self::NORMALISE_SPACE_REGEXP,
                ' ',
                $content,
            ),
        );
    }

    /**
     * Extract the item ID out of `$subject`, for use in `buildUpdates` and
     * `buildDeletes` if updating indices for a single item.
     *
     * @param object $subject The item to update indices for.
     * @return int|string|null The item ID, or `null` if this builder doesn't work with this type of item.
     */
    protected abstract function extractItemId(object $subject): int|string|null;
}
