<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Alaress\Password\Service as PasswordService;
use Schoolbox\User as UserModel;

/**
 * Sets the user's password, if it exists in the row and it isn't empty.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Password extends Field
{
    /**
     * @var PasswordService
     */
    protected $passwordService;

    /**
     * Constructor
     *
     * @param string          $fieldname
     * @param string          $setMethod
     * @param PasswordService $passwordService
     */
    public function __construct($fieldname, $setMethod, PasswordService $passwordService)
    {
        parent::__construct($fieldname, $setMethod, false);
        $this->passwordService = $passwordService;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        if (isset($row[$this->fieldname]) && strlen($row[$this->fieldname]) > 0) {
            $password = $this->passwordService->hash($row[$this->fieldname]);
            return call_user_func([$user, $this->setMethod], $password);
        }
        return $user;
    }
}
