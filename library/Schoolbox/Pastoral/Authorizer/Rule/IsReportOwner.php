<?php

/**
 * Determine if a pastoral report is owned by the logged in user
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\User;

class IsReportOwner implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (empty($params['report']) && empty($params['reportId'])) {
            return true;
        }

        if (!isset($params['report'])) {
            if (!isset($params['reportId'])) {
                throw new Unauthorized();
            } else {
                try {
                    $params['report'] = PastoralReportMapper::getInstance()->findById($params['reportId']);
                } catch (MapperException $e) {
                    throw new Unauthorized();
                }
            }
        }

        if (!$params['report'] instanceof PastoralReport) {
            throw new Unauthorized();
        }

        if ($params['report']->getOwner()->getId() == $user->getId()) {
            return true;
        }

        throw new Unauthorized();
    }
}
