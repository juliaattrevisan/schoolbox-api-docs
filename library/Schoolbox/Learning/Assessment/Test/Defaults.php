<?php

/**
 * Bestiary
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Test;

use \Schoolbox\User;

trait Defaults
{
    /**
     * @var User User
     */
    protected $hodes;

    /**
     * @var User
     */
    protected $sberryhill;

    /**
     * @var User
     */
    protected $slucier;

    /**
     * @var User
     */
    protected $bsalem;

    /**
     * @var User
     */
    protected $asaxon;

    /**
     * @var User
     */
    protected $celia;

    /**
     * @var User
     */
    protected $somodio;

    /**
     * @var \Alaress_Date_Format
     */
    protected $today;

    /**
     * @var \Alaress_Date_Format
     */
    protected $tomorrow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $thirteenDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $fourteenDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $twentyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $thirtyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $fortyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $fiftyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $sixtyDaysFromNow;

    /** @var ParticipantLinker */
    private $linker;

    /**
     * The Bestiary
     */
    protected function setup()
    {
        /* Dudes
         */
        $this->hodes = (new User)->setId(230)->setFirstname('David')->setLastname('Rhodes');
        $this->sberryhill = (new User)->setId(15)->setFirstname('Scott')->setLastname('Berryhill');
        $this->slucier = (new User)->setId(16)->setFirstname('Sam')->setLastname('Lucier');
        $this->bsalem = (new User)->setId(32)->setFirstname('Bobby')->setLastname('Salem');
        $this->asaxon = (new User)->setId(17)->setFirstname('Anita')->setLastname('Saxon');
        $this->celia = (new User)->setId(3)->setFirstname('Celia')->setLastname('Glenn');
        $this->somodio = (new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio');

        /* Times
         */
        $dateFormat = 'Y-m-d H:i:s';
        $dateInit = '2016-09-08 11:30:00';

        $this->today = new \Alaress_Date_Format($dateInit, $dateFormat);
        $this->tomorrow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1);
        $this->thirteenDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(13);
        $this->fourteenDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(14);
        $this->twentyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(20);
        $this->thirtyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(30);
        $this->fortyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(40);
        $this->fiftyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(50);
        $this->sixtyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(60);

        $this->linker = new ParticipantLinker();
    }


    /**
     * @return ParticipantLinker
     */
    protected function getLinker(): ParticipantLinker
    {
        return $this->linker;
    }

}
