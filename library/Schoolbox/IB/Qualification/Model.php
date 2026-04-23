<?php

/**
 * IB Qualification Model
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Qualification;

use JsonSerializable;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Sequential;
use Schoolbox\IB\SystemPurpose\Model as SystemPurposeModel;

/**
 * Class Model
 *
 * @package Schoolbox\IB\Qualification
 */
class Model implements ISoftDeletable, Sequential
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;
    use ModelTraits\HasSequence;

    /**
     * Qualification Name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * System Purpose for this qualification
     * @var SystemPurposeModel|null
     */
    private ?SystemPurposeModel $systemPurpose = null;

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return SystemPurposeModel|null
     */
    public function getSystemPurpose(): ?SystemPurposeModel
    {
        return $this->systemPurpose;
    }

    /**
     * @param SystemPurposeModel|null $systemPurpose
     * @return self
     */
    public function setSystemPurpose(?SystemPurposeModel $systemPurpose): self
    {
        $this->systemPurpose = $systemPurpose;
        return $this;
    }
}
