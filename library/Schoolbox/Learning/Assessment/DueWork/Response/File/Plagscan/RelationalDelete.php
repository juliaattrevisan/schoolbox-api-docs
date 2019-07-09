<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;
use Schoolbox\Resource\Storage\RelationalDelete\IRelationalDelete;

class RelationalDelete extends Deletable implements IRelationalDelete
{
    /**
     * @var \Database
     */
    private $database;

    /**
     * RelationalDelete constructor.
     *
     * @param Mapper          $mapper
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger)
    {
        parent::__construct($mapper, $logger, 'storage_hash');

        $this->database = \Database::getInstance();
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        $query = 'DELETE pd FROM plagscan_document pd 
                    INNER JOIN student_submission_file ssf ON pd.student_submission_file_id = ssf.id 
                    WHERE ssf.hash = :hash';

        $statement = $this->database->prepare($query);
        $statement->execute(['hash' => $hash]);

        if (!$statement->rowCount()) {
            parent::remove($hash, $performDryRun);
        }
    }
}
