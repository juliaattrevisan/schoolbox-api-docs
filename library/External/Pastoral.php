<?php
/**
 * @author      Damian Sloane <jdsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use \Schoolbox\Pastoral\Model as PastoralModel;
use \Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;

class External_Pastoral implements External_PastoralInterface
{
    /** @var External_PastoralInterface */
    private $database;

    /** @var External_Offline_Pastoral */
    private $offline;
    
    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildPastoralConnector($databaseName));
        $this->setOffline(new External_Offline_Pastoral());
    }

    /**
     * @inheritdoc
     */
    public function importPastoral($user, $defaultOwner, $importParentAccess)
    {
        return $this->getDatabase()->importPastoral($user, $defaultOwner, $importParentAccess);
    }

    /**
     * @inheritdoc
     */
    public function getPastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->getPastoral($pastoral);
    }

    /**
     * @inheritdoc
     */
    public function createPastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->createPastoral($pastoral);
    }

    /**
     * @inheritdoc
     */
    public function updatePastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->updatePastoral($pastoral);
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->deletePastoral($pastoral);
    }

    /**
     * @inheritdoc
     */
    public function createComment(PastoralCommentModel $comment)
    {
        return $this->getDatabase()->createComment($comment);
    }

    /**
     * @inheritDoc
     */
    public function deleteComment(PastoralCommentModel $comment)
    {
        return $this->getDatabase()->deleteComment($comment);
    }

    /**
     * @inheritDoc
     */
    public function uploadPastoralDocument(UploadQueue $upload)
    {
        return $this->getDatabase()->uploadPastoralDocument($upload);
    }

    /**
     * @return External_PastoralInterface
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param External_PastoralInterface $database
     * @return self
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @return External_Offline_Pastoral
     */
    public function getOffline()
    {
        return $this->offline;
    }

    /**
     * @param External_Offline_Pastoral $offline
     * @return self
     */
    public function setOffline($offline)
    {
        $this->offline = $offline;
        return $this;
    }
}
