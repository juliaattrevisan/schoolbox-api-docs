<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\YearLevel;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\HasSequence;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Sequential;

/**
 * A 'YearLevel' is an educational stage, ie, a subdivision of formal learning,
 * typically covering early childhood education, primary education, secondary education and tertiary (or higher) education.
 *
 * Conceptual examples include "Prep", "2", "year 6", "post-grad" etc.
 *
 * These should be considered distinct, eg "Year 3/4" would generally be represented by some other entity
 * having relationships to both a "Year 3" and "Year 4" YearLevel/Model.
 */
class Model implements ISoftDeletable, Sequential
{
    use CreateCollection;
    use Id;
    use HasSequence;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var string */
    private $name;

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
