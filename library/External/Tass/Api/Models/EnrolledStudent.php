<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

use Alaress_Date_Format;

// TODO de-dupe with Student?
class EnrolledStudent
{

    /** @var Alaress_Date_Format|null */
    private $acceptDate;

    /** @var string */
    private $acceptPaid;

    /** @var string */
    private $altId;

    /** @var string */
    private $assessDate;

    /** @var string */
    private $assessFlag;

    /** @var null|bool */
    private $boarder;

    /** @var string */
    private $campusCode;

    /** @var int */
    private $companyCode;

    /** @var string */
    private $currentSchool;

    /** @var string */
    private $currentYearGroup;

    /** @var string */
    private $dateArrival;

    /** @var string */
    private $displayName;

    /** @var Alaress_Date_Format|null */
    private $dateOfA;  // TODO what is this? @see $this->dateOfArrival de-dupe?

    /** @var Alaress_Date_Format|null */
    private $dateOfBirth;

    /** @var string */
    private $entryYearGroup;

    /** @var string */
    private $entryYearGroupDescription;

    /** @var string */
    private $entryYear;

    /** @var string */
    private $email;

    /** @var string */
    private $followUpDate;

    /** @var string */
    private $followUpFlag;

    /** @var string */
    private $formClass;

    /** @var string */
    private $fte;

    /** @var string */
    private $givenName;

    /** @var string */
    private $holdPaid;

    /** @var string */
    private $house;

    /** @var Alaress_Date_Format|null */
    private $hPaidDate;

    /** @var string */
    private $id;

    /** @var string */
    private $interviewDate;

    /** @var string */
    private $interviewFlag;

    /** @var string */
    private $lwAddNum;

    /** @var string */
    private $mobilePhone;

    /** @var string */
    private $mode;

    /** @var string */
    private $multiParFlag;

    /** @var Alaress_Date_Format|null */
    private $offerDate;

    /** @var string */
    private $parCode;

    /** @var string */
    private $pcTutGroup;

    /** @var string */
    private $placeOffered;

    /** @var string */
    private $preferredName;

    /** @var string */
    private $priCode;

    /** @var int */
    private $priPoints;

    /** @var string */
    private $privacyFlag;

    /** @var Alaress_Date_Format|null */
    private $prospDate;

    /** @var string */
    private $prospFlag;

    /** @var null|bool */
    private $readOnly;

    /** @var string */
    private $religion;

    /** @var string */
    private $residentialStatus;

    /** @var string */
    private $sex;

    /** @var string */
    private $smsFlag;

    /** @var string */
    private $studentCode;

    /** @var string */
    private $studentId;

    /** @var array */
    private $udBills;

    /** @var array */
    private $udCodes;

    /** @var array */
    private $udFlags;

    /** @var array */
    private $udTexts;

    /** @var string */
    private $surName;

    /** @var Alaress_Date_Format|null */
    private $tferDate;

    /** @var string */
    private $updateBy;

    /** @var Alaress_Date_Format|null */
    private $updateOn;

    /** @var string */
    private $visaExpiry;

    /** @var string */
    private $visaSubClass;

    /** @var string */
    private $yearGroup;  // TODO de-dupe with $currentYearGroup ?


    /**
     * @param Alaress_Date_Format|null $acceptDate
     * @param string                   $acceptPaid
     * @param string                   $altId
     * @param string                   $assessDate
     * @param string                   $assessFlag
     * @param null|bool                $boarder
     * @param string                   $campusCode
     * @param int                      $companyCode
     * @param string                   $currentSchool
     * @param string                   $currentYearGroup
     * @param string                   $dateArrival
     * @param string                   $displayName
     * @param Alaress_Date_Format|null $dateOfA
     * @param Alaress_Date_Format|null $dateOfBirth
     * @param string                   $entryYearGroup
     * @param null|string              $entryYearGroupDescription
     * @param string                   $entryYear
     * @param string                   $email
     * @param string                   $followUpDate
     * @param string                   $followUpFlag
     * @param string                   $formClass
     * @param string                   $fte
     * @param string                   $givenName
     * @param string                   $holdPaid
     * @param string                   $house
     * @param Alaress_Date_Format|null $hPaidDate
     * @param null|string              $id
     * @param string                   $interviewDate
     * @param string                   $interviewFlag
     * @param string                   $lwAddNum
     * @param string                   $mobilePhone
     * @param string                   $mode
     * @param string                   $multiParFlag
     * @param Alaress_Date_Format|null $offerDate
     * @param string                   $parCode
     * @param string                   $pcTutGroup
     * @param string                   $placeOffered
     * @param string                   $preferredName
     * @param null|string              $priCode
     * @param null|int                 $priPoints
     * @param string                   $privacyFlag
     * @param Alaress_Date_Format|null $prospDate
     * @param string                   $prospFlag
     * @param bool|null                $readOnly
     * @param string                   $religion
     * @param string                   $residentialStatus
     * @param string                   $sex
     * @param string                   $smsFlag
     * @param string                   $studentCode
     * @param string                   $studentId
     * @param array                    $udBills
     * @param array                    $udCodes
     * @param array                    $udFlags
     * @param array                    $udTexts
     * @param string                   $surName
     * @param Alaress_Date_Format|null $tferDate
     * @param string                   $updateBy
     * @param Alaress_Date_Format|null $updateOn
     * @param string                   $visaExpiry
     * @param string                   $visaSubClass
     * @param null|string              $yearGroup
     */
    public function __construct(
        ?Alaress_Date_Format $acceptDate,
        ?string $acceptPaid,
        ?string $altId,
        ?string $assessDate,
        ?string $assessFlag,
        ?bool $boarder,
        ?string $campusCode,
        ?int $companyCode,
        ?string $currentSchool,
        ?string $currentYearGroup,
        ?string $dateArrival,
        ?string $displayName,
        ?Alaress_Date_Format $dateOfA,
        ?Alaress_Date_Format $dateOfBirth,
        ?string $entryYearGroup,
        ?string $entryYearGroupDescription,
        ?string $entryYear,
        ?string $email,
        ?string $followUpDate,
        ?string $followUpFlag,
        ?string $formClass,
        ?string $fte,
        ?string $givenName,
        ?string $holdPaid,
        ?string $house,
        ?Alaress_Date_Format $hPaidDate,
        ?string $id,
        ?string $interviewDate,
        ?string $interviewFlag,
        ?string $lwAddNum,
        ?string $mobilePhone,
        ?string $mode,
        ?string $multiParFlag,
        ?Alaress_Date_Format $offerDate,
        ?string $parCode,
        ?string $pcTutGroup,
        ?string $placeOffered,
        ?string $preferredName,
        ?string $priCode,
        ?int $priPoints,
        ?string $privacyFlag,
        ?Alaress_Date_Format $prospDate,
        ?string $prospFlag,
        ?bool $readOnly,
        ?string $religion,
        ?string $residentialStatus,
        ?string $sex,
        ?string $smsFlag,
        ?string $studentCode,
        ?string $studentId,
        array $udBills,
        array $udCodes,
        array $udFlags,
        array $udTexts,
        ?string $surName,
        ?Alaress_Date_Format $tferDate,
        ?string $updateBy,
        ?Alaress_Date_Format $updateOn,
        ?string $visaExpiry,
        ?string $visaSubClass,
        ?string $yearGroup
    ) {
        $this->acceptDate                = $acceptDate;
        $this->acceptPaid                = $acceptPaid;
        $this->altId                     = $altId;
        $this->assessDate                = $assessDate;
        $this->assessFlag                = $assessFlag;
        $this->boarder                   = $boarder;
        $this->campusCode                = $campusCode;
        $this->companyCode               = $companyCode;
        $this->currentSchool             = $currentSchool;
        $this->currentYearGroup          = $currentYearGroup;
        $this->dateArrival               = $dateArrival;
        $this->displayName               = $displayName;
        $this->dateOfA                   = $dateOfA;
        $this->dateOfBirth               = $dateOfBirth;
        $this->entryYearGroup            = $entryYearGroup;
        $this->entryYearGroupDescription = $entryYearGroupDescription;
        $this->entryYear                 = $entryYear;
        $this->email                     = $email;
        $this->followUpDate              = $followUpDate;
        $this->followUpFlag              = $followUpFlag;
        $this->formClass                 = $formClass;
        $this->fte                       = $fte;
        $this->givenName                 = $givenName;
        $this->holdPaid                  = $holdPaid;
        $this->house                     = $house;
        $this->hPaidDate                 = $hPaidDate;
        $this->id                 = $id;
        $this->interviewDate             = $interviewDate;
        $this->interviewFlag             = $interviewFlag;
        $this->lwAddNum                  = $lwAddNum;
        $this->mobilePhone               = $mobilePhone;
        $this->mode                      = $mode;
        $this->multiParFlag              = $multiParFlag;
        $this->offerDate                 = $offerDate;
        $this->parCode                   = $parCode;
        $this->pcTutGroup                = $pcTutGroup;
        $this->placeOffered              = $placeOffered;
        $this->preferredName             = $preferredName;
        $this->priCode             = $priCode;
        $this->priPoints             = $priPoints;
        $this->privacyFlag               = $privacyFlag;
        $this->prospDate                 = $prospDate;
        $this->prospFlag                 = $prospFlag;
        $this->readOnly                  = $readOnly;
        $this->religion                  = $religion;
        $this->residentialStatus         = $residentialStatus;
        $this->sex                       = $sex;
        $this->smsFlag                   = $smsFlag;
        $this->studentCode               = $studentCode;
        $this->studentId                 = $studentId;
        $this->udBills                   = $udBills;
        $this->udCodes                   = $udCodes;
        $this->udFlags                   = $udFlags;
        $this->udTexts                   = $udTexts;
        $this->surName                   = $surName;
        $this->tferDate                  = $tferDate;
        $this->updateBy                  = $updateBy;
        $this->updateOn                  = $updateOn;
        $this->visaExpiry                = $visaExpiry;
        $this->visaSubClass              = $visaSubClass;
        $this->yearGroup = $yearGroup;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getAcceptDate(): ?Alaress_Date_Format
    {
        return $this->acceptDate;
    }


    /**
     * @return string
     */
    public function getAcceptPaid(): ?string
    {
        return $this->acceptPaid;
    }


    /**
     * @return string
     */
    public function getAltId(): ?string
    {
        return $this->altId;
    }


    /**
     * @return string
     */
    public function getAssessDate(): ?string
    {
        return $this->assessDate;
    }


    /**
     * @return string
     */
    public function getAssessFlag(): ?string
    {
        return $this->assessFlag;
    }


    /**
     * @return null|bool
     */
    public function getBoarder(): ?bool
    {
        return $this->boarder;
    }


    /**
     * @return string
     */
    public function getCampusCode(): ?string
    {
        return $this->campusCode;
    }


    /**
     * @return int
     */
    public function getCompanyCode(): int
    {
        return $this->companyCode;
    }


    /**
     * @return string
     */
    public function getCurrentSchool(): ?string
    {
        return $this->currentSchool;
    }


    /**
     * @return string
     */
    public function getCurrentYearGroup(): ?string
    {
        return $this->currentYearGroup;
    }


    /**
     * @return string
     */
    public function getDateArrival(): ?string
    {
        return $this->dateArrival;
    }


    /**
     * @return string
     */
    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getDateOfA(): ?Alaress_Date_Format
    {
        return $this->dateOfA;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getDateOfBirth(): ?Alaress_Date_Format
    {
        return $this->dateOfBirth;
    }


    /**
     * @return string
     */
    public function getEntryYearGroup(): ?string
    {
        return $this->entryYearGroup;
    }

    /**
     * @return string
     */
    public function getEntryYearGroupDescription(): ?string
    {
        return $this->entryYearGroupDescription;
    }


    /**
     * @return string
     */
    public function getEntryYear(): ?string
    {
        return $this->entryYear;
    }


    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }


    /**
     * @return string
     */
    public function getFollowUpDate(): ?string
    {
        return $this->followUpDate;
    }


    /**
     * @return string
     */
    public function getFollowUpFlag(): ?string
    {
        return $this->followUpFlag;
    }


    /**
     * @return string
     */
    public function getFormClass(): ?string
    {
        return $this->formClass;
    }


    /**
     * @return string
     */
    public function getFte(): ?string
    {
        return $this->fte;
    }


    /**
     * @return string
     */
    public function getGivenName(): ?string
    {
        return $this->givenName;
    }


    /**
     * @return string
     */
    public function getHoldPaid(): ?string
    {
        return $this->holdPaid;
    }


    /**
     * @return string
     */
    public function getHouse(): ?string
    {
        return $this->house;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getHPaidDate(): ?Alaress_Date_Format
    {
        return $this->hPaidDate;
    }


    /**
     * @return string
     */
    public function getId(): ?string
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getInterviewDate(): ?string
    {
        return $this->interviewDate;
    }


    /**
     * @return string
     */
    public function getInterviewFlag(): ?string
    {
        return $this->interviewFlag;
    }


    /**
     * @return string
     */
    public function getLwAddNum(): ?string
    {
        return $this->lwAddNum;
    }


    /**
     * @return string
     */
    public function getMobilePhone(): ?string
    {
        return $this->mobilePhone;
    }


    /**
     * @return string
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }


    /**
     * @return string
     */
    public function getMultiParFlag(): ?string
    {
        return $this->multiParFlag;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getOfferDate(): ?Alaress_Date_Format
    {
        return $this->offerDate;
    }


    /**
     * @return string
     */
    public function getParCode(): ?string
    {
        return $this->parCode;
    }


    /**
     * @return string
     */
    public function getPcTutGroup(): ?string
    {
        return $this->pcTutGroup;
    }


    /**
     * @return string
     */
    public function getPlaceOffered(): ?string
    {
        return $this->placeOffered;
    }


    /**
     * @return string
     */
    public function getPreferredName(): ?string
    {
        return $this->preferredName;
    }

    /**
     * @return string
     */
    public function getPriCodes(): ?string
    {
        return $this->priCode;
    }

    /**
     * @return int
     */
    public function getPriPoints(): ?int
    {
        return $this->priPoints;
    }


    /**
     * @return string
     */
    public function getPrivacyFlag(): ?string
    {
        return $this->privacyFlag;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getProspDate(): ?Alaress_Date_Format
    {
        return $this->prospDate;
    }


    /**
     * @return string
     */
    public function getProspFlag(): ?string
    {
        return $this->prospFlag;
    }


    /**
     * @return null|bool
     */
    public function getReadOnly(): ?bool
    {
        return $this->readOnly;
    }

    /**
     * @return string
     */
    public function getReligion(): ?string
    {
        return $this->religion;
    }


    /**
     * @return string
     */
    public function getResidentialStatus(): ?string
    {
        return $this->residentialStatus;
    }


    /**
     * @return string
     */
    public function getSex(): ?string
    {
        return $this->sex;
    }


    /**
     * @return string
     */
    public function getSmsFlag(): ?string
    {
        return $this->smsFlag;
    }


    /**
     * @return string
     */
    public function getStudentCode(): ?string
    {
        return $this->studentCode;
    }


    /**
     * @return string
     */
    public function getStudentId(): ?string
    {
        return $this->studentId;
    }


    /**
     * @return array
     */
    public function getUdBills(): array
    {
        return $this->udBills;
    }


    /**
     * @return array
     */
    public function getUdCodes(): array
    {
        return $this->udCodes;
    }


    /**
     * @return array
     */
    public function getUdFlags(): array
    {
        return $this->udFlags;
    }


    /**
     * @return array
     */
    public function getUdTexts(): array
    {
        return $this->udTexts;
    }


    /**
     * @return string
     */
    public function getSurName(): ?string
    {
        return $this->surName;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getTferDate(): ?Alaress_Date_Format
    {
        return $this->tferDate;
    }


    /**
     * @return string
     */
    public function getUpdateBy(): ?string
    {
        return $this->updateBy;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getUpdateOn(): ?Alaress_Date_Format
    {
        return $this->updateOn;
    }


    /**
     * @return string
     */
    public function getVisaExpiry(): ?string
    {
        return $this->visaExpiry;
    }


    /**
     * @return string
     */
    public function getVisaSubClass(): ?string
    {
        return $this->visaSubClass;
    }


    /**
     * @return null|string
     */
    public function getYearGroup(): ?string
    {
        return $this->yearGroup;
    }
}
