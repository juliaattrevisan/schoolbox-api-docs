<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\L10n;

/**
 * An engraving method for RosettaStones.
 */
class RosettaStoneFactory
{
    /**
     * Added to allow testing of updates/23.0.alpha1/calendarConnector/02-migrateData.php, using var_export() and eval().
     *
     * @param array $state
     * @return RosettaStoneFactory
     */
    public static function __set_state(array $state)
    {
        $obj = new self;
        return $obj;
    }

    /**
     * Engraves a rosetta stone based on the contents of a l10n mapper.
     *
     * @param Mapper $mapper
     * @return RosettaStone
     */
    public function build(Mapper $mapper)
    {
        return new RosettaStone($mapper->findAll());
    }
}
