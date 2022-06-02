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
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger)
    {
        parent::__construct($mapper, $logger);

        $this->database = $this->mapper->getDatabase();
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        // Find metadata id
        $query     = "SELECT `emf`.`storage_id`
                        FROM `eotc_metadata_file` `emf`
                            -- left joining as there may be eotc_metadata_file without corresponding eotc_metadata
                            LEFT JOIN `eotc_metadata` ON `eotc_metadata`.id = `emf`.`metadata_id`
                            JOIN `storage` ON `storage`.`storage_id` = `emf`.`storage_id`
                        WHERE `hash` = :hash";
        $statement = $this->database->query($query, ['hash' => $hash]);
        if ($statement->rowCount() === 0) {
            return;
        }
        $storageIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (!$storageIds) {
            return;
        }

        // should be one.... but
        $storageIdCount = count($storageIds);
        if ($performDryRun) {
            $this->logger->notice("Will delete {$storageIdCount} eotc metadata file(s) from eotc_metadata_file", ['char' => '-']);
        } else {
            $bindings  = Builder::getBindings($storageIds, 'st');
            $query     = "DELETE FROM `eotc_metadata_file` WHERE `storage_id` IN ({$bindings->getPlaceholder()})";
            $statement = $this->database->prepare($query);
            $statement->execute($bindings->getParams());
            $this->logger->notice("Deleted {$storageIdCount} eotc metadata files from eotc_metadata_file", ['char' => '-']);
        }
    }
}
