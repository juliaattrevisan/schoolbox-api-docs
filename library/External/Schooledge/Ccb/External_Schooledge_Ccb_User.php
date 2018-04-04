<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);


/**
 * Class External_Schooledge_Ccb_User
 */
class External_Schooledge_Ccb_User extends External_Schooledge_User
{
    /**
     * The filter for a student's contacts - this filters out anyone who's not
     * a primary guardian or parent.
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
