<?php

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\User;

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Calendar_Api_EventType_Fixtures_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * The Results section of a matchup's description, as per the modal
     * The results are optional, and can be empty for either home team or away team, or both
     */
    private const RESULT_TEMPLATE = '
        <h3>Result</h3>
        <p>%s</p>
        <p>%s</p>
    ';


    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * @var \Schoolbox\Fixtures\Matchup\Mapper
     */
    private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Player\Mapper
     */
    private \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper;


    /**
     * @inheritdoc
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper,
        \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper
    ) {
        parent::__construct($connectorId, $user, $viewer);

        $this->eventTypeMapper = $eventTypeMapper;
        $this->matchupMapper = $matchupMapper;
        $this->teamPlayerMapper = $teamPlayerMapper;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return \Calendar\Event\Collection
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection
    {
        $stack = new \Calendar\Event\Collection();

        // Figure out event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $eventType = null;
        }

        /* Ensure $eventType is valid, finding first available one if it is invalid.
         * If you can't find one, we can't assign anything to the events from this subscription
         * so just return the empty stack; there's no way we could categorise those events
         *
         * getLuEventType() can return a deleted event type identifier.
         */
        $eventType = $this->eventTypeMapper->findFirstMeaningfulId($eventType);

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$eventType, $allowedEventTypes, true)) {
                return $stack;
            }
        }

        $eventColor = null;

        try {
            /** @var EventType $eventTypeObject */
            $eventTypeObject = $this->eventTypeMapper->findById($eventType);
            $eventColor      = $eventTypeObject->getColor();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }

        /* Find the list of matches that $user is somehow involved in,
         * and also get a list of teams that $user is somehow involved in
         * This is used to cross reference, sow e can generate a link to a
         * results screen which is team-specific (findInvolvedMatchups()
         * won't tell you which team you're involved with specifically)
         */
        $matchups = $this->matchupMapper->findInvolvedMatchups($this->getUser());
        $involvedTeamIds = $this->teamPlayerMapper->findInvolvedTeamIds($this->getUser());

        /** @var \Schoolbox\Fixtures\Matchup\Model $matchup */
        foreach ($matchups as $matchup) {
            $instance = new \Calendar\Event\Type\Fixtures\Event();

            /* Venues are now optional in matchups
             * Only try to represent it meaningfully if it actually existed
             */
            $location = '';

            if (null !== $matchup->getVenue()) {
                $location = $matchup->getVenue()->getName() . ($matchup->getVenue()->getAddress() ? ' (' . $matchup->getVenue()->getAddress() . ')' : '');
            }

            /* Detail is an aggregation of multiple pieces
             */
            $detail = $matchup->getNotes();

            $homeTeamResult = $matchup->getHomeTeamResult() ? $matchup->getHomeTeamResult()->getDescription() : '';
            $awayTeamResult = $matchup->getAwayTeamResult() ? $matchup->getAwayTeamResult()->getDescription() : '';

            if ($homeTeamResult || $awayTeamResult) {
                $detail .= sprintf(self::RESULT_TEMPLATE,
                    $homeTeamResult,
                    $awayTeamResult
                );
            }

            $instance->setConnectorId($this->getConnectorId());
            $instance->setTitle($matchup->getName());
            $instance->setStartTime($matchup->getStartAt());
            $instance->setEndTime($matchup->getFinishAt());
            $instance->setAllDay(false);
            $instance->setLocation($location);
            $instance->setColour($eventColor);
            $instance->setType($eventType);
            $instance->setMatchup($matchup);
            $instance->setDetail($detail);
            $instance->setInvolvedTeamIds($involvedTeamIds);

            $stack->add($instance);
        }

        return $stack;
    }


    /**
     * Find a particular event from this source
     * This implementation of getEvent is actually used
     * (by /www/calendar/ajax/create.php, to display a new event)
     *
     * @param int $eventId
     * @return \Calendar\Event\Collection
     */
    public function getEvent($eventId)
    {
        /* TODO I don't think this is used anymore...
         */
        return new \Calendar\Event\Collection();
    }
}
