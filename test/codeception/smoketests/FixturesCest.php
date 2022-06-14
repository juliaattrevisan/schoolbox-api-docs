<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace smoketests;

use IntegrationTester;
use Payload;
use Schoolbox\Collection;
#use React\Tests\Socket\IntegrationTest;

class FixturesCest
{
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin(); #we logged in as alaress
        $i->stopFollowingRedirects();
    }

    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/ with post request to create new season.
     *
     * @param IntegrationTester $i
     */
    public function createSeason(IntegrationTester $i): void
    {
        $timestamp = time();
        $seasonName = 'Auto Test Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', '/fixtures/seasons');
    }

    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/<id> to edit a existing season.
     *
     * This test create a new season and then sends the request to edit the season.
     *
     * @param IntegrationTester $i
     */
    public function updateSeason(IntegrationTester $i): void
    {
        $timestamp = time();
        $seasonName = 'Auto Test Update Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);

        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);

        $seasonName = 'Auto Test Season Updated ' . $timestamp;
        $updateSeason = new Payload\Fixtures\Seasons\Update($newSeason);
        $updateSeason->setFormParam('name', $seasonName);
        $i->sendRequest($updateSeason);

        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', '/fixtures/seasons');
    }

    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/<id>/archive to archive an existing season.
     *
     * This test create a new season and then sends the request to archive the newly created season.
     *
     * @param IntegrationTester $i
     */
    public function archiveSeason(IntegrationTester $i): void
    {
        $timestamp = time();
        $seasonName = 'Auto Test Archive Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);

        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);

        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::ARCHIVE);
        $i->sendRequest($archivePayload);

        $i->seeResponseCodeIs(200);
    }

    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/<id>/unArchive to restore an existing archived season.
     *
     * This test create a new season and archive it. Then sends the request to resotre the archived season.
     *
     * @param IntegrationTester $i
     */
    public function restoreSeason(IntegrationTester $i): void
    {
        $timestamp = time();
        $seasonName = 'Auto Test Restore Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);

        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);

        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::ARCHIVE);
        $i->sendRequest($archivePayload);

        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::RESTORE);
        $i->sendRequest($archivePayload);

        $i->seeResponseCodeIs(200);
    }

    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/<id>/delete to delete a newly created season.
     *
     * This test creates a new season then deletes it.
     *
     * @param IntegrationTester $i
     */
    public function deleteSeason(IntegrationTester $i): void
    {
        $timestamp = time();
        $seasonName = 'Auto Test Delete Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);

        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);

        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::DELETE);
        $i->sendRequest($archivePayload);

        $i->seeResponseCodeIs(200);
    }

    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createStatus(IntegrationTester $i): void
    {
        $timestamp = time();
        $statusName = 'AutoTest SCreate ' . $timestamp;
        $newStatus = new Payload\Fixtures\Status\Create($statusName);

        $i->sendRequest($newStatus);

        $i->seeResponseCodeIs(200);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateStatus(IntegrationTester $i): void
    {
        $timestamp = time();
        $statusName = 'AutoTest SChangeMe ' . $timestamp;

        $newStatus = new Payload\Fixtures\Status\Create($statusName);

        $i->sendRequest($newStatus);

        $statusMapper = \Schoolbox\Fixtures\Status\Mapper::getInstance();
        $statusModel = $statusMapper->findOneByColumn('name', $statusName);

        $updateStatus = new Payload\Fixtures\Status\Update($statusModel);

        $newStatusName = 'AutoTest SUpdate ' . $timestamp;
        $updateStatus->setName($newStatusName);

        $i->sendRequest($updateStatus);

        $i->seeResponseCodeIs(200);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteStatus(IntegrationTester $i): void
    {
        $timestamp = time();
        $statusName = 'AutoTest SDeleteMe ' . $timestamp;

        $newStatus = new Payload\Fixtures\Status\Create($statusName);

        $i->sendRequest($newStatus);

        $statusMapper = \Schoolbox\Fixtures\Status\Mapper::getInstance();
        $statusModel = $statusMapper->findOneByColumn('name', $statusName);

        $deleteStatus = new Payload\Fixtures\Status\Delete($statusModel);

        $i->sendRequest($deleteStatus);

        $i->seeResponseCodeIs(204);
    }

    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createVenue(IntegrationTester $i): void
    {
        $timestamp = time();
        $venueName = 'AutoTest SCreate ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateVenue(IntegrationTester $i): void
    {
        $timestamp = time();
        $venueName = 'AutoTest SChangeMe ' . $timestamp;

        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $updateVenue = new Payload\Fixtures\Venue\Update($venueModel);

        $newVenueName = 'AutoTest SUpdate ' . $timestamp;
        $updateVenue->setName($newVenueName);

        $i->sendRequest($updateVenue);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteVenue(IntegrationTester $i): void
    {
        $timestamp = time();
        $venueName = 'AutoTest SDeleteMe ' . $timestamp;

        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $deleteVenue = new Payload\Fixtures\Venue\Delete($venueModel);

        $i->sendRequest($deleteVenue);

        $i->seeResponseCodeIs(204);
    }


    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createExtSchool(IntegrationTester $i): void
    {
        $timestamp = time();
        $schoolName = 'AutoTest SCreate ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateExtSchool(IntegrationTester $i): void
    {
        $timestamp = time();
        $schoolName = 'AutoTest SChangeMe ' . $timestamp;

        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $updateSchool = new Payload\Fixtures\School\Update($schoolModel);

        $newSchoolName = 'AutoTest SUpdate ' . $timestamp;
        $updateSchool->setName($newSchoolName);

        $i->sendRequest($updateSchool);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteExtSchool(IntegrationTester $i): void
    {
        $timestamp = time();
        $schoolName = 'AutoTest SDeleteMe ' . $timestamp;

        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $deleteSchool = new Payload\Fixtures\School\Delete($schoolModel);

        $i->sendRequest($deleteSchool);

        $i->seeResponseCodeIs(200);
    }

    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createSport(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'AutoTest SportCreate ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateSport(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'AutoTest SportChangeMe ' . $timestamp;

        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $updateSport = new Payload\Fixtures\Sport\Update($sportModel);

        $updateSport->editPosition(3, 'Position 3 edited');
        $updateSport->deletePosition(2); //This will reduce the amount of positions thus making the names not match the position number
        $updateSport->addPosition('Added edited position'); // This will add a new position.

        $newSportName = 'AutoTest SportUpdate ' . $timestamp;
        $updateSport->setName($newSportName);

        $i->sendRequest($updateSport);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteSport(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'AutoTest SportDeleteMe ' . $timestamp;

        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $deleteSport = new Payload\Fixtures\Sport\Delete($sportModel);

        $i->sendRequest($deleteSport);

        $i->seeResponseCodeIs(204);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function createDivision(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Create Division Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Create Division Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Create ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function addPlayerDivision(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Add Player Division Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Add Player Division Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Add Player ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findOneByColumn('username', 'jreed');

        $collectionOfUsers = new \Schoolbox\Collection([$user]);

        $addPlayer = new Payload\Fixtures\Division\AddPlayer($divModel, $collectionOfUsers);

        $i->sendRequest($addPlayer);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function removePlayerDivision(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Remove Player Division Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Remove Player Division Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Remove Player ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findOneByColumn('username', 'jreed');

        $collectionOfUsers = new \Schoolbox\Collection([$user]);

        $addPlayer = new Payload\Fixtures\Division\AddPlayer($divModel, $collectionOfUsers);

        $i->sendRequest($addPlayer);

        $removePlayer = new Payload\Fixtures\Division\removePlayer($divModel, $user);

        $i->sendRequest($removePlayer);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateDivision(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Update Division Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Update Division Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division ChangeMe ' . $timestamp;
        $updatedName = 'AutoTest Division Update ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $updateDivision = new Payload\Fixtures\Division\Update($divModel);

        $updateDivision->setFormParam('name', $updatedName);

        $i->sendRequest($updateDivision);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteDivision(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Delete Division Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Delete Division Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Shouldn\'t Exist ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $deleteDivision = new Payload\Fixtures\Division\Delete($divModel);

        $i->sendRequest($deleteDivision);

        $i->seeResponseCodeIs(204);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function createTeam(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Create Team Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Create Team Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Create Team ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Create ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateTeam(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Update Team Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Update Team Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Update Team ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team CangeMe ' . $timestamp;
        $updateTeamName = 'AutoTest Team Update ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $updateTeam = new Payload\Fixtures\Team\Update($teamModel);
        $updateTeam->setFormParam('name', $updateTeamName);

        $i->sendRequest($updateTeam);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteTeam(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Update Team Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Update Team Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Division Update Team ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team I should\'t exist ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $updateTeam = new Payload\Fixtures\Team\Delete($teamModel);

        $i->sendRequest($updateTeam);

        $i->seeResponseCodeIs(204);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function addPlayerTeam(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Add Player Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Add Player Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Add Player ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Add Player ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findOneByColumn('username', 'jreed');

        $collectionOfUsers = new \Schoolbox\Collection([$user]);

        $addPlayer = new Payload\Fixtures\Division\AddPlayer($divModel, $collectionOfUsers);

        $i->sendRequest($addPlayer);

        $addPlayer = new Payload\Fixtures\Team\AddPlayer($teamModel, $user);

        $i->sendRequest($addPlayer);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function setPlayerPosition(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Set Pos Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        /** @var \Schoolbox\Fixtures\Sport\Model $sportModel */
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $positions = $sportModel->getPositions();

        $seasonName = 'Autotest Set Pos Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Set Pos ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Set Pos ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findOneByColumn('username', 'jreed');

        $collectionOfUsers = new \Schoolbox\Collection([$user]);

        $addPlayer = new Payload\Fixtures\Division\AddPlayer($divModel, $collectionOfUsers);

        $i->sendRequest($addPlayer);

        $addPlayer = new Payload\Fixtures\Team\AddPlayer($teamModel, $user);

        $i->sendRequest($addPlayer);

        /** @var \Schoolbox\Fixtures\Position\Model $onePosition **/
        $onePosition = $positions->first();

        $setPosition = new Payload\Fixtures\Division\setPosition($teamModel, $user, $onePosition);

        $i->sendRequest($setPosition);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function reorderPlayers(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest reorder player Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest reorder player Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div reorder player ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div reorder player ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user1 = $userMapper->findOneByColumn('username', 'jreed');
        $user2 = $userMapper->findOneByColumn('username', 'lsmith');

        $collectionOfUsers = new \Schoolbox\Collection([$user1, $user2]);

        $addPlayer = new Payload\Fixtures\Division\AddPlayer($divModel, $collectionOfUsers);

        $i->sendRequest($addPlayer);

        $addPlayer = new Payload\Fixtures\Team\AddPlayer($teamModel, $user1);

        $i->sendRequest($addPlayer);

        $addPlayer = new Payload\Fixtures\Team\AddPlayer($teamModel, $user2);

        $i->sendRequest($addPlayer);

        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        //For some reason the Team model doesn't have the players even though they are there and exist on the system.
        $reorderPlayers = new Payload\Fixtures\Team\reorderPlayers($teamModel);

        //So we have to do this manually.
        $reorderPlayers->manuallyPopulatePlayer($user1, 0);
        $reorderPlayers->manuallyPopulatePlayer($user2, 1);

        $reorderPlayers->swapPlayerAwithB(0, 1);

        $i->sendRequest($reorderPlayers);

        $i->seeResponseCodeIs(303);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function removePlayerTeam(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Remove Player Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Remove Player Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Remove Player ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Remove Player ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findOneByColumn('username', 'jreed');

        $collectionOfUsers = new \Schoolbox\Collection([$user]);

        $addPlayer = new Payload\Fixtures\Division\AddPlayer($divModel, $collectionOfUsers);

        $i->sendRequest($addPlayer);

        $addPlayer = new Payload\Fixtures\Team\AddPlayer($teamModel, $user);

        $i->sendRequest($addPlayer);

        $removePlayer = new Payload\Fixtures\Team\removePlayer($teamModel, $user);

        $i->sendRequest($removePlayer);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function createMatch(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Add Match Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Add Match Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Add Match ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Add Match ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $schoolName = 'AutoTest School Match Create ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $venueName = 'AutoTest Venue Match Create ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $matchName = 'AutoTest Match Create ' . $timestamp;

        $createMatch = new Payload\Fixtures\Matchup\Create($matchName, $divModel, $venueModel, $teamModel, $schoolModel);

        $i->sendRequest($createMatch);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateMatch(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Update Match Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Update Match Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Update Match ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Update Match ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $schoolName = 'AutoTest School Match Update ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $venueName = 'AutoTest Venue Match Update ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $matchName = 'AutoTest Match changeme ' . $timestamp;

        $createMatch = new Payload\Fixtures\Matchup\Create($matchName, $divModel, $venueModel, $teamModel, $schoolModel);

        $i->sendRequest($createMatch);

        $matchMapper = \Schoolbox\Fixtures\Matchup\Mapper::getInstance();
        $matchModel = $matchMapper->findOneByColumn('name', $matchName);

        $matchName = 'AutoTest Match Update ' . $timestamp;

        $updateMatch = new Payload\Fixtures\Matchup\Update($matchModel);

        $updateMatch->setFormParam('name', $matchName);

        $i->sendRequest($updateMatch);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteMatch(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Delete Match Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Delete Match Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Delete Match ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Delete Match ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $schoolName = 'AutoTest School Match Delete ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $venueName = 'AutoTest Venue Match Delete ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $matchName = 'AutoTest Match shouldn\'t exist ' . $timestamp;

        $createMatch = new Payload\Fixtures\Matchup\Create($matchName, $divModel, $venueModel, $teamModel, $schoolModel);

        $i->sendRequest($createMatch);

        $matchMapper = \Schoolbox\Fixtures\Matchup\Mapper::getInstance();
        $matchModel = $matchMapper->findOneByColumn('name', $matchName);

        $deleteMatch = new Payload\Fixtures\Matchup\Delete($matchModel);

        $i->sendRequest($deleteMatch);

        $i->seeResponseCodeIs(204);
    }


    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function createMatchResult(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Create Result Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Create Result Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Create Result ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Create Result ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $schoolName = 'AutoTest School Result Create ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $venueName = 'AutoTest Venue Result Create ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $matchName = 'AutoTest Match Result Create ' . $timestamp;

        $createMatch = new Payload\Fixtures\Matchup\Create($matchName, $divModel, $venueModel, $teamModel, $schoolModel);

        $i->sendRequest($createMatch);

        $matchMapper = \Schoolbox\Fixtures\Matchup\Mapper::getInstance();
        $matchModel = $matchMapper->findOneByColumn('name', $matchName);

        $createResult = new Payload\Fixtures\Matchup\Result\Create($matchModel, $teamModel);

        $i->sendRequest($createResult);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateMatchResult(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Update Result Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Update Result Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Update Result ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Update Result ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $schoolName = 'AutoTest School Result Update ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $venueName = 'AutoTest Venue Result Update ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $matchName = 'AutoTest Match Result Update ' . $timestamp;

        $createMatch = new Payload\Fixtures\Matchup\Create($matchName, $divModel, $venueModel, $teamModel, $schoolModel);

        $i->sendRequest($createMatch);

        $matchMapper = \Schoolbox\Fixtures\Matchup\Mapper::getInstance();
        $matchModel = $matchMapper->findOneByColumn('name', $matchName);

        $createResult = new Payload\Fixtures\Matchup\Result\Create($matchModel, $teamModel);

        $i->sendRequest($createResult);

        $resultMapper = \Schoolbox\Fixtures\Result\Mapper::getInstance();
        $resultModel = $resultMapper->findOneByColumn('match_id', $matchModel->getId());

        $updateResult = new Payload\Fixtures\Matchup\Result\Update($resultModel);

        //$updateResult->getFormParams('description', '<p> This is an example of a updated result </p>');

        $i->sendRequest($updateResult);

        $i->seeResponseCodeIs(303);
    }

    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteMatchResult(IntegrationTester $i): void
    {
        $timestamp = time();
        $sportName = 'Autotest Delete Result Team Div Sport ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);

        $i->sendRequest($newSport);

        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel = $sportMapper->findOneByColumn('name', $sportName);

        $seasonName = 'Autotest Delete Result Team Div Season ' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);

        $i->sendRequest($newSeason);

        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $seasonModel = $seasonMapper->findOneByColumn('name', $seasonName);

        $divisionName = 'AutoTest Team Div Delete Result ' . $timestamp;

        $newDivision = new Payload\Fixtures\Division\Create($divisionName, $sportModel, $seasonModel);

        $i->sendRequest($newDivision);

        $divMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $divModel = $divMapper->findOneByColumn('name', $divisionName);

        $teamName = 'AutoTest Team Div Delete Result ' . $timestamp;

        $newTeam = new Payload\Fixtures\Team\Create($teamName, $divModel);

        $i->sendRequest($newTeam);

        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
        $teamModel = $teamMapper->findOneByColumn('name', $teamName);

        $schoolName = 'AutoTest School Result Delete ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);

        $i->sendRequest($newSchool);

        $schoolMapper = \Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel = $schoolMapper->findOneByColumn('name', $schoolName);

        $venueName = 'AutoTest Venue Result Delete ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);

        $i->sendRequest($newVenue);

        $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel = $venueMapper->findOneByColumn('name', $venueName);

        $matchName = 'AutoTest Match Result Delete ' . $timestamp;

        $createMatch = new Payload\Fixtures\Matchup\Create($matchName, $divModel, $venueModel, $teamModel, $schoolModel);

        $i->sendRequest($createMatch);

        $matchMapper = \Schoolbox\Fixtures\Matchup\Mapper::getInstance();
        $matchModel = $matchMapper->findOneByColumn('name', $matchName);

        $createResult = new Payload\Fixtures\Matchup\Result\Create($matchModel, $teamModel);

        $i->sendRequest($createResult);

        $resultMapper = \Schoolbox\Fixtures\Result\Mapper::getInstance();
        $resultModel = $resultMapper->findOneByColumn('match_id', $matchModel->getId());

        $deleteResult = new Payload\Fixtures\Matchup\Result\Delete($resultModel);

        $i->sendRequest($deleteResult);

        $i->seeResponseCodeIs(204);
    }
}
