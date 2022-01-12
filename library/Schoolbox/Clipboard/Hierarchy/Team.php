<?php
/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;

/**
 * Import Clipboard teams into schoolbox
 *
 * Clipboard Category/Subcategory equates to schoolbox Divisions which are
 * created progressively
 */
class Team implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Codec\Division
     */
    private Codec\Division $divisionCodec;


    /**
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param IApi            $api
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->divisionCodec = new Codec\Division();
    }


    /**
     * @return void
     */
    public function import(): void
    {
        /* Set up a local cache for (sport, division, team) tuples
         */
        $lookup = [];

        $teams = $this->api->retrieve('GET', 'teams', []);

        foreach ($teams['data'] as $team) {
            $teamCategory = $team['category']['name'] ?? '';
            $teamSubcategory = $team['subcategory']['name'] ?? '';
            $teamActivity = $team['activity']['name'] ?? '';

            /* Try to manufacture a division title
             */
            $divisionName = $this->divisionCodec->encode($teamCategory, $teamSubcategory, $teamActivity);

            $this->log->debug(sprintf(
                'Encoded category/subcategory/activity (%s, %s, %s) into schoolbox division name: %s',
                $teamCategory,
                $teamSubcategory,
                $teamActivity,
                $divisionName
            ));

            /* TODO: Spawn a Sport if it doesn't exist
             *  or reuse an existing one if it existed
             */

            /* TODO: Spawn a Division if it doesn't exist
             *  or reuse an existing one if it existed
             */
        }
    }
}
