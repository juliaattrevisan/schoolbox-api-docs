<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Alaress\Web\Request\Values;
use External_Pastoral;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Action\Mapper as PastoralActionMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTagsModel;
use Schoolbox\Pastoral\Translator\VisibilityTranslator;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\TokenReplacer;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class for processing pastoral records from a form.
 */
class RecordProcessor
{
    /**
     * Constructor
     *
     * @param IConfigLoader            $config
     * @param DataSource               $pastoralDataSource
     * @param PastoralSubTypeMapper    $pastoralSubTypeMapper
     * @param PastoralTypeMapper       $pastoralTypeMapper
     * @param PastoralSeverityMapper   $pastoralSeverityMapper
     * @param PastoralTagMapper        $pastoralTagMapper
     * @param PastoralActionMapper     $pastoralActionMapper
     * @param RecordAction\Mapper      $pastoralRecordActionMapper
     * @param PastoralAttachmentMapper $pastoralAttachmentMapper
     * @param UserMapper               $userMapper
     * @param PastoralAccessMapper     $pastoralAccessMapper
     * @param PastoralGroupMapper      $pastoralGroupMapper
     * @param ErrorMapper              $errorMapper
     */
    public function __construct(
        private IConfigLoader $config,
        private PastoralDataSource $pastoralDataSource,
        private PastoralSubTypeMapper $pastoralSubTypeMapper,
        private PastoralTypeMapper $pastoralTypeMapper,
        private PastoralSeverityMapper $pastoralSeverityMapper,
        private PastoralTagMapper $pastoralTagMapper,
        private PastoralActionMapper $pastoralActionMapper,
        private \Schoolbox\Pastoral\RecordAction\Mapper $pastoralRecordActionMapper,
        private PastoralAttachmentMapper $pastoralAttachmentMapper,
        private UserMapper $userMapper,
        private PastoralAccessMapper $pastoralAccessMapper,
        private PastoralGroupMapper $pastoralGroupMapper,
        private ErrorMapper $errorMapper,
    ) {
    }

    /**
     * Create/update student records
     *
     * @param int                $primaryStudentId    The primary record's student ID
     * @param Values             $post                POST parameters passed by the create/update form
     * @param array              $duplicateStudentIds List of students the primary record should be duplicated on
     * @param PastoralModel|null $parent              Parent record (if we are updating an existing record)
     *
     * @return GenericCollection
     * @throws MapperException
     */
    public function processPastoralRecords(
        User $user,
        int $primaryStudentId,
        Values $post,
        array $duplicateStudentIds = [],
        PastoralModel $parent = null
    ): GenericCollection {
        $heading = trim((string)$post->get('heading'));
        if (empty($heading)) {
            throw new SchoolboxException('Heading cannot be empty');
        }
        $records = $this->pastoralDataSource->createCollection();

        // Make sure the primary records student ID is at the start of the array so we create it first
        array_unshift($duplicateStudentIds, $primaryStudentId);

        // Prepare type. No database change.
        if (!$post->get('typeId')) {
            throw new SchoolboxException('Type cannot be empty');
        }
        try {
            $type = $this->pastoralTypeMapper->findById($post->get('typeId'));
        } catch (MapperException\Deleted $e) {
            /** @var Type\Model $type */
            $type = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw new SchoolboxException("Pastoral type with ID {$post->get('typeId')} cannot be found");
        }

        // Prepare subtype. No database change.
        $subtypeId = $post->get('subtypeId');
        $subtype   = null;
        if (!empty($subtypeId)) {
            try {
                $subtype = $this->pastoralSubTypeMapper->findById($subtypeId);
            } catch (MapperException\Deleted $e) {
                /** @var SubType\Model $subtype */
                $subtype = $e->getDeletedObject();
            } catch (MapperException $e) {
                throw new SchoolboxException("Pastoral sub-type with ID {$subtypeId} cannot be found");
            }
            // Ensure that subtype belongs to the parent type:
            // if there is somehow a mismatch, clear the subtype
            if ($type->getId() !== $subtype->getPastoralType()->getId()) {
                throw new SchoolboxException("Pastoral sub-type with ID {$subtypeId} is not a sub-type of type {$type->getId()}");
            }
        }
        // If type has subtypes but subtype is not provided, throw error
        if (!$subtype && $type->getSubTypes()->count() > 0) {
            throw new SchoolboxException("Sub-type needs to be selected for type {$post->get('typeId')}");
        }

        // Prepare Severity. No database change.
        $severity = null;
        if ($this->config->get('pastoral_severity')) {
            if (!$post->get('severityId')) {
                throw new SchoolboxException("Pastoral severity is required");
            }
            try {
                $severity = $this->pastoralSeverityMapper->findById($post->get('severityId'));
            } catch (MapperException\Deleted $e) {
                /** @var Severity\Model $severity */
                $severity = $e->getDeletedObject();
            } catch (MapperException $e) {
                throw new SchoolboxException("Pastoral severity with ID {$post->get('severityId')} cannot be found");
            }
        }

        // Prepare access. No database change yet.
        $accesses = (new VisibilityTranslator(
            $this->pastoralAccessMapper,
            $this->pastoralGroupMapper,
            $this->userMapper,
            \Schoolbox\Pastoral\Access\Model::class
        ))->prepareAccesses($post, $subtype ?? $type);

        // Prepare attachments. No database change yet.
        $newAttachments = $this->prepareAttachments($post);

        // Prepare actions.
        $newRecordActions = $this->prepareRecordActions($post);

        // Now ready to make changes
        $db = $this->pastoralTypeMapper->getDatabase();
        $db->beginTransaction();

        // Prepare tags. Save newly created tags.
        $tags = $this->prepareTags($post->get('tags') ?? '', $post->get('tagNames') ?? '');

        $pastoralExternal = new External_Pastoral();

        // Go through every kid
        foreach ($duplicateStudentIds as $studentIdCurrent) {
            $previousRecord = null;
            if ($parent instanceof PastoralModel && $studentIdCurrent === $primaryStudentId) {
                // Updating existing pastoral record
                $record         = $parent;
                $previousRecord = clone $parent;
            } else {
                // Creating a new record
                $record = $this->pastoralDataSource->createModel();
                if (isset($parent)) {
                    $record->setParent($parent);
                }
            }

            // Find student
            try {
                $student = $this->userMapper->findById($studentIdCurrent);
            } catch (MapperException $e) {
                $db->rollback();
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
            }
            $record->setStudent($student);

            // If type was deleted, allow it to be saved only when it's not changed.
            if ($type->isDeleted() && ($record->getType()->getId() !== $type->getId())) {
                $db->rollback();
                throw new SchoolboxException("Pastoral type with ID {$type->getId()} cannot be found");
            }
            $record->setType($type);

            // If subtype was deleted, allow it to be saved only when it's not changed.
            if ($subtype && $subtype->isDeleted() && ($record->getSubType()->getId() !== $subtype->getId())) {
                $db->rollback();
                throw new SchoolboxException("Pastoral subtype with ID {$subtype->getId()} cannot be found");
            }
            $record->setSubType($subtype);

            // If severity was deleted, allow it to be saved only when it's not changed.
            if ($severity && $severity->isDeleted() && (!$record->getSeverity() || $record->getSeverity()->getId() !== $severity->getId())) {
                $db->rollback();
                throw new SchoolboxException("Pastoral severity with ID {$severity->getId()} cannot be found");
            }
            $record->setSeverity($severity);
            $record->setModifiedBy($user);
            $record->setHeading($heading);
            if ($post->offsetExists('occurredOn')) {
                if ((string)$post->get('occurredOn') === '') {
                    $record->setOccurredOn(null);
                } else {
                    try {
                        $occurredOn = \Alaress_Date_Format::newLocalShort($post->get('occurredOn'))->startOfDay();
                        $record->setOccurredOn($occurredOn);
                    } catch (\Exception $e) {
                        $locale = new \Alaress_Date_Locale_Config();
                        throw new SchoolboxException("occurredOn must be in the format of {$locale->localShortFormat()}");
                    }
                }
            }
            if ($post->get('points')) {
                $points = $post->getFloat('points');
                if (!is_numeric($post->get('points')) || $points < -1000000 || $points > 1000000) {
                    throw new SchoolboxException('Points must be a float between -1000000 and 1000000');
                }
                $record->setPoints($points);
            }

            /* Perform token replacements, basically a square bracket phplib
             * that performs a one-way substitution
             */
            $record->setBodyPublic(
                (new TokenReplacer\Student(new TokenReplacer\Simple()))
                    ->replace(trim($post->get('body_public') ?? ''), $student)
            );

            /* Perform token replacements, basically a square bracket phplib
             * that performs a one-way substitution
             */
            $record->setBodyPrivate(
                (new TokenReplacer\Student(new TokenReplacer\Simple()))
                    ->replace(trim($post->get('body_private') ?? ''), $student)
            );

            // Only set owner if this is a new record
            if (!$record->getId()) {
                $record->setOwner($user);
                $record->setExternalId(null);
            }
            $this->pastoralDataSource->save($record);

            // Store the first records ID for use as parent for subsequent copies
            if (!isset($parent)) {
                $parent = $record;
            }

            // Save pastoral tags
            $this->pastoralTagMapper->deleteForRecord($record);
            foreach ($tags as $tag) {
                $this->pastoralTagMapper->assignToRecord($record, $tag);
            }
            $record->setTags($tags);

            // Save permissions/visibility
            $existingAccesses = $record->getAccess()->indexBy('id');
            $keptAccessIds    = [];
            $record->setAccess($this->pastoralAccessMapper->createCollection());
            /** @var PastoralAccessModel $access */
            foreach ($accesses as $access) {
                if ($previousRecord && $access->getId()) {
                    $keptAccessIds[] = $access->getId();
                }
                $recordAccess = clone $access;
                // if this record is new, create new access
                if (!$previousRecord) {
                    $recordAccess->setId(0);
                }
                $recordAccess->setPastoral($record);
                $this->pastoralAccessMapper->save($recordAccess);
                $record->getAccess()->add($recordAccess);
            }

            if ($previousRecord) {
                // Delete accesses no longer exists
                $removingAccesses = $existingAccesses->filter(
                    static function ($access) use ($keptAccessIds) {
                        /** @var PastoralAccessModel $access */
                        return !in_array($access->getId(), $keptAccessIds);
                    }
                );
                $this->pastoralAccessMapper->delete($removingAccesses);
            }

            // Save actions, record actions are compared against luAction id rather than recordAction id.
            if ($this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $existingRecordActions = $record->getRecordActions()->indexByCallback(
                    static function ($recordAction) {
                        /** @var RecordAction\Model $recordAction */
                        return $recordAction->getAction()->getId();
                    }
                );
                $record->setRecordActions($this->pastoralRecordActionMapper->createCollection());
                /** @var RecordAction\Model $action */
                foreach ($newRecordActions as $action) {
                    // if this action is already set to this record, just put it back
                    if (in_array($action->getAction()->getId(), $existingRecordActions->keys())) {
                        $record->getRecordActions()->add($existingRecordActions->getOffset($action->getAction()->getId()));
                    } else {
                        $recordAction = clone $action;
                        $recordAction->setPastoral($record);
                        $this->pastoralRecordActionMapper->save($recordAction);
                        $record->getRecordActions()->add($recordAction);
                    }
                }
                // Delete actions no longer exists
                $removingRecordActions = $existingRecordActions->filter(
                    static function ($action) use ($newRecordActions) {
                        /** @var RecordAction\Model $action */
                        return !in_array($action->getAction()->getId(), $newRecordActions->keys());
                    }
                );
                $this->pastoralRecordActionMapper->deleteByColumn(
                    'id',
                    $removingRecordActions->getPropertyValues('id')
                );
            }

            // preparation
            $oldAttachments       = $this->pastoralAttachmentMapper->findByColumn(
                'pastoral_id',
                $record->getId()
            );
            $newRecordAttachments = $this->pastoralAttachmentMapper->createCollection();
            foreach ($newAttachments as $newAttachment) {
                $newRecordAttachment = clone $newAttachment;
                $newRecordAttachment->setPastoral($record);
                $newRecordAttachments->add($newRecordAttachment);
            }

            [
                $createdAttachments,
                $deletedAttachments,
            ] = $this->pastoralAttachmentMapper->diffAttachments(
                $newRecordAttachments,
                $oldAttachments
            );
            // Save new attachments that aren't in old
            $this->pastoralAttachmentMapper->save($createdAttachments);
            // Delete old attachments that aren't in new
            $this->pastoralAttachmentMapper->delete($deletedAttachments);
            $record->setAttachments($newRecordAttachments);

            $db->onPersist(
                function () use ($previousRecord, $record, $pastoralExternal): void {
                    // raise notification
                    $action = $previousRecord ? \Schoolbox\Mapper::METHOD_UPDATE : \Schoolbox\Mapper::METHOD_CREATE;
                    $this->pastoralDataSource->resetPropagator();
                    $this->pastoralDataSource->raiseNotification($record, $action, $previousRecord);

                    // Save record in external database, and set pastoral external_id in Schoolbox database
                    try {
                        $pastoralExternal->syncPastoral(
                            $record,
                            $this->config->get('external_pastoral_title_include')
                        );
                    } catch (\Exception $e) {
                        // TODO figure out how to pass to Sentry
//                        // Send it to Sentry I suppose: there's nothing that
//                        // the end user may do with this information.
//                        $this->controller->captureException($e);
                    }
                }
            );
            $records->add($record);
        }
        $db->commit();
        return $records;
    }

    /**
     * Prepare pastoral record attachments.
     *
     * @param Values $post POST request vars
     * @return Collection
     * @throws MapperException
     */
    private function prepareAttachments(Values $post): Collection
    {
        $newAttachments = $this->pastoralAttachmentMapper->createCollection();

        foreach ([true, false] as $isPrivate) {
            $attachzone    = $isPrivate ? Controller::ATTACHZONE_PRIVATE : Controller::ATTACHZONE_PUBLIC;
            $processedHash = [];

            // If there are any new attachments, compare and replace
            if (is_array($post[$attachzone . '_attachments'] ?? null)) {
                $collectorFactory  = new CollectorFactory();
                $attachmentObjects = $collectorFactory->build($post[$attachzone . '_attachments'])->getAll();

                /** @var Storage $storageObject */
                foreach ($attachmentObjects as $storageObject) {
                    if (in_array($storageObject->getHash(), $processedHash)) {
                        continue;
                    }
                    // Only add attachments which don't already exist
                    $attachment = $this->pastoralAttachmentMapper->createModel()
                                                                 ->setRealname($storageObject->getFilename())
                                                                 ->setStorage($storageObject)
                                                                 ->setPrivate($isPrivate)
                                                                 ->setExternalId(null);
                    $newAttachments->add($attachment);
                    $processedHash[] = $storageObject->getHash();
                }
            }
        }

        return $newAttachments;
    }

    /**
     * Returns recordActions from post vars, indexed by luAction id
     * @param Values $post
     * @return Collection
     * @throws MapperException
     */
    private function prepareRecordActions(Values $post): Collection
    {
        $recordActions = $this->pastoralRecordActionMapper->createCollection();
        if (!$this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            return $recordActions;
        }

        if (!is_array($post->get('actions') ?? [])) {
            throw new SchoolboxException('actions need to be passed as an array');
        }

        $postedActionIds = array_map('intval', array_filter($post->get('actions') ?? []));

        $actions =
            !empty($postedActionIds)
                ? $this->pastoralActionMapper->findByColumn('id', $postedActionIds)
                : $this->pastoralActionMapper->createCollection();

        // if there are actions which are not in db, throw error
        $invalidActionIds = array_diff($postedActionIds, $actions->getPropertyValues('id'));
        if (!empty($invalidActionIds)) {
            throw new SchoolboxException(sprintf("Action %s not found.", implode(',', $invalidActionIds)));
        }

        foreach ($actions as $action) {
            /** @var RecordAction\Model $recordAction */
            $recordAction = $this->pastoralRecordActionMapper->createModel();
            $recordActions->offsetSet($action->getId(), $recordAction->setAction($action));
        }
        return $recordActions;
    }


    /**
     * Assign tags to a record, spawning them if required, or resurrecting them
     * if these tags once existed
     *
     * @param string $tagIdCsv
     * @param string $tagNameCsv
     * @return GenericCollection
     * @throws MapperException
     */
    private function prepareTags(string $tagIdCsv, string $tagNameCsv): GenericCollection
    {
        $tagNames = array_map('trim', array_filter(explode(',', $tagNameCsv)));
        // remove new tags set as string starting from 'new-'
        $tagIds = array_map(
            'trim',
            array_filter(explode(',', $tagIdCsv), static function ($tagId) {
                return is_numeric(trim($tagId));
            })
        );

        // create initial tag collection from tagIds
        $tags          = $this->pastoralTagMapper->findByColumn('id', $tagIds);
        $invalidTagIds = array_diff($tagIds, $tags->getPropertyValues('id'));
        if (!empty($invalidTagIds)) {
            throw new SchoolboxException(sprintf("Invalid Tag ID provided: %s", implode(',', $invalidTagIds)));
        }

        $canCreate = (bool)$this->config->get('pastoral_create_tag_enable');

        foreach ($tagNames as $tagName) {
            /** @var PastoralTagsModel|null $tag */
            $tag = null;
            // newly created tag from selectize has this strange string from modify_scripts.twig
            try {
                $tag = $this->pastoralTagMapper->findOneByColumn('name', $tagName, ['cache' => false]);
                $tags->add($tag);
            } catch (MapperException $e) {
                if (!$canCreate) {
                    throw new SchoolboxException(sprintf("Tag %s not found.", $tagName));
                }
                $tag = new PastoralTagsModel();
                $tag->setName($tagName);
                $this->pastoralTagMapper->save($tag);
                $tags->add($tag);
            }
        }
        return $tags;
    }
}
