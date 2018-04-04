<?php
/**
 * Schooledge implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schooledge\Ccb;

use Schoolbox\External\Connector\Schooledge\Base\Sync as BaseSync;

class Sync extends BaseSync
{
    /**
     * CCB's student contact filter also allows through any user who has the
     * custom flag "Copy-of-reports".
     *
     * @var string
     */
    protected $studentContactFilter = "(
        sc.ResidentialGuardian = 'Y' OR
        sc.Relationship = 'Father' OR
        sc.Relationship = 'Mother' OR
        sc.Relationship = 'Stepfather' OR
        sc.Relationship = 'Stepmother' OR
        sc.Relationship = 'Step Father' OR
        sc.Relationship = 'Step Mother' OR
        sc.Relationship = 'Houseparents' OR
        sc.Relationship LIKE '%Guardian%' OR
        sc.Relationship LIKE 'Foster%' OR
        sc.Relationship = 'Carer' OR
        sc.[Copy-of-reports] = 'Y'
    )";
}
