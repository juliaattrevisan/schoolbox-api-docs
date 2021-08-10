<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use House;
use PDOStatement;
use Schoolbox\Discussion\Thread\Mapper as ThreadMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Mapper for learning moment evidence.
 *
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('evidence');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('filename', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('discussion_thread_id', ThreadMapper::class, 'id', null, null, 'discussionThread');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'recipient', 'recipient');
        $this->uses('type', self::TYPE_STRING);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'creator', 'creator');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsVia(
            'curriculum_evidence',
            \Schoolbox\Curriculum\Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'evidence_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->joinsVia(
            'evidence_tag',
            Tag\Mapper::class,
            'id',
            'tag_id',
            'evidence_id',
            null,
            null,
            null,
            'tags'
        );
    }


    /**
     * Override to select storage, recipient and creator.
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('storage', 'storage')},
                {$this->getJoinColumnString('storage.allowedFileExtension', 'mimetype')},
                {$this->getJoinColumnString('recipient', 'recipient')},
                {$this->getJoinColumnString('recipient.year', 'recipientYear')},
                {$this->getJoinColumnString('recipient.schoolHouse', 'recipientSchoolHouse')},
                {$this->getJoinColumnString('creator', 'creator')}
        ";
    }


    /**
     * Override to join on storage, recipient and creator.
     *
     * @return string
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('storage', '', 'storage')}
            LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'storage', 'mimetype')}
            LEFT JOIN {$this->getJoinSql('recipient', '', 'recipient', true)}
            LEFT JOIN {$this->getJoinSql('recipient.year', 'recipient', 'recipientYear', true)}
            LEFT JOIN {$this->getJoinSql('recipient.schoolHouse', 'recipient', 'recipientSchoolHouse', true)}
            LEFT JOIN {$this->getJoinSql('creator', '', 'creator', true)}
        ";
    }


    /**
     * Override to:
     * * hydrate storage, recipient and creator
     * * key the collection by ID
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     *
     * @noinspection PhpDocMissingThrowsInspection hydrate() may throw an error if the join is misdefined.
     *               It's fine here though.
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $model = $this->hydrate($row);

            /** @var Storage\Model|null $storage */
            $storage = $this->hydrateJoin($row, 'storage', 'storage');
            if ($storage) {
                /** @var Mimetype|null $mimetype */
                $mimetype = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'mimetype');
                if ($mimetype) {
                    $storage->setMimetype($mimetype);
                }
            }
            $model->setStorage($storage);

            /** @var User|null $recipient */
            $recipient = $this->hydrateJoin($row, 'recipient', 'recipient');
            if ($recipient) {
                /** @var YearLevel|null $recipientYear */
                $recipientYear = $this->hydrateJoin($row, 'recipient.year', 'recipientYear');
                if ($recipientYear) {
                    $recipient->setYear($recipientYear);
                }

                /** @var House|null $recipientSchoolHouse */
                $recipientSchoolHouse = $this->hydrateJoin($row, 'recipient.schoolHouse', 'recipientSchoolHouse');
                if ($recipientSchoolHouse) {
                    $recipient->setSchoolHouse($recipientSchoolHouse);
                }
            }
            $model->setRecipient($recipient);

            /** @var User|null $creator */
            $creator = $this->hydrateJoin($row, 'creator', 'creator');
            $model->setCreator($creator);

            // Set the list of tags as an empty collection:
            // these will be hydrated in `hydrateTagsIntoCollection`
            $model->setTags($this->getJoinMapper('evidence_tag')->createCollection());

            // Set the list of curriculum codes as an empty collection:
            // these will be hydrated in `hydrateCurriculumNodesIntoCollection`
            $model->setCurriculumNodes($this->getJoinMapper('curriculum_evidence')->createCollection());

            $collection[$model->getId()] = $model;
        }
        $collection = $this->hydrateTagsIntoCollection($collection);
        if ($this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            $collection = $this->hydrateCurriculumNodesIntoCollection($collection);
        }
        return $collection;
    }


    /**
     * Helper method for hydrating evidence tags into the collection of
     * evidences retrieved by `hydrateSelect`.
     *
     * @param GenericCollection $collection
     * @return GenericCollection
     */
    private function hydrateTagsIntoCollection(GenericCollection $collection): GenericCollection
    {
        $evidenceIds = $collection->keys();
        $pb          = $this->getPredicateBuilder()->in('id', $evidenceIds);
        $query       = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('evidence_tag', 'et')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('evidence_tag', '', 'et')}
            WHERE {$pb->getWhereIntersect()}
        ";

        $evidenceTagStatement = $this->database->query($query, $pb->getParams());
        foreach ($evidenceTagStatement as $row) {
            /** @var Tag\Model $evidenceTag */
            $evidenceTag = $this->hydrateJoin($row, 'evidence_tag', 'et');
            $collection[$row['id']]->getTags()->setOffset($evidenceTag->getId(), $evidenceTag);
        }
        return $collection;
    }


    /**
     * Helper method for hydrating curriculum nodes into the collection of
     * evidences retrieved by `hydrateSelect`.
     *
     * @param GenericCollection $collection
     * @return GenericCollection
     */
    private function hydrateCurriculumNodesIntoCollection(GenericCollection $collection): GenericCollection
    {
        $evidenceIds = $collection->keys();
        $pb          = $this->getPredicateBuilder()->in('id', $evidenceIds);
        $query       = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('curriculum_evidence', 'cn')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('curriculum_evidence', '', 'cn')}
            WHERE {$pb->getWhereIntersect()}
        ";

        $curriculumNodeStatement = $this->database->query($query, $pb->getParams());
        foreach ($curriculumNodeStatement as $row) {
            /** @var \Schoolbox\Curriculum\Node\Model $curriculumNode */
            $curriculumNode = $this->hydrateJoin($row, 'curriculum_evidence', 'cn');
            $collection[$row['id']]->getCurriculumNodes()->setOffset($curriculumNode->getId(), $curriculumNode);
        }
        return $collection;
    }


    /**
     * Find all the curriculum nodes that used to come with each Model instance
     *
     * @param Model $evidence
     * @return GenericCollection
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findCurriculumNodes(Model $evidence): GenericCollection
    {
        $curriculumNodeMapper = \Schoolbox\Curriculum\Node\Mapper::getInstance();

        $query = "
            SELECT {$curriculumNodeMapper->getColumnString('cn')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('curriculum_evidence', '', 'cn')}
            WHERE {$this->getTable()}.{$this->getIdentifierColumn()} = :evidenceId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(
            [
                'evidenceId' => $evidence->getId(),
            ]
        );

        $nodes = $curriculumNodeMapper->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $node = $curriculumNodeMapper->hydrate($row, 'cn');

            if ($node) {
                $nodes->add($node);
            }
        }

        return $nodes;
    }
}
