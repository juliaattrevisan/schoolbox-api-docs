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
        $timestamp=time();
        $seasonName = 'Auto Test Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);
        
        $i->seeResponseCodeIs('303');
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
        $timestamp=time();
        $seasonName = 'Auto Test Update Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);
        
        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);
        
        $seasonName = 'Auto Test Season Updated ' . $timestamp;
        $updateSeason = new Payload\Fixtures\Seasons\Update($newSeason);
        $updateSeason->setFormParam('name', $seasonName);
        $i->sendRequest($updateSeason);
        
        $i->seeResponseCodeIs('303');
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
        $timestamp=time();
        $seasonName = 'Auto Test Archive Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);
        
        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);
        
        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::ARCHIVE);
        $i->sendRequest($archivePayload);
        
        $i->seeResponseCodeIs('200');
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
        $timestamp=time();
        $seasonName = 'Auto Test Restore Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);
        
        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);
        
        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::ARCHIVE);
        $i->sendRequest($archivePayload);
        
        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::RESTORE);
        $i->sendRequest($archivePayload);
        
        $i->seeResponseCodeIs('200');
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
        $timestamp=time();
        $seasonName = 'Auto Test Delete Season' . $timestamp;
        $newSeason = new Payload\Fixtures\Seasons\Create($seasonName);
        $i->sendRequest($newSeason);
        
        $SeasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $newSeason = $SeasonMapper->findOneByColumn('name', $seasonName);
        
        $archivePayload = new Payload\Fixtures\Seasons\ChangeState($newSeason, Payload\Fixtures\Seasons\ChangeState::DELETE);
        $i->sendRequest($archivePayload);
        
        $i->seeResponseCodeIs('200');
    }
    
    /**
     * Request URL:
     * 
     * 
     * @param IntegrationTester $i
     */
    public function createStatus(IntegrationTester $i): void
    {
        $timestamp=time();
        $statusName= 'AutoTest SCreate ' . $timestamp;
        $newStatus = new Payload\Fixtures\Status\Create($statusName);
        
        $i->sendRequest($newStatus);
        
        $i->seeResponseCodeIs('200');
    }
    
    /**
     * Request URL:
     * 
     * @param IntegrationTester $i
     */
    public function updateStatus(IntegrationTester $i): void
    {
        $timestamp=time();
        $statusName= 'AutoTest SChangeMe ' . $timestamp;
        
        $newStatus = new Payload\Fixtures\Status\Create($statusName);
        
        $i->sendRequest($newStatus);
        
        $statusMapper=\Schoolbox\Fixtures\Status\Mapper::getInstance();
        $statusModel=$statusMapper->findOneByColumn('name', $statusName);
        
        $updateStatus = new Payload\Fixtures\Status\Update($statusModel);
        
        $newStatusName= 'AutoTest SUpdate ' . $timestamp;
        $updateStatus->setName($newStatusName);
        
        $i->sendRequest($updateStatus);
        
        $i->seeResponseCodeIs('200');
        
    }
    
    /**
     * Request URL:
     * 
     * @param IntegrationTester $i
     */
    public function deleteStatus(IntegrationTester $i): void
    {
        $timestamp=time();
        $statusName= 'AutoTest SDeleteMe ' . $timestamp;
        
        $newStatus = new Payload\Fixtures\Status\Create($statusName);
        
        $i->sendRequest($newStatus);
        
        $statusMapper=\Schoolbox\Fixtures\Status\Mapper::getInstance();
        $statusModel=$statusMapper->findOneByColumn('name', $statusName);
        
        $deleteStatus = new Payload\Fixtures\Status\Delete($statusModel);
        
        $i->sendRequest($deleteStatus);
        
        $i->seeResponseCodeIs('204');
    }
    
    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createVenue(IntegrationTester $i): void
    {
        $timestamp=time();
        $venueName= 'AutoTest SCreate ' . $timestamp;
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);
        
        $i->sendRequest($newVenue);
        
        $i->seeResponseCodeIs('303');
    }
    
    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateVenue(IntegrationTester $i): void
    {
        $timestamp=time();
        $venueName= 'AutoTest SChangeMe ' . $timestamp;
        
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);
        
        $i->sendRequest($newVenue);
        
        $venueMapper=\Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel=$venueMapper->findOneByColumn('name', $venueName);
        
        $updateVenue = new Payload\Fixtures\Venue\Update($venueModel);
        
        $newVenueName= 'AutoTest SUpdate ' . $timestamp;
        $updateVenue->setName($newVenueName);
        
        $i->sendRequest($updateVenue);
        
        $i->seeResponseCodeIs('303');
        
    }
    
    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteVenue(IntegrationTester $i): void
    {
        $timestamp=time();
        $venueName= 'AutoTest SDeleteMe ' . $timestamp;
        
        $newVenue = new Payload\Fixtures\Venue\Create($venueName);
        
        $i->sendRequest($newVenue);
        
        $venueMapper=\Schoolbox\Fixtures\Venue\Mapper::getInstance();
        $venueModel=$venueMapper->findOneByColumn('name', $venueName);
        
        $deleteVenue = new Payload\Fixtures\Venue\Delete($venueModel);
        
        $i->sendRequest($deleteVenue);
        
        $i->seeResponseCodeIs('204');
    }
    
    
    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createExtSchool(IntegrationTester $i): void
    {
        $timestamp=time();
        $schoolName= 'AutoTest SCreate ' . $timestamp;
        $newSchool = new Payload\Fixtures\School\Create($schoolName);
        
        $i->sendRequest($newSchool);
        
        $i->seeResponseCodeIs('303');
    }
    
    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateExtSchool(IntegrationTester $i): void
    {
        $timestamp=time();
        $schoolName= 'AutoTest SChangeMe ' . $timestamp;
        
        $newSchool = new Payload\Fixtures\School\Create($schoolName);
        
        $i->sendRequest($newSchool);
        
        $schoolMapper=\Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel=$schoolMapper->findOneByColumn('name', $schoolName);
        
        $updateSchool = new Payload\Fixtures\School\Update($schoolModel);
        
        $newSchoolName= 'AutoTest SUpdate ' . $timestamp;
        $updateSchool->setName($newSchoolName);
        
        $i->sendRequest($updateSchool);
        
        $i->seeResponseCodeIs('303');
        
    }
    
    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteExtSchool(IntegrationTester $i): void
    {
        $timestamp=time();
        $schoolName= 'AutoTest SDeleteMe ' . $timestamp;
        
        $newSchool = new Payload\Fixtures\School\Create($schoolName);
        
        $i->sendRequest($newSchool);
        
        $schoolMapper=\Schoolbox\Fixtures\School\Mapper::getInstance();
        $schoolModel=$schoolMapper->findOneByColumn('name', $schoolName);
        
        $deleteSchool = new Payload\Fixtures\School\Delete($schoolModel);
        
        $i->sendRequest($deleteSchool);
        
        $i->seeResponseCodeIs('200');
    }
    
    /**
     * Request URL:
     *
     *
     * @param IntegrationTester $i
     */
    public function createSport(IntegrationTester $i): void
    {
        $timestamp=time();
        $sportName= 'AutoTest SportCreate ' . $timestamp;
        $newSport = new Payload\Fixtures\Sport\Create($sportName);
        
        $i->sendRequest($newSport);
        
        $i->seeResponseCodeIs('303');
    }
    
    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function updateSport(IntegrationTester $i): void
    {
        $timestamp=time();
        $sportName= 'AutoTest SportChangeMe ' . $timestamp;
        
        $newSport = new Payload\Fixtures\Sport\Create($sportName);
        
        $i->sendRequest($newSport);
        
        $sportMapper=\Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel=$sportMapper->findOneByColumn('name', $sportName);
        
        $updateSport = new Payload\Fixtures\Sport\Update($sportModel);
        
        $updateSport->editPosition(3, 'Position 3 edited');
        $updateSport->deletePosition(2); //This will reduce the amount of positions thus making the names not match the position number
        $updateSport->addPosition('Added edited position'); // This will add a new position.
        
        $newSportName= 'AutoTest SportUpdate ' . $timestamp;
        $updateSport->setName($newSportName);
        
        $i->sendRequest($updateSport);
        
        $i->seeResponseCodeIs('303');
        
    }
    
    /**
     * Request URL:
     *
     * @param IntegrationTester $i
     */
    public function deleteSport(IntegrationTester $i): void
    {
        $timestamp=time();
        $sportName= 'AutoTest SportDeleteMe ' . $timestamp;
        
        $newSport = new Payload\Fixtures\Sport\Create($sportName);
        
        $i->sendRequest($newSport);
        
        $sportMapper=\Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $sportModel=$sportMapper->findOneByColumn('name', $sportName);
        
        $deleteSport = new Payload\Fixtures\Sport\Delete($sportModel);
        
        $i->sendRequest($deleteSport);
        
        $i->seeResponseCodeIs('204');
    }
}
