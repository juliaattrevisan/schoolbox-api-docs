<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\L10n;

/**
 * An engraving method for RosettaStones.
 */
class RosettaStoneFactory
{
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
