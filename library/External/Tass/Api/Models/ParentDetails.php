<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

use Alaress_Date_Format;

/**
 * Ideally this class would be called 'Parent', but that's a reserved word.
 *
 * This represents both parents (ie a mother and father)
 */
class ParentDetails
{

    /** @var array TODO explode into individual attributes, or make sub-object collection */
    private $addresses;

    /** @var bool */
    private $canDelete;

    /** @var int */
    private $companyCode;

    /** @var string */
    private $fatherName;

    /** @var string */
    private $fatherOccupation;

    /** @var string */
    private $motherName;

    /** @var string */
    private $motherOccupation;

    /** @var int */
    private $parentCode;

    /** @var string */
    private $parentType;

    /** @var int */
    private $recordId;

    /** @var bool */
    private $splitFamilyRulesExist;

    /** @var string */
    private $statusDescription;

    /** @var string */
    private $statusFlag;

    /** @var string */
    private $surname;

    /** @var string */
    private $tableId;

    /**
     * @var mixed[] TODO what does 'ud' mean? if confirmed to mean 'user-defined', rename this to $userDefinedFlags
     */
    private $udFlags;

    /** @var mixed[] */
    private $udCodes;

    /** @var mixed[] */
    private $udText;

    /** @var string */
    private $updateBy;

    /** @var Alaress_Date_Format */
    private $updateOn;

    /** @var bool */
    private $webAccessFlag;


    /**
     * @param array               $addresses
     * @param bool                $canDelete
     * @param int                 $companyCode
     * @param string              $fatherName
     * @param string              $fatherOccupation
     * @param string              $motherName
     * @param string              $motherOccupation
     * @param int                 $parentCode
     * @param string              $parentType
     * @param int                 $recordId
     * @param bool                $splitFamilyRulesExist
     * @param string              $statusDescription
     * @param string              $statusFlag
     * @param string              $surname
     * @param string              $tableId
     * @param array               $udFlags
     * @param array               $udCodes
     * @param array               $udText
     * @param string              $updateBy
     * @param Alaress_Date_Format $updateOn
     * @param bool                $webAccessFlag
     */
    public function __construct(
        array $addresses,
        bool $canDelete,
        int $companyCode,
        string $fatherName,
        string $fatherOccupation,
        string $motherName,
        string $motherOccupation,
        int $parentCode,
        string $parentType,
        int $recordId,
        bool $splitFamilyRulesExist,
        string $statusDescription,
        string $statusFlag,
        string $surname,
        string $tableId,
        array $udFlags,
        array $udCodes,
        array $udText,
        string $updateBy,
        Alaress_Date_Format $updateOn,
        bool $webAccessFlag
    ) {
        $this->addresses             = $addresses;
        $this->canDelete             = $canDelete;
        $this->companyCode           = $companyCode;
        $this->fatherName            = $fatherName;
        $this->fatherOccupation      = $fatherOccupation;
        $this->motherName            = $motherName;
        $this->motherOccupation      = $motherOccupation;
        $this->parentCode            = $parentCode;
        $this->parentType            = $parentType;
        $this->recordId              = $recordId;
        $this->splitFamilyRulesExist = $splitFamilyRulesExist;
        $this->statusDescription     = $statusDescription;
        $this->statusFlag            = $statusFlag;
        $this->surname               = $surname;
        $this->tableId               = $tableId;
        $this->udFlags               = $udFlags;
        $this->udCodes               = $udCodes;
        $this->udText                = $udText;
        $this->updateBy              = $updateBy;
        $this->updateOn              = $updateOn;
        $this->webAccessFlag         = $webAccessFlag;
    }


    /**
     * @return array
     */
    public function getAddresses(): array
    {
        return $this->addresses;
    }


    /**
     * @return bool
     */
    public function isCanDelete(): bool
    {
        return $this->canDelete;
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
    public function getFatherName(): string
    {
        return $this->fatherName;
    }


    /**
     * @return string
     */
    public function getFatherOccupation(): string
    {
        return $this->fatherOccupation;
    }


    /**
     * @return string
     */
    public function getMotherName(): string
    {
        return $this->motherName;
    }


    /**
     * @return string
     */
    public function getMotherOccupation(): string
    {
        return $this->motherOccupation;
    }


    /**
     * @return int
     */
    public function getParentCode(): int
    {
        return $this->parentCode;
    }


    /**
     * @return string
     */
    public function getParentType(): string
    {
        return $this->parentType;
    }


    /**
     * @return int
     */
    public function getRecordId(): int
    {
        return $this->recordId;
    }


    /**
     * @return bool
     */
    public function isSplitFamilyRulesExist(): bool
    {
        return $this->splitFamilyRulesExist;
    }


    /**
     * @return string
     */
    public function getStatusDescription(): string
    {
        return $this->statusDescription;
    }


    /**
     * @return string
     */
    public function getStatusFlag(): string
    {
        return $this->statusFlag;
    }


    /**
     * @return string
     */
    public function getSurname(): string
    {
        return $this->surname;
    }


    /**
     * @return string
     */
    public function getTableId(): string
    {
        return $this->tableId;
    }


    /**
     * @return mixed[]
     */
    public function getUdFlags(): array
    {
        return $this->udFlags;
    }


    /**
     * @return mixed[]
     */
    public function getUdCodes(): array
    {
        return $this->udCodes;
    }


    /**
     * @return mixed[]
     */
    public function getUdText(): array
    {
        return $this->udText;
    }


    /**
     * @return string
     */
    public function getUpdateBy(): string
    {
        return $this->updateBy;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getUpdateOn(): Alaress_Date_Format
    {
        return $this->updateOn;
    }


    /**
     * @return bool
     */
    public function isWebAccessFlag(): bool
    {
        return $this->webAccessFlag;
    }
}
