<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Event\Overrides\Applicator;
use Schoolbox\External\Event\Overrides\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * For a given event, find the final calculated list of users
 * which is the default users from sis_class, mutated by the
 * Overrides, then deduped.
 *
 * Only applies to the 'timetable' event classification
 */
class TimetableUsers
{
    /**
     * INVOLVEMENT_FUTURE_THRESHOLD is the number of weeks into the future to look
     * for involvements. You might not be a teacher of a class today,
     * but you might be some time later.
     *
     * INVOLVEMENT_PAST_THRESHOLD is the number of weeks into the past to look
     * for involvements. You might have been the teacher of a class
     * at some point in the past, but no longer
     *
     * INVOLVEMENT_PAST_THRESHOLD and INVOLVEMENT_FUTURE_THRESHOLD are both
     * defaulted to 1 week just because I said so. This gives you a span of
     * 2 weeks where your involvements are examined.
     *
     * We don't know your involvements outside of this interval.
     */
    public const INVOLVEMENT_PAST_THRESHOLD = -1;
    public const INVOLVEMENT_FUTURE_THRESHOLD = 1;


    /**
     * @var \Schoolbox\External\ClassEntryMapper
     */
    private \Schoolbox\External\ClassEntryMapper $classEntryMapper;


    /**
     * @var \Schoolbox\External\ClassInvolvementMapper
     */
    private \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var Applicator
     */
    private Applicator $applicator;


    /**
     * Constructor.
     *
     * @param ClassEntryMapper                         $classEntryMapper
     * @param ClassInvolvementMapper                   $classInvolvementMapper
     * @param \Schoolbox\External\Event\Mapper         $eventMapper
     * @param Mapper                                   $overrideMapper
     * @param \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
     */
    public function __construct(
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->eventMapper = $eventMapper;
        $this->overrideMapper = $overrideMapper;
        $this->applicator = new Applicator($staffProfileMapper);
    }


    /**
     * Finds all staff externalIds involved in $classCode between the two timestamps,
     * taking into account override mutations
     *
     * @param string $classCode
     * @param int    $startTimestamp
     * @param int    $endTimestamp
     * @return array
     */
    public function findTeachersForClassCode(string $classCode, int $startTimestamp, int $endTimestamp): array
    {
        return \Alaress\Redis::getCacheInstance()->cacheFunctionCall(
            'findTeachersForClassCode',
            function ($param) {
                [$classCode, $startTimestamp, $endTimestamp] = $param;

                /* Micromanage a stack of minimal event data with overrides
                 */
                $stack[$classCode] = [
                    'code' => $classCode,
                    'usuals' => [],
                    /* A list of people who survived all the mutations
                     * and transformations in this algorithm
                     */
                    'manifest' => [],
                ];

                /* Find the usual guys for the given class code out of sis_class_user
                 */
                $query = "
                    SELECT DISTINCT se.code, scu.*
                    FROM {$this->classInvolvementMapper->getTable()} scu
                    INNER JOIN {$this->classEntryMapper->getTable()} sc ON scu.class_id = sc.id
                    INNER JOIN {$this->eventMapper->getTable()} se ON sc.code = se.code
                    WHERE sc.code = :classCode
                    AND se.classification = :classification
                    ORDER BY sc.id ASC
                ";

                $statement = $this->eventMapper->getDatabase()->prepare($query);
                $statement->execute([
                    'classCode' => $classCode,
                    'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                ]);

                /* Pile it into the $stack in preparation for the mutations
                 */
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    if (!isset($stack[$row['code']])) {
                        $stack[$row['code']] = [
                            'code' => $row['code'],
                            'usuals' => [],
                            /* A list of people who survived all the mutations
                             * and transformations in this algorithm
                             */
                            'manifest' => [],
                        ];
                    }

                    /* Insert and dedupe
                     */
                    $stack[$row['code']]['usuals'][$row['external_id']] = $row['external_id'];
                }

                /* Get a list of events between $startTimestamp and $endTimestamp
                 * along with their overrides.
                 *
                 * Secondary ordering of overrides by their sequence _was_ done,
                 * but is no longer: this is because doing so created a temp
                 * table, significantly slowing the query. Instead, we now order
                 * overrides when processing the event list.
                 */
                $query = "
                    SELECT se.id, se.code, seou.external_id, seou.action, seou.seqeunce
                    FROM {$this->eventMapper->getTable()} se
                    INNER JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
                    WHERE se.code = :classCode
                    AND se.classification = :classification
                    AND se.start_at >= :from
                    AND se.start_at <= :to
                    ORDER BY se.id ASC
                ";

                $statement = $this->eventMapper->getDatabase()->prepare($query);
                $statement->execute([
                    'classCode' => $classCode,
                    'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                    'from' => \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase(),
                    'to' => \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase(),
                ]);

                /* Remember: overrides are generally optional so they might not even
                 * exist for $classCode which is why we get the Usual Guys first
                 *
                 * Assumption:
                 *   We can presume that sis_class_user data exists first simply because
                 *   that's how we chose to build things. To have overrides only, and
                 *   nothing for sis_class and sis_class_user, is a bit weird and is
                 *   probably a broken situation. I thought of making a manifest for
                 *   each sis_event, but the data size would be extremely big so we chose
                 *   not to do that and opted for this hybrid approach instead. In the
                 *   grand scheme of things it would have been easier to have that
                 *   manifest instead, but I was overridden
                 */
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $stack[$row['code']]['overrides'][$row['sequence']][] = [
                        'external_id' => $row['external_id'],
                        'action' => $row['action']
                    ];
                }

                /* Perform mutations
                 */
                $manifest = $this->applicator->apply(
                    $stack[$classCode]['usuals'] ?? [],
                    $this->reorderOverrides($stack[$classCode]['overrides'] ?? []),
                );

                /* Since we're explicitly looking for staff members in this method
                  * we can basically kick out everyone else who isn't, if they
                  * survived the override mutations
                  *
                  * If we haven't got this already, get it and remember it
                  * in case we needed it again and again and again
                  */
                return $this->applicator->staffOnly($manifest);
            },
            [$classCode, $startTimestamp, $endTimestamp],
            \Config::get('external_cache_timeout')
        );
    }


    /**
     * Generate a list of events in which $user is involved, between two timestamps
     * Also takes into account any event overrides
     *
     * @param User                   $user
     * @param int                    $startTimestamp
     * @param int                    $endTimestamp
     * @param GenericCollection|null $folderCodes
     * @return array
     * @throws \PDOException
     */
    public function findClassCodesForUser(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?GenericCollection $folderCodes = null): array
    {
        return \Alaress\Redis::getCacheInstance()->cacheFunctionCall(
            'findClassCodesForUser',
            function ($param) {
                [$user, $startTimestamp, $endTimestamp, $folderCodes] = $param;

                /* Micromanage a stack of minimal event data with overrides
                 */
                $lookup = [];
                $stack = [];

                /* Micromanage dates
                 */
                $from = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
                $to = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

                /* Find the usual guys for the given class code out of sis_class_user
                 * In this case, $user will be the only guy in the list since we don't
                 * actually care about anyone else. We only care when $user is one of
                 * a class' usual guys.
                 */
                $query = "
                    SELECT se.code, sc.name, scu.*
                    FROM {$this->classInvolvementMapper->getTable()} scu
                    INNER JOIN {$this->classEntryMapper->getTable()} sc ON scu.class_id = sc.id
                    INNER JOIN {$this->eventMapper->getTable()} se ON sc.code = se.code
                    WHERE scu.external_id = :externalId
                    AND se.classification = :classification
                    AND se.start_at >= :from
                    AND se.start_at <= :to
                    ORDER BY sc.id ASC
                ";

                $statement = $this->eventMapper->getDatabase()->prepare($query);
                $statement->execute([
                    'externalId' => $user->getExternalId(),
                    'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                    'from' => $from,
                    'to' => $to,
                ]);

                /* Pile it into the $lookup in preparation for the mutations across multiple
                 * instances of a sis_event. A class code could have different sets of humans
                 * involved on different days so you have to repeat the process for every
                 * sis_event you find
                 */
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    if (!isset($lookup[$row['code']])) {
                        $lookup[$row['code']] = [
                            'name' => $row['name'],
                            'code' => $row['code'],
                            /* The guys who are usually involved in this class
                             */
                            'usuals' => [],
                            /* A list of overrides index by event id since each event
                             * has its own list of overrides
                             */
                            'overrides' => [],
                            /* A list of people who survived all the mutations
                             * and transformations in this algorithm, for each event instance
                             */
                            'manifest' => [],
                        ];
                    }

                    /* Insert and dedupe
                     */
                    $lookup[$row['code']]['usuals'][$row['external_id']] = $row['external_id'];
                }

                /* Find all the events within the time interval specified, because $user
                 * could have been affected by any of their overrides - you won't know
                 * until you play through all the mutations.
                 *
                 * As with the query in `findTeachersForClassCode`: this query
                 * no longer has a secondary ordering on `sequence`.
                 */
                $query = "
                    SELECT se.code, se.title, se.id, seou.external_id, seou.action, seou.seqeunce
                    FROM {$this->eventMapper->getTable()} se
                    INNER JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
                    WHERE se.classification = :classification
                    AND se.start_at >= :from
                    AND se.start_at <= :to
                    ORDER BY se.id
                ";

                $statement = $this->eventMapper->getDatabase()->prepare($query);
                $statement->execute([
                    'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                    'from' => $from,
                    'to' => $to,
                ]);

                /* Collate all the overrides and group them so we can mutate everything
                 * in a convenient manner
                 */
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    if (!isset($lookup[$row['code']])) {
                        $lookup[$row['code']] = [
                            'name' => $row['title'],
                            'code' => $row['code'],
                            /* The guys who are usually involved in this class
                             */
                            'usuals' => [],
                            /* A list of overrides index by event id since each event
                             * has its own list of overrides
                             */
                            'overrides' => [],
                            /* A list of people who survived all the mutations
                             * and transformations in this algorithm, for each event instance
                             */
                            'manifest' => [],
                        ];
                    }

                    /* Manufacture a list of overrides for each event id
                     */
                    $lookup[$row['code']]['overrides'][$row['id']][$row['sequence']][] = [
                        'external_id' => $row['external_id'],
                        'action' => $row['action']
                    ];
                }

                /* For every thing we found in $lookup,
                 * Play back all the overrides against $usuals
                 * then collate the list of surviving guys in $manifest
                 */
                foreach ($lookup as $classCode => &$data) {
                    foreach ($data['overrides'] as $eventId => $overrides) {
                        $manifest = $this->applicator->apply(
                            $data['usuals'],
                            $this->reorderOverrides($overrides),
                        );

                        /* When we use this to calculate My Subjects in the side menu
                         * it's actually a union of the usual guys and the survivors
                         * of the mutations. This does not affect timetables, just
                         * folder access and shortcuts.
                         */
                        $superposition = $manifest + $data['usuals'];

                        $data['manifest'][$eventId] = $superposition;

                        /* If you were involved in this class at any point within the
                         * time interval specified, I suppose you can pile its class code
                         * into the stack and skip processing other overrides - we don't
                         * need to investigate further, since we know you're involved at
                         * least once.
                         */
                        if (isset($data['manifest'][$eventId][$user->getExternalId()])) {
                            $stack[$data['code']] = [
                                'name' => $data['name'],
                                'code' => $data['code'],
                            ];

                            break;
                        }
                    }
                }

                unset($data);

                return array_values($stack);
            },
            [$user, $startTimestamp, $endTimestamp, $folderCodes],
            \Config::get('external_cache_timeout')
        );
    }


    /**
     * Generate a list of events in which $user is involved, between two timestamps
     * Also takes into account any event overrides
     *
     * @param User                   $user
     * @param int                    $startTimestamp
     * @param int                    $endTimestamp
     * @param GenericCollection|null $folderCodes
     * @return GenericCollection
     * @throws InvalidColumnException
     * @throws \PDOException
     * @throws Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function findForUser(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?GenericCollection $folderCodes = null): GenericCollection
    {
        if (!$user->getExternalId()) {
            return $this->eventMapper->createCollection();
        }

        $eventIds = \Alaress\Redis::getCacheInstance()->cacheFunctionCall(
            'findForUser',
            function ($param) {
                [$user, $startTimestamp, $endTimestamp, $folderCodes] = $param;

                $startDatetime  = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
                $finishDatetime = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

                /* Micromanage a stack of minimal event data with overrides
                 */
                $stack = [];

                /* Events where $user is somehow relevant today
                 */
                $eventIds = [];

                /* Find the usual guys for the sis_events occurring between the two timestamps
                 */
                $query = "
                    SELECT DISTINCT se.id AS eventId, se.code, scu.*
                    FROM {$this->classInvolvementMapper->getTable()} scu
                    INNER JOIN {$this->classEntryMapper->getTable()} sc ON scu.class_id = sc.id
                    INNER JOIN {$this->eventMapper->getTable()} se ON sc.code = se.code
                    WHERE se.classification = :classification
                    AND se.start_at >= :from
                    AND se.start_at <= :to
                    AND scu.external_id = :externalId
                ";

                $params = [
                    'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                    'from'           => $startDatetime,
                    'to'             => $finishDatetime,
                    'externalId'     => $user->getExternalId(),
                ];

                if ($folderCodes && $folderCodes->count()) {
                    $bindings = Builder::getBindings($folderCodes->keys(), 'folderCode');
                    $query    .= " AND se.code IN ({$bindings->getPlaceholder()})";
                    $params   = array_merge($params, $bindings->getParams());
                }

                $query .= "ORDER BY sc.id ASC";

                $statement = $this->eventMapper->getDatabase()->prepare($query);
                $statement->execute($params);

                /* Pile it into the $stack in preparation for the mutations
                 */
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    if (!isset($stack[$row['eventId']])) {
                        $stack[$row['eventId']] = [
                            'code'      => $row['code'],
                            'usuals'    => [],
                            /* A list of overrides index by event id since each event
                             * has its own list of overrides
                             */
                            'overrides' => [],
                            /* A list of people who survived all the mutations
                             * and transformations in this algorithm
                             */
                            'manifest'  => [],
                        ];
                    }

                    /* Insert and dedupe
                     */
                    $stack[$row['eventId']]['usuals'][$row['external_id']] = $row['external_id'];
                }

                /* Find all events which have overrides, and occurring between $startDatetime
                 * and $finishDatetime timestamps. You can't tell how $externalId will be
                 * affected by existing mutations such as CLEAR and CLEAR_STAFF unless you
                 * do this and apply the mutations.
                 */
                $rows = $this->findCommonOverrides($startDatetime, $finishDatetime, $folderCodes);

                /* Remember: overrides are generally optional so they might not even
                 * exist for $classCode which is why we get the Usual Guys first
                 *
                 * Assumption:
                 *   We can presume that sis_class_user data exists first simply because
                 *   that's how we chose to build things. To have overrides only, and
                 *   nothing for sis_class and sis_class_user, is a bit weird and is
                 *   probably a broken situation. I thought of making a manifest for
                 *   each sis_event, but the data size would be extremely big so we chose
                 *   not to do that and opted for this hybrid approach instead. In the
                 *   grand scheme of things it would have been easier to have that
                 *   manifest instead, but I was overridden
                 */
                foreach ($rows as $row) {
                    /* Some events might contain only overrides so if the struct
                     * never existed before, reinit it here
                     *
                     * Sometimes a class consists purely of overrides
                     * e.g. no students, just a non-regular teacher
                     * In that case $data['usuals'] is not set
                     */
                    if (!isset($stack[$row['eventId']])) {
                        $stack[$row['eventId']] = [
                            'code'      => $row['code'],
                            'usuals'    => [],
                            /* A list of overrides index by event id since each event
                             * has its own list of overrides
                             */
                            'overrides' => [],
                            /* A list of people who survived all the mutations
                             * and transformations in this algorithm
                             */
                            'manifest'  => [],
                        ];
                    }

                    $stack[$row['eventId']]['overrides'][$row['sequence']][] = [
                        'external_id' => $row['external_id'],
                        'action'      => $row['action'],
                    ];
                }

                /* Perform mutations
                 */
                foreach ($stack as $eventId => &$data) {
                    $data['manifest'] = $this->applicator->apply(
                        $data['usuals'],
                        $this->reorderOverrides($data['overrides']),
                    );

                    /* Only care about events the current user is in today
                     */
                    if (array_key_exists($user->getExternalId(), $data['manifest'])) {
                        $eventIds[$eventId] = $data['manifest'];
                    }
                }

                return $eventIds;
            },
            [$user, $startTimestamp, $endTimestamp, $folderCodes],
            \Config::get('external_cache_timeout')
        );

        return $this->eventMapper->findWithCheapStaff($eventIds);
    }


    /**
     * Find all the overrides for events occurring between the two timestamps
     *
     * @param string                 $startDatetime
     * @param string                 $finishDatetime
     * @param GenericCollection|null $folderCodes
     * @return array
     */
    private function findCommonOverrides(string $startDatetime, string $finishDatetime, ?GenericCollection $folderCodes = null): array
    {
        return \Alaress\Redis::getCacheInstance()->cacheFunctionCall(
            'findCommonOverrides',
            function ($param) {
                [$startDatetime, $finishDatetime, $folderCodes] = $param;
                $stack = [];

                /* Get a list of events between $startTimestamp and $endTimestamp
                 * along with a list of their overrides.
                 *
                 * As with the query in `findTeachersForClassCode`: this query
                 * no longer has a secondary ordering on `sequence`.
                 */
                $query = "
                    SELECT se.id AS eventId, se.code, seou.external_id, seou.action, seou.seqeunce
                    FROM {$this->eventMapper->getTable()} se
                    INNER JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
                    WHERE se.classification = :classification
                    AND se.start_at >= :from
                    AND se.start_at <= :to
                ";

                $params = [
                    'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                    'from'           => $startDatetime,
                    'to'             => $finishDatetime,
                ];

                if ($folderCodes && $folderCodes->count()) {
                    $bindings = Builder::getBindings($folderCodes->keys(), 'folderCode');
                    $query    .= " AND se.code IN ({$bindings->getPlaceholder()}) ";
                    $params   = array_merge($params, $bindings->getParams());
                }

                $query .= " ORDER BY se.id ASC";

                $statement = $this->eventMapper->getDatabase()->prepare($query);
                $statement->execute($params);

                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $stack[] = $row;
                }

                return $stack;
            },
            [$startDatetime, $finishDatetime, $folderCodes],
            \Config::get('external_cache_timeout')
        );
    }

    /**
     * Reorder overrides, provided as an array of arrays of overrides keyed by
     * sequence number, into a single ordered list of overrides.
     *
     * @param array{int, array<array{external_id: string, action: string}>} $overrides key: sequence number,
     *                                                                                 value: a list of overrides.
     * @return array<array{external_id: string, action: string}>
     */
    private function reorderOverrides(array $overrides): array
    {
        $reorderedOverrides = [];
        ksort($overrides, SORT_NUMERIC);
        foreach ($overrides as $overridesBySequence) {
            $reorderedOverrides = array_merge($reorderedOverrides, $overridesBySequence);
        }
        return $reorderedOverrides;
    }
}
