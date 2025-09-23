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
use Schoolbox\Pastoral\RecordAction\Mapper as PastoralRecordActionMapper;
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
use Sentry\State\HubInterface;

/**
 * Class for processing pastoral records from a form.
 */
class RecordProcessor
{
    /* Pastoral Points must fall within this range
     */
    private const POINTS_MIN = -1000000;
    private const POINTS_MAX = 1000000;


    /**
     * FIXME remove since I can't programmatically rely on getters/setters from the mapper
     *  in a derived manner (stupid underscores!) which means I'm already whitelisting simply
     *  by implementing the mapping manually
     *
     * Partial Updates only accept values from the following fields
     * (a whitelist)
     *
     * The value indicates whether we can use a mapper method to blindly map
     * a value to an instance's property: tags, actions, roles, groups are
     * separately addressed from heading, typeId, et al.
     */
    private const PARTIAL_ALLOWED_FIELDS = [
        /* Title, in the ui
         *
         * Other nodes that can be directly mapped to an instance (true)
         * or if they're dealt with separately (false)
         */
        'heading',
        'points',
        /* Related objects need instances, not scalars, to map successfully to an instance
         */
        'typeId',
        'subtypeId',
        'severityId',
        /* Dates
         */
        'expiresAt',
        'occurredOn',
        /* Confidential text
         */
        'body_private',
        /* General text
         */
        'body_public',
        /* Actions, Tags, Roles, Groups all sold separately
         */
        'actions',
        /* Why would you ever want to use `tags` vs `tagNames`? The latter is way more convenient
         */
        'tagNames',
        /* Role + Group = Access (Record Visibility)
         */
        'role',
        'group',
    ];


    /**
     * Constructor
     *
     * @param IConfigLoader              $config
     * @param DataSource                 $pastoralDataSource
     * @param PastoralSubTypeMapper      $pastoralSubTypeMapper
     * @param PastoralTypeMapper         $pastoralTypeMapper
     * @param PastoralSeverityMapper     $pastoralSeverityMapper
     * @param PastoralTagMapper          $pastoralTagMapper
     * @param PastoralActionMapper       $pastoralActionMapper
     * @param PastoralRecordActionMapper $pastoralRecordActionMapper
     * @param PastoralAttachmentMapper   $pastoralAttachmentMapper
     * @param UserMapper                 $userMapper
     * @param PastoralAccessMapper       $pastoralAccessMapper
     * @param PastoralGroupMapper        $pastoralGroupMapper
     * @param ErrorMapper                $errorMapper
     * @param HubInterface               $sentry
     */
    public function __construct(
        private IConfigLoader $config,
        private PastoralDataSource $pastoralDataSource,
        private PastoralSubTypeMapper $pastoralSubTypeMapper,
        private PastoralTypeMapper $pastoralTypeMapper,
        private PastoralSeverityMapper $pastoralSeverityMapper,
        private PastoralTagMapper $pastoralTagMapper,
        private PastoralActionMapper $pastoralActionMapper,
        private PastoralRecordActionMapper $pastoralRecordActionMapper,
        private PastoralAttachmentMapper $pastoralAttachmentMapper,
        private UserMapper $userMapper,
        private PastoralAccessMapper $pastoralAccessMapper,
        private PastoralGroupMapper $pastoralGroupMapper,
        private ErrorMapper $errorMapper,
        private HubInterface $sentry,
    ) {
    }


    /**
     * Overwrites $record's props with whatever ones existed in $partial, an array of key-value tuples
     * that partially represent a pastoral record.
     *
     * @param array $partial
     * @param Model $record
     * @return void
     */
    public function processPartialPastoralRecords(array $partial, PastoralModel $record): void
    {
        /* TODO set up transaction because we're mutating in situ
         *  and you can't tell when you'll die
         */

        /* Manually map the partial array into $record, one prop at a time
         * Not all of them can be conveniently derived: body_public maps to setBody_public() (nonexistent method)
         * and some require actual hydrated stubs to proceed (types, subtypes, severity, etc)
         *
         * Do it the long way (really?)
         */
        if ($partial['heading'] ?? false) {
            $record->setHeading($partial['heading']);
        }

        /* Points must be within a range of valid values randomly hardcoded here
         */
        if ($partial['points'] ?? false) {
            $points = (float)$partial['points'];

            /* Assert correct value range
             */
            if (!is_numeric($partial['points']) || $points < self::POINTS_MIN || $points > self::POINTS_MAX) {
                throw new SchoolboxException(sprintf(
                    'Points must be a float between %s and %s',
                    self::POINTS_MIN,
                    self::POINTS_MAX
                ));
            }

            $record->setPoints($partial['points']);
        }

        /* If a type id was specified and it differed to what we already have,
         * try to find it. If the type was deleted, disregard it.
         */
        if ($partial['typeId'] ?? false) {
            if ((int)$partial['typeId'] !== (int)$record->getType()->getId()) {
                try {
                    $record->setType($this->pastoralTypeMapper->findById($partial['typeId']));
                } catch (MapperException $e) {
                    /* noop: either not found, or it was deleted
                     * Leave current setting unchanged
                     */
                }
            }
        }

        /* If a subtype id was specified and it differed to what we already have,
         * try to find it. If the subtype was deleted, disregard it.
         */
        if ($partial['subtypeId'] ?? false) {
            if ((int)$partial['subtypeId'] !== (int)$record->getSubType()->getId()) {
                try {
                    $record->setSubType($this->pastoralSubTypeMapper->findById($partial['subtypeId']));
                } catch (MapperException $e) {
                    /* noop: either not found, or it was deleted
                     * Leave current setting unchanged
                     */
                }
            }
        }

        /* If a severity id was specified and it differed to what we already have,
         * try to find it. If the severity was deleted, disregard it.
         */
        if ($partial['severityId'] ?? false) {
            if ((int)$partial['severityId'] !== (int)$record->getSeverity()->getId()) {
                try {
                    $record->setSeverity($this->pastoralSeverityMapper->findById($partial['severityId']));
                } catch (MapperException $e) {
                    /* noop: either not found, or it was deleted
                     * Leave current setting unchanged
                     */
                }
            }
        }

        /* This has to be in one of the rfc formats or the locale's format
         * which might change per instance
         */
        $locale = new \Alaress_Date_Locale_Config();

        if ($partial['expiresAt'] ?? false) {
            $expiresAt = new \Alaress_Date_Format(
                $partial['expiresAt'],
                [
                    $locale->localFormat(),
                    // API docs state we accept RFC3339 without qualification, so we must accept both variants.
                    \DateTimeInterface::RFC3339,
                    \DateTimeInterface::RFC3339_EXTENDED
                ]
            );

            $record->setExpiresAt($expiresAt);
        }

        if ($partial['occurredOn'] ?? false) {
            $occurredOn = new \Alaress_Date_Format(
                $partial['occurredOn'],
                [
                    $locale->localFormat(),
                    // API docs state we accept RFC3339 without qualification, so we must accept both variants.
                    \DateTimeInterface::RFC3339,
                    \DateTimeInterface::RFC3339_EXTENDED
                ]
            );

            $record->setOccurredOn($occurredOn);
        }

        /* Allow token replacements in Confidential
         */
        if ($partial['body_private'] ?? false) {
            $record->setBodyPrivate((new TokenReplacer\Student(new TokenReplacer\Simple()))
                ->replace(trim($partial['body_private']), $record->getStudent()));
        }

        /* Allow token replacements in General
         */
        if ($partial['body_public'] ?? false) {
            $record->setBodyPrivate((new TokenReplacer\Student(new TokenReplacer\Simple()))
                ->replace(trim($partial['body_public']), $record->getStudent()));
        }

        if ($partial['actions'] ?? false) {
            /* There's a config option which, if enabled, allows you to mutate pastoral actions;
             * otherwise, leave everything unchanged I suppose
             */
            if ($this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                /* Spawn a Values instance to make this compatible with existing method which does a lot
                 */
                $newRecordActions = $this->prepareRecordActions(new Values($partial));

                $this->updateActions($record, $newRecordActions);
            }
        }

        /* Such a strange mix of philosophies in this place, it does my head in
         * The same idea is implemented in such subtly different ways everywhere here
         */
        if (($partial['tags'] ?? false) || ($partial['tagNames'] ?? false)) {
            /* At least one of these had data:
             * if you supplied both, merge and treat synonymously
             */
            $tags = $this->prepareTags($partial['tags'] ?? '', $partial['tagNames'] ?? '');

            // Save pastoral tags
            $this->pastoralTagMapper->deleteForRecord($record);
            foreach ($tags as $tag) {
                $this->pastoralTagMapper->assignToRecord($record, $tag);
            }
            $record->setTags($tags);
        }

        /* Role and Group selections are both processed in the same method.
         * If either was specified in $partial then maybe we have some work to do
         */
        if (($partial['role'] ?? false) || ($partial['group'] ?? false) || ($partial['user'] ?? false)) {
            /* By this point, types and subtypes were already updated in $record which
             * is a circumstantial sequential dependency that we happen to fulfil...
             */
            $type = $record->getType();
            $subtype = $record->getSubtype();

            /* Accesses depend on types and subtypes, somehow
             */
            $accesses = (new VisibilityTranslator(
                $this->pastoralAccessMapper,
                $this->pastoralGroupMapper,
                $this->userMapper,
                \Schoolbox\Pastoral\Access\Model::class
            ))->prepareAccesses(new Values($partial), $subtype ?? $type);

            /* A somewhat-different reimplementation from the one in processPastoralRecords() because
             * we don't need $previousRecord afaict
             */
            $record->setAccess($this->pastoralAccessMapper->createCollection());

            /** @var PastoralAccessModel $access */
            foreach ($accesses as $access) {
                $recordAccess = clone $access;
                $recordAccess->setPastoral($record);
                print_r($recordAccess);
                $this->pastoralAccessMapper->save($recordAccess);
                $record->getAccess()->add($recordAccess);
            }
        }


    }


    /**
     * Perform a mutative in-place update of a pastoral record's Actions collection, mutating the
     * db whenever we add a new action or remove an existing action.
     *
     * @param Model $record
     * @param Collection $newRecordActions
     * @return void
     * @throws MapperException
     */
    private function updateActions(PastoralModel $record, Collection $newRecordActions): void
    {
        // Save actions, record actions are compared against luAction id rather than recordAction id.
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


    /**
     * Create and/or update student records, based on the content of a form.
     *
     * @param User               $user                The user who is creating the record
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
        ?PastoralModel $parent = null
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
            /* Note that at this point, $record might not actually have a subtype instance: it was deleted
             * via another tab or multiplayer schoolbox, or $record is a whole new instance that's trying to
             * assign a deleted subtype (a new $record instance won't have a subtype at this point)
             *
             * This throws what seems to be a pre-existing message that sounds (for its era)
             * like something that's user-facing.
             */
            if ($subtype && $subtype->isDeleted() && ($record->getSubType()?->getId() !== $subtype->getId())) {
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
                        throw new SchoolboxException("Occurred On must be in the format of {$locale->localShortFormat()}");
                    }
                }
            }

            if ($post->offsetExists('expiresAt')) {
                if ((string)$post->get('expiresAt') === '') {
                    $record->setExpiresAt(null);
                } else {
                    try {
                        $expiresAt = \Alaress_Date_Format::newLocalShort($post->get('expiresAt'))->startOfDay();
                        $record->setExpiresAt($expiresAt);
                    } catch (\Exception $e) {
                        $locale = new \Alaress_Date_Locale_Config();
                        throw new SchoolboxException("Expires At must be in the format of {$locale->localShortFormat()}");
                    }
                }
            }

            if ($post->getFloat('points') === null) {
                $record->setPoints(null);
            } else {
                $points = $post->getFloat('points');

                /* Assert correct value range
                 */
                if (!is_numeric($post->get('points')) || $points < self::POINTS_MIN || $points > self::POINTS_MAX) {
                    throw new SchoolboxException(sprintf(
                        'Points must be a float between %s and %s',
                        self::POINTS_MIN,
                        self::POINTS_MAX
                    ));
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
                $this->updateActions($record, $newRecordActions);
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
                        // Send it to Sentry I suppose: there's nothing that
                        // the end user may do with this information.
                        /** @noinspection PhpExpressionResultUnusedInspection */
                        $this->sentry->captureException($e);
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
    public function prepareTags(string $tagIdCsv, string $tagNameCsv): GenericCollection
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
