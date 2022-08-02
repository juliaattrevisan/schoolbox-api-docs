<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace EOTC;

use Alaress\Database\Predicate\Builder;
use Alaress\IDatabase;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable
{
    /**
     * @var IDatabase
     */
    private IDatabase $database;

    /**
     * RelationalDelete constructor.
     *
     * @param Mapper          $mapper
     * @param LoggerInterface $logger
     * @param IDatabase       $database
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger, IDatabase $database)
    {
        parent::__construct($mapper, $logger);

        $this->database = $database;
    }

    /**
     * Unlink EotcMetaData from any associated RAMS files as identified by the specified hash.
     *
     * Does not delete the EotcMetaData entity.
     *
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        // Find metadata_id + storage_id, so we can delete the relationship, and advise operator of effect.
        $query     = "
            SELECT
                `emf`.`metadata_id`,
                `emf`.`storage_id`
            FROM `eotc_metadata_file` `emf`
            -- left joining as there may be eotc_metadata_file without corresponding eotc_metadata
            LEFT JOIN `eotc_metadata` ON `eotc_metadata`.id = `emf`.`metadata_id`
            JOIN `storage` ON `storage`.`storage_id` = `emf`.`storage_id`
            WHERE `hash` = :hash";
        $statement = $this->database->query($query, ['hash' => $hash]);
        if ($statement->rowCount() === 0) {
            return;
        }
        $metadataIds = [];
        $storageIds  = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $metadataIds[] = $row['metadata_id'];
            $storageIds[]  = $row['storage_id'];
        }
        // Filter out NULLs & deduplicate
        $notNull     = static function ($metadataId) {
            return $metadataId !== null;
        };
        $metadataIds = array_unique(array_filter($metadataIds, $notNull));
        $metadataIdsCount = count($metadataIds);

        if (!$storageIds) {
            return;
        }

        // should be one.... but
        $storageIdCount = count($storageIds);
        if ($performDryRun) {
            $this->logger->notice("Will delete {$storageIdCount} file(s) from $metadataIdsCount EotcMetadata(s)", ['char' => '-']);
        } else {
            $bindings  = Builder::getBindings($storageIds, 'st');
            $query     = "DELETE FROM `eotc_metadata_file` WHERE `storage_id` IN ({$bindings->getPlaceholder()})";
            $statement = $this->database->prepare($query);
            $statement->execute($bindings->getParams());
            $this->logger->notice("Deleted {$storageIdCount} file(s) from $metadataIdsCount EotcMetadata(s)", ['char' => '-']);
        }
    }
}
