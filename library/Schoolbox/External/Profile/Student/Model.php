<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile\Student;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\External\Profile\Model as ProfileModel;

class Model
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * @var Alaress_Date_Format|null
     */
    private $dateOfBirth;

    /**
     * @var string|null
     */
    private $username;

    /**
     * @var string|null
     */
    private $email;

    /**
     * @var string|null
     */
    private $gender;

    /**
     * @var string|null
     */
    private $emergencyContactName1;

    /**
     * @var string|null
     */
    private $emergencyContactPhone1;

    /**
     * @var string|null
     */
    private $emergencyContactPhoneAlt1;

    /**
     * @var string|null
     */
    private $emergencyContactName2;

    /**
     * @var string|null
     */
    private $emergencyContactPhone2;

    /**
     * @var string|null
     */
    private $emergencyContactPhoneAlt2;

    /**
     * @var string|null
     */
    private $emergencyContactName3;

    /**
     * @var string|null
     */
    private $emergencyContactPhone3;

    /**
     * @var string|null
     */
    private $emergencyContactPhoneAlt3;

    /**
     * @var string|null
     */
    private $headOfHouseId;

    /**
     * @var string|null
     */
    private $headOfHouseName;

    /**
     * @var string|null
     */
    private $houseName;

    /**
     * @var string|null
     */
    private $formName;

    /**
     * @var string|null
     */
    private $formTeacherId;

    /**
     * @var string|null
     */
    private $formTeacherName;

    /**
     * @var string|null
     */
    private $tutorGroup;

    /**
     * @var string|null
     */
    private $tutorTeacherId;

    /**
     * @var string|null
     */
    private $tutorTeacherName;

    /**
     * @var string|null
     */
    private $yearLevel;

    /**
     * @var string|null
     */
    private $yearLevelCoordinatorId;

    /**
     * @var string|null
     */
    private $yearLevelCoordinatorName;

    /**
     * @var string|null
     */
    private $mobile;

    /**
     * @var string|null
     */
    private $studiesCode;

    /**
     * @var string|null
     */
    private $alertMedical;

    /**
     * @var string|null
     */
    private $alertComment;

    /**
     * @var string|null
     */
    private $alertMessage;

    /**
     * @var string|null
     */
    private $alertLegal;

    /**
     * @var Alaress_Date_Format|null
     */
    private $enrolmentDate;

    /**
     * @var Alaress_Date_Format|null
     */
    private $leavingDate;

    /**
     * @var bool|null
     */
    private $ib;

    /**
     * @var bool|null
     */
    private $individualLearningPlans;

    /**
     * @var bool|null
     */
    private $esl;

    /**
     * @var bool|null
     */
    private $specialNeeds;

    /**
     * @var bool|null
     */
    private $boardingStatus;

    /**
     * @var ProfileModel
     */
    private $baseProfile;


    /**
     * @return Alaress_Date_Format|null
     */
    public function getDateOfBirth(): ?Alaress_Date_Format
    {
        return $this->dateOfBirth;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return string|null
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactName1(): ?string
    {
        return $this->emergencyContactName1;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactPhone1(): ?string
    {
        return $this->emergencyContactPhone1;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactPhoneAlt1(): ?string
    {
        return $this->emergencyContactPhoneAlt1;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactName2(): ?string
    {
        return $this->emergencyContactName2;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactPhone2(): ?string
    {
        return $this->emergencyContactPhone2;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactPhoneAlt2(): ?string
    {
        return $this->emergencyContactPhoneAlt2;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactName3(): ?string
    {
        return $this->emergencyContactName3;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactPhone3(): ?string
    {
        return $this->emergencyContactPhone3;
    }

    /**
     * @return string|null
     */
    public function getEmergencyContactPhoneAlt3(): ?string
    {
        return $this->emergencyContactPhoneAlt3;
    }

    /**
     * @return string|null
     */
    public function getHeadOfHouseId(): ?string
    {
        return $this->headOfHouseId;
    }

    /**
     * @return string|null
     */
    public function getHeadOfHouseName(): ?string
    {
        return $this->headOfHouseName;
    }

    /**
     * @return string|null
     */
    public function getHouseName(): ?string
    {
        return $this->houseName;
    }

    /**
     * @return string|null
     */
    public function getFormName(): ?string
    {
        return $this->formName;
    }

    /**
     * @return string|null
     */
    public function getFormTeacherId(): ?string
    {
        return $this->formTeacherId;
    }

    /**
     * @return string|null
     */
    public function getFormTeacherName(): ?string
    {
        return $this->formTeacherName;
    }

    /**
     * @return string|null
     */
    public function getTutorGroup(): ?string
    {
        return $this->tutorGroup;
    }

    /**
     * @return string|null
     */
    public function getTutorTeacherId(): ?string
    {
        return $this->tutorTeacherId;
    }

    /**
     * @return string|null
     */
    public function getTutorTeacherName(): ?string
    {
        return $this->tutorTeacherName;
    }

    /**
     * @return string|null
     */
    public function getYearLevel(): ?string
    {
        return $this->yearLevel;
    }

    /**
     * @return string|null
     */
    public function getYearLevelCoordinatorId(): ?string
    {
        return $this->yearLevelCoordinatorId;
    }

    /**
     * @return string|null
     */
    public function getYearLevelCoordinatorName(): ?string
    {
        return $this->yearLevelCoordinatorName;
    }

    /**
     * @return string|null
     */
    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    /**
     * @return string|null
     */
    public function getStudiesCode(): ?string
    {
        return $this->studiesCode;
    }

    /**
     * @return string|null
     */
    public function getAlertMedical(): ?string
    {
        return $this->alertMedical;
    }

    /**
     * @return string|null
     */
    public function getAlertComment(): ?string
    {
        return $this->alertComment;
    }

    /**
     * @return string|null
     */
    public function getAlertMessage(): ?string
    {
        return $this->alertMessage;
    }

    /**
     * @return string|null
     */
    public function getAlertLegal(): ?string
    {
        return $this->alertLegal;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getEnrolmentDate(): ?Alaress_Date_Format
    {
        return $this->enrolmentDate;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getLeavingDate(): ?Alaress_Date_Format
    {
        return $this->leavingDate;
    }

    /**
     * @return bool|null
     */
    public function getIb(): ?bool
    {
        return $this->ib;
    }

    /**
     * @return bool|null
     */
    public function getIndividualLearningPlans(): ?bool
    {
        return $this->individualLearningPlans;
    }

    /**
     * @return bool|null
     */
    public function getEsl(): ?bool
    {
        return $this->esl;
    }

    /**
     * @return bool|null
     */
    public function getSpecialNeeds(): ?bool
    {
        return $this->specialNeeds;
    }

    /**
     * @return bool|null
     */
    public function getBoardingStatus(): ?bool
    {
        return $this->boardingStatus;
    }

    /**
     * @return ProfileModel
     */
    public function getBaseProfile(): ProfileModel
    {
        return $this->baseProfile;
    }


    /**
     * @param Alaress_Date_Format|null $dateOfBirth
     * @return Model
     */
    public function setDateOfBirth(?Alaress_Date_Format $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    /**
     * @param string|null $username
     * @return Model
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @param string|null $email
     * @return Model
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @param string|null $gender
     * @return Model
     */
    public function setGender(?string $gender): self
    {
        $this->gender = $gender;
        return $this;
    }

    /**
     * @param string|null $emergencyContactName1
     * @return Model
     */
    public function setEmergencyContactName1(?string $emergencyContactName1): self
    {
        $this->emergencyContactName1 = $emergencyContactName1;
        return $this;
    }

    /**
     * @param string|null $emergencyContactPhone1
     * @return Model
     */
    public function setEmergencyContactPhone1(?string $emergencyContactPhone1): self
    {
        $this->emergencyContactPhone1 = $emergencyContactPhone1;
        return $this;
    }

    /**
     * @param string|null $emergencyContactPhoneAlt1
     * @return Model
     */
    public function setEmergencyContactPhoneAlt1(?string $emergencyContactPhoneAlt1): self
    {
        $this->emergencyContactPhoneAlt1 = $emergencyContactPhoneAlt1;
        return $this;
    }

    /**
     * @param string|null $emergencyContactName2
     * @return Model
     */
    public function setEmergencyContactName2(?string $emergencyContactName2): self
    {
        $this->emergencyContactName2 = $emergencyContactName2;
        return $this;
    }

    /**
     * @param string|null $emergencyContactPhone2
     * @return Model
     */
    public function setEmergencyContactPhone2(?string $emergencyContactPhone2): self
    {
        $this->emergencyContactPhone2 = $emergencyContactPhone2;
        return $this;
    }

    /**
     * @param string|null $emergencyContactPhoneAlt2
     * @return Model
     */
    public function setEmergencyContactPhoneAlt2(?string $emergencyContactPhoneAlt2): self
    {
        $this->emergencyContactPhoneAlt2 = $emergencyContactPhoneAlt2;
        return $this;
    }

    /**
     * @param string|null $emergencyContactName3
     * @return Model
     */
    public function setEmergencyContactName3(?string $emergencyContactName3): self
    {
        $this->emergencyContactName3 = $emergencyContactName3;
        return $this;
    }

    /**
     * @param string|null $emergencyContactPhone3
     * @return Model
     */
    public function setEmergencyContactPhone3(?string $emergencyContactPhone3): self
    {
        $this->emergencyContactPhone3 = $emergencyContactPhone1;
        return $this;
    }

    /**
     * @param string|null $emergencyContactPhoneAlt3
     * @return Model
     */
    public function setEmergencyContactPhoneAlt3(?string $emergencyContactPhoneAlt3): self
    {
        $this->emergencyContactPhoneAlt3 = $emergencyContactPhoneAlt3;
        return $this;
    }

    /**
     * @param string|null $headOfHouseId
     * @return Model
     */
    public function setHeadOfHouseId(?string $headOfHouseId): self
    {
        $this->headOfHouseId = $headOfHouseId;
        return $this;
    }

    /**
     * @param string|null $headOfHouseName
     * @return Model
     */
    public function setHeadOfHouseName(?string $headOfHouseName): self
    {
        $this->headOfHouseName = $headOfHouseName;
        return $this;
    }

    /**
     * @param string|null $houseName
     * @return Model
     */
    public function setHouseName(?string $houseName): self
    {
        $this->houseName = $houseName;
        return $this;
    }

    /**
     * @param string|null $formName
     * @return Model
     */
    public function setFormName(?string $formName): self
    {
        $this->formName = $formName;
        return $this;
    }

    /**
     * @param string|null $formTeacherId
     * @return Model
     */
    public function setFormTeacherId(?string $formTeacherId): self
    {
        $this->formTeacherId = $formTeacherId;
        return $this;
    }

    /**
     * @param string|null $formTeacherName
     * @return Model
     */
    public function setFormTeacherName(?string $formTeacherName): self
    {
        $this->formTeacherName = $formTeacherName;
        return $this;
    }

    /**
     * @param string|null $tutorGroup
     * @return Model
     */
    public function setTutorGroup(?string $tutorGroup): self
    {
        $this->tutorGroup = $tutorGroup;
        return $this;
    }

    /**
     * @param string|null $tutorTeacherId
     * @return Model
     */
    public function setTutorTeacherId(?string $tutorTeacherId): self
    {
        $this->tutorTeacherId = $tutorTeacherId;
        return $this;
    }

    /**
     * @param string|null $tutorTeacherName
     * @return Model
     */
    public function setTutorTeacherName(?string $tutorTeacherName): self
    {
        $this->tutorTeacherName = $tutorTeacherName;
        return $this;
    }

    /**
     * @param string|null $yearLevel
     * @return Model
     */
    public function setYearLevel(?string $yearLevel): self
    {
        $this->yearLevel = $yearLevel;
        return $this;
    }

    /**
     * @param string|null $yearLevelCoordinatorId
     * @return Model
     */
    public function setYearLevelCoordinatorId(?string $yearLevelCoordinatorId): self
    {
        $this->yearLevelCoordinatorId = $yearLevelCoordinatorId;
        return $this;
    }

    /**
     * @param string|null $yearLevelCoordinatorName
     * @return Model
     */
    public function setYearLevelCoordinatorName(?string $yearLevelCoordinatorName): self
    {
        $this->yearLevelCoordinatorName = $yearLevelCoordinatorName;
        return $this;
    }

    /**
     * @param string|null $mobile
     * @return Model
     */
    public function setMobile(?string $mobile): self
    {
        $this->mobile = $mobile;
        return $this;
    }

    /**
     * @param string|null $studiesCode
     * @return Model
     */
    public function setStudiesCode(?string $studiesCode): self
    {
        $this->studiesCode = $studiesCode;
        return $this;
    }

    /**
     * @param string|null $alertMedical
     * @return Model
     */
    public function setAlertMedical(?string $alertMedical): self
    {
        $this->alertMedical = $alertMedical;
        return $this;
    }

    /**
     * @param string|null $alertComment
     * @return Model
     */
    public function setAlertComment(?string $alertComment): self
    {
        $this->alertComment = $alertComment;
        return $this;
    }

    /**
     * @param string|null $alertMessage
     * @return Model
     */
    public function setAlertMessage(?string $alertMessage): self
    {
        $this->alertMessage = $alertMessage;
        return $this;
    }

    /**
     * @param string|null $alertLegal
     * @return Model
     */
    public function setAlertLegal(?string $alertLegal): self
    {
        $this->alertLegal = $alertLegal;
        return $this;
    }

    /**
     * @param Alaress_Date_Format|null $enrolmentDate
     * @return Model
     */
    public function setEnrolmentDate(?Alaress_Date_Format $enrolmentDate): self
    {
        $this->enrolmentDate = $enrolmentDate;
        return $this;
    }

    /**
     * @param Alaress_Date_Format|null $leavingDate
     * @return Model
     */
    public function setLeavingDate(?Alaress_Date_Format $leavingDate): self
    {
        $this->leavingDate = $leavingDate;
        return $this;
    }

    /**
     * @param bool|null $ib
     * @return Model
     */
    public function setIb(?bool $ib): self
    {
        $this->ib = $ib;
        return $this;
    }

    /**
     * @param bool|null $individualLearningPlans
     * @return Model
     */
    public function setIndividualLearningPlans(?bool $individualLearningPlans): self
    {
        $this->individualLearningPlans = $individualLearningPlans;
        return $this;
    }

    /**
     * @param bool|null $esl
     * @return Model
     */
    public function setEsl(?bool $esl): self
    {
        $this->esl = $esl;
        return $this;
    }

    /**
     * @param bool|null $specialNeeds
     * @return Model
     */
    public function setSpecialNeeds(?bool $specialNeeds): self
    {
        $this->specialNeeds = $specialNeeds;
        return $this;
    }

    /**
     * @param bool|null $boardingStatus
     * @return Model
     */
    public function setBoardingStatus(?bool $boardingStatus): self
    {
        $this->boardingStatus = $boardingStatus;
        return $this;
    }

    /**
     * @param ProfileModel|null $baseProfile
     * @return Model
     */
    public function setBaseProfile(?ProfileModel $baseProfile): self
    {
        $this->baseProfile = $baseProfile;
        return $this;
    }
}
