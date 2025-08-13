<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment;

use Alaress\Database\Predicate\Builder;
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
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection findByColumnList($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 * @method Model[]|GenericCollection findByPredicates(Builder $pb, array $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('moment');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('discussion_thread_id', ThreadMapper::class, 'id', null, null, 'discussionThread');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'recipient', 'recipient');
        /* For moderation workflow
         */
        $this->joins('submitted_by', UserMapper::class, 'id', null, null, 'submittedBy', 'submittedBy');
        $this->uses('submitted_at', self::TYPE_DATETIME);
        $this->joins('published_by', UserMapper::class, 'id', null, null, 'publishedBy', 'publishedBy');
        $this->uses('published_at', self::TYPE_DATETIME);
        $this->joins('rejected_by', UserMapper::class, 'id', null, null, 'rejectedBy', 'rejectedBy');
        $this->uses('rejected_at', self::TYPE_DATETIME);
        /* Standard timestamps and authoring details
         */
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'creator', 'creator');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsVia(
            'curriculum_moment',
            \Schoolbox\Curriculum\Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'moment_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->joinsVia(
            'moment_tag',
            \Schoolbox\Learning\Moment\Tag\Mapper::class,
            'id',
            'tag_id',
            'moment_id',
            null,
            null,
            null,
            'tags'
        );

        $this->hasJoin(
            'moment_evidence',
            [
                'multiple'  => true,
                'mapper'    => Evidence\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'moment_id',
            ]
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
                {$this->getJoinColumnString('recipient', 'recipient')},
                {$this->getJoinColumnString('recipient.year', 'recipientYear')},
                {$this->getJoinColumnString('recipient.schoolHouse', 'recipientSchoolHouse')},
                {$this->getJoinColumnString('recipient.pronoun', 'recipientPronoun')},
                {$this->getJoinColumnString('recipient.role', 'recipientRole')},
                {$this->getJoinColumnString('creator', 'creator')},
                {$this->getJoinColumnString('creator.pronoun', 'creatorPronoun')},
                {$this->getJoinColumnString('submittedBy', 'submittedBy')},
                {$this->getJoinColumnString('publishedBy', 'publishedBy')},
                {$this->getJoinColumnString('rejectedBy', 'rejectedBy')}
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
            LEFT JOIN {$this->getJoinSql('recipient', '', 'recipient', true)}
            LEFT JOIN {$this->getJoinSql('recipient.year', 'recipient', 'recipientYear', true)}
            LEFT JOIN {$this->getJoinSql('recipient.schoolHouse', 'recipient', 'recipientSchoolHouse', true)}
            LEFT JOIN {$this->getJoinSql('recipient.pronoun', 'recipient', 'recipientPronoun')}
            LEFT JOIN {$this->getJoinSql('recipient.role', 'recipient', 'recipientRole')}
            LEFT JOIN {$this->getJoinSql('creator', '', 'creator', true)}
            LEFT JOIN {$this->getJoinSql('creator.pronoun', 'creator', 'creatorPronoun')}
            LEFT JOIN {$this->getJoinSql('submittedBy', '', 'submittedBy', true)}
            LEFT JOIN {$this->getJoinSql('publishedBy', '', 'publishedBy', true)}
            LEFT JOIN {$this->getJoinSql('rejectedBy', '', 'rejectedBy', true)}
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

//            /** @var Storage\Model|null $storage */
//            $storage = $this->hydrateJoin($row, 'storage', 'storage');
//            if ($storage) {
//                /** @var Mimetype|null $mimetype */
//                $mimetype = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'mimetype');
//                if ($mimetype) {
//                    $storage->setMimetype($mimetype);
//                }
//            }
//            $model->setStorage($storage);

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

                /** @var User\Pronoun\Model|null $recipientPronoun */
                $recipientPronoun = $this->hydrateJoin($row, 'recipient.pronoun', 'recipientPronoun');
                $recipient->setPronoun($recipientPronoun);
            }
            $model->setRecipient($recipient);

            /** @var User|null $creator */
            $creator = $this->hydrateJoin($row, 'creator', 'creator');
            /** @var User\Pronoun\Model|null $creatorPronoun */
            $creatorPronoun = $this->hydrateJoin($row, 'creator.pronoun', 'creatorPronoun');
            $creator->setPronoun($creatorPronoun);
            $model->setCreator($creator);

            // Set the list of tags as an empty collection:
            // these will be hydrated in `hydrateTagsIntoCollection`
            $model->setTags($this->getJoinMapper('moment_tag')->createCollection());

            // Set the list of curriculum codes as an empty collection:
            // these will be hydrated in `hydrateCurriculumNodesIntoCollection`
            $model->setCurriculumNodes($this->getJoinMapper('curriculum_moment')->createCollection());

            /* Initialise evidence stack
             */
            $model->setEvidence($this->getJoinMapper('moment_evidence')->createCollection());

            /* Set submitter/publisher/rejecter
             */
            /** @var \Schoolbox\User $submitter */
            $submitter = $this->hydrateJoin($row, 'submittedBy', 'submittedBy');

            if ($submitter) {
                $model->setSubmittedBy($submitter);
            }

            /** @var \Schoolbox\User $publisher */
            $publisher = $this->hydrateJoin($row, 'publishedBy', 'publishedBy');

            if ($publisher) {
                $model->setPublishedBy($publisher);
            }

            /** @var \Schoolbox\User $rejecter */
            $rejecter = $this->hydrateJoin($row, 'rejectedBy', 'rejectedBy');

            if ($rejecter) {
                $model->setRejectedBy($rejecter);
            }

            $collection[$model->getId()] = $model;
        }

        /* Add extra bits to each moment in the set
         */
        $collection = $this->hydrateTagsIntoCollection($collection);
        $collection = $this->hydrateEvidenceIntoCollection($collection);

        if ($this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            $collection = $this->hydrateCurriculumNodesIntoCollection($collection);
        }

        return $collection;
    }


    private function hydrateEvidenceIntoCollection(GenericCollection $collection): GenericCollection
    {
        $momentIds = $collection->keys();

        $pb = $this->getPredicateBuilder()->in('id', $momentIds);

        $query = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('moment_evidence', 'me')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('moment_evidence', '', 'me')}
            WHERE {$pb->getWhereIntersect()}
        ";

        $momentEvidenceStatement = $this->database->query($query, $pb->getParams());

        foreach ($momentEvidenceStatement as $row) {
            if ($row['me.id'] !== null) {
                /** @var \Schoolbox\Learning\Moment\Evidence\Model $evidence */
                $evidence = $this->hydrateJoin($row, 'moment_evidence', 'me');
                $collection[$row['id']]->getEvidence()->setOffset($evidence->getId(), $evidence);
            }
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
                {$this->getJoinColumnString('moment_tag', 'mt')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('moment_tag', '', 'mt')}
            WHERE {$pb->getWhereIntersect()}
        ";

        $evidenceTagStatement = $this->database->query($query, $pb->getParams());
        foreach ($evidenceTagStatement as $row) {
            if ($row['mt.id'] !== null) {
                //only step into if the evidence tag is NOT deleted
                /** @var \Schoolbox\Learning\Moment\Tag\Model $evidenceTag */
                $evidenceTag = $this->hydrateJoin($row, 'moment_tag', 'mt');
                $collection[$row['id']]->getTags()->setOffset($evidenceTag->getId(), $evidenceTag);
            }
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
                {$this->getJoinColumnString('curriculum_moment', 'cn')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN curriculum_moment cm ON moment.id = cm.moment_id
            INNER JOIN curriculum_node cn ON cm.curriculum_node_id = cn.id AND cn.deleted_at IS NULL
            WHERE {$pb->getWhereIntersect()}
        ";

        $curriculumNodeStatement = $this->database->query($query, $pb->getParams());
        foreach ($curriculumNodeStatement as $row) {
            /** @var \Schoolbox\Curriculum\Node\Model $curriculumNode */
            $curriculumNode = $this->hydrateJoin($row, 'curriculum_moment', 'cn');
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
            LEFT JOIN {$this->getJoinSql('curriculum_moment', '', 'cn')}
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
