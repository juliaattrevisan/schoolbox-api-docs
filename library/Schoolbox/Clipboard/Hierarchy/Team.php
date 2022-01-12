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
     * @var \Schoolbox\Fixtures\Season\Mapper
     */
    private \Schoolbox\Fixtures\Season\Mapper $seasonMapper;


    /**
     * @var \Schoolbox\Fixtures\Sport\Mapper
     */
    private \Schoolbox\Fixtures\Sport\Mapper $sportMapper;


    /**
     * @var \Schoolbox\Fixtures\Division\Mapper
     */
    private \Schoolbox\Fixtures\Division\Mapper $divisionMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Mapper
     */
    private \Schoolbox\Fixtures\Team\Mapper $teamMapper;


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

        $this->seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $this->sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $this->divisionMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $this->teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
    }


    /**
     * @return void
     */
    public function import(): void
    {

        /* TODO: I guess have a yearlong season
         */
        $season = $this->seasonMapper->findById(1);

        /* Set up a local cache for (sport, division, team) tuples
         */
        $lookup = [];

        $teams = $this->api->retrieve('GET', 'teams', []);

        foreach ($teams['data'] as $team) {
            $teamActivity = $team['activity']['name'] ?? '';
            $teamCategory = $team['category']['name'] ?? '';
            $teamSubcategory = $team['subcategory']['name'] ?? '';
            $teamName = $team['name'];

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

            /* Try to spawn a corresponding Sport instance
             */
            $sportName = $team['activity']['name'];

            if (!isset($lookup[$sportName])) {
                try {
                    $sport = $this->sportMapper->findOneByColumn('name', $sportName);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $sport = $this->sportMapper->createModel()
                        ->setName($sportName);

                    $this->sportMapper->save($sport);
                }

                $lookup[$sportName] = [
                    'instance' => $sport,
                    'divisions' => []
                ];
            } else {
                $sport = $lookup[$sportName]['instance'];
            }

            /* Try to spawn a corresponding Division instance
             */
            if (!isset($lookup[$sportName]['divisions'][$divisionName])) {
                try {
                    $division = $this->divisionMapper->findOneByColumnList([
                        'name' => $divisionName,
                        'sport_id' => $sport->getId()
                    ]);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $division = $this->divisionMapper->createModel()
                        ->setName($divisionName)
                        ->setSeason($season)
                        ->setSport($sport);

                    $this->divisionMapper->save($division);
                }

                $lookup[$sportName]['divisions'][$divisionName] = [
                    'instance' => $division,
                    'teams' => []
                ];
            } else {
                $division = $lookup[$sportName]['divisions'][$divisionName]['instance'];
            }

            /* Try to spawn a corresponding Team instance
             */
            if (!isset($lookup[$sportName]['divisions'][$divisionName]['teams'][$teamName])) {
                try {
                    $team = $this->teamMapper->findOneByColumnList([
                        'name' => $teamName,
                        'division_id' => $division->getId()
                    ]);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $team = $this->teamMapper->createModel()
                        ->setName($teamName)
                        ->setDivision($division);

                    $this->teamMapper->save($team);
                }

                $lookup[$sportName]['divisions'][$divisionName]['teams'][$teamName]['instance'] = $team;
            } else {
                $team = $lookup[$sportName]['divisions'][$divisionName]['teams'][$teamName]['instance'];
            }

            $this->log->debug(sprintf(
                'Sport: %s %s, Division: %s %s, Team: %s %s',
                $sport->getId(),
                $sport->getName(),
                $division->getId(),
                $division->getName(),
                $team->getId(),
                $team->getName()
            ));
        }
    }
}

