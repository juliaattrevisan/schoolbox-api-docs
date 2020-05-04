<?php 
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace smoketests;

use IntegrationTester;
use Payload;

class FixturesCest
{
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin(); #we logged in as alaress
        $i->stopFollowingRedirects();
    }
    
    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/
     * Referer https://vagrant.dev.schoolbox.cloud/fixtures/seasons/new
     * id	
        name	adsgf
        start	04/05/2020
        end	23/05/2020
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
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/4
     * Refererhttps://vagrant.dev.schoolbox.cloud/fixtures/seasons/4/edit
     * id	4
     * name	Winter-2018-edit
     * start	21/06/2018
     * end	22/09/2018
     * 
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
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/5/archive
     * https://vagrant.dev.schoolbox.cloud/fixtures/seasons
     * @skip
     */
    public function archiveSeason(IntegrationTester $i): void
    {
        
    }
    
    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/5/unArchive
     * https://vagrant.dev.schoolbox.cloud/fixtures/seasons
     * @skip
     */
    public function restoreSeason(IntegrationTester $i): void
    {
        
    }
    
    /**
     * Request URL:https://vagrant.dev.schoolbox.cloud/fixtures/seasons/5/delete
     * https://vagrant.dev.schoolbox.cloud/fixtures/seasons
     * @skip
     */
    public function deleteSeason(IntegrationTester $i): void
    {
        
    }
}
?>