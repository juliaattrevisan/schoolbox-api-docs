<?php

/**
 * Authorization method - decides whether $user can manage a reporting activity which usually involves the
 * create/modify/delete functions, among others; this is based on service permissions given to $user.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\User;

class IsManageReportingActivity implements IAuthorizationRule
{
    /**
     * Constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(private IConfigLoader $configLoader)
    {
    }


    /**
     * @inheritDoc
     *
     * @param array $params
     * @param User|null $user
     * @return void
     */
    public function authorize(array $params = array(), ?User $user = null)
    {
        /* Reporting Activities need further evaluation to decide if a feature/action/ability is available.
         * This is because of the idiosyncratic nature where some staff have service permissions that allow access
         *
         * (Non-reporting activities don't need further checks.)
         */
        if (!$params['assessment'] instanceof Reporting\Model) {
            return;
        }

        /* $user must have the ability to manage reporting activities as per their LMS Moderator write+ service permission
         */
        if ((new Reporting\ValidManageActivity())->isSatisfiedBy($this->configLoader->get(ConfigMapper::NAME_REPORTING_ACTIVITY_ENABLE), $user)) {
            return;
        }

        throw new Unauthorized('User cannot modify custom participation for reporting activities');
    }
}
