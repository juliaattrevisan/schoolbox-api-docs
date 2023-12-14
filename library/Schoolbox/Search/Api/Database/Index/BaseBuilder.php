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
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
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
            ->setFulltextIndex('')
            ->setTextIndex('');
    }

    /**
     * Helper function for adding a fulltext lookup to the index.
     *
     * @param Index\Model $index
     * @param string|null $indexContent
     * @return Index\Model
     */
    protected function addFulltextIndex(Index\Model $index, ?string $indexContent): Index\Model
    {
        if (!isset($indexContent)) {
            return $index;
        }
        // Normalise $indexContent before adding it. Possible filters:
        // trim, strip_tags, HTML special chars decode, space normalisation,
        // stop-word removal?
        // TODO does this need to be customised per content type?
        //      yes it does, as I'm adding different types of content
        $normalisedIndexContent = trim(
            preg_replace(
                self::NORMALISE_SPACE_REGEXP,
                ' ',
                htmlspecialchars_decode(strip_tags($indexContent)),
            ),
        );
        if ($normalisedIndexContent === '') {
            return $index;
        }

        $currentIndex = $index->getFulltextIndex();
        if ($currentIndex === '') {
            return $index->setFulltextIndex($normalisedIndexContent);
        } else {
            return $index->setFulltextIndex($currentIndex . self::INDEX_SEPARATOR . $normalisedIndexContent);
        }
    }

    /**
     * Helper function for adding a basic text lookup to the index.
     *
     * @param Index\Model $index
     * @param string|null $indexContent
     * @return Index\Model
     */
    protected function addTextIndex(Index\Model $index, ?string $indexContent): Index\Model
    {
        if (!isset($indexContent)) {
            return $index;
        }
        $normalisedIndexContent = trim(
            preg_replace(
                self::NORMALISE_SPACE_REGEXP,
                ' ',
                $indexContent,
            ),
        );
        if ($normalisedIndexContent === '') {
            return $index;
        }

        $currentIndex = $index->getTextIndex();
        if ($currentIndex === '') {
            return $index->setTextIndex($normalisedIndexContent);
        } else {
            return $index->setTextIndex($currentIndex . self::INDEX_SEPARATOR . $normalisedIndexContent);
        }
    }
}
