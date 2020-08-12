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
use React\Tests\Socket\IntegrationTest;

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
    
    public function createStatus(IntegrationTester $i): void
    {
        $timestamp=time();
        $statusName= 'AutoTest SCreate ' . $timestamp;
        $newStatus = new Payload\Fixtures\Status\Create($statusName);
        
        $i->sendRequest($newStatus);
        
        $i->seeResponseCodeIs('200');
    }
    
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
    
    
}
