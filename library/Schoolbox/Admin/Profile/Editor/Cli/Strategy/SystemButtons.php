<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli\Strategy;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Profile\Editor\Cli\MigrateButtonsTool;

class SystemButtons
{
    /**
     * Corresponds to a secondary identifier in the schema so that we 
     * can figure out custom buttons from system buttons, and also when
     * schools fill their buttons with junk, we'll be able to discern which
     * row is the ePortfolio button and so on.
     *
     * As we add system buttons, we can just add a new row; we don't
     * have to change the table schema, allowing us to make additions
     * in master. Otherwise we would be prohibited due to the proscription
     * on changing db schema in master releases.
     */
    private const SYSTEM_EPORTFOLIO = 'eportfolio';
    private const SYSTEM_DUEWORK = 'duework';


    /**
     * Constructor.
     *
     * @param IConnector $db
     * @param LoggerInterface $log
     * @param array $roletypeLookup
     */
    public function __construct(
        private IConnector $db,
        private LoggerInterface $log,
        private array $roletypeLookup
    ) {
    }


    /**
     * Create system buttons as profile buttons
     *
     * @return void
     */
    public function migrate(): void
    {
        /* Get a list of buttons to inspect - users can specify --system,
         * --custom, or both
         */
        $candidates = $this->generateCandidates($this->roletypeLookup);
    }


    /**
     * These were hardcoded into each of our templates; we'll migrate them into
     * the main profile_button system just like the custom buttons
     *
     * @return array
     */
    private function generateCandidates(): array
    {
        $candidates = [
            MigrateButtonsTool::ROLETYPE_STUDENT => [
                [
                    1,
                    'ePortfolio',
                    'ePortfolio',
                    'eportfolio',
                    '/eportfolio/[profileId]',  // named confusingly, this is the viewee's user.id
                    null,
                    null,
                    self::SYSTEM_EPORTFOLIO,
                    MigrateButtonsTool::ROLETYPE_STUDENT
                ],
                [
                    1,
                    'Due Work',
                    'ePortfolio',
                    'eportfolio',
                    '/eportfolio/[profileId]',  // named confusingly, this is the viewee's user.id
                    null,
                    null,
                    self::SYSTEM_EPORTFOLIO,
                    MigrateButtonsTool::ROLETYPE_STUDENT
                ],
            ],
            MigrateButtonsTool::ROLETYPE_STAFF => [],
            MigrateButtonsTool::ROLETYPE_PARENT => [],
        ];

        return $candidates;
    }
}
