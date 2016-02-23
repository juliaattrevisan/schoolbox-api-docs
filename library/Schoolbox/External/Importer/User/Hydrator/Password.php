<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Alaress\Password\Password as PasswordGenerator;
use Schoolbox\User as UserModel;

/**
 * Sets the user's password, if it exists in the row and it isn't empty.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Password extends Field
{
    /**
     * @var PasswordGenerator
     */
    protected $passwordGenerator;

    /**
     * Constructor
     *
     * @param string            $fieldname
     * @param string            $setMethod
     * @param PasswordGenerator $passwordGenerator
     */
    public function __construct($fieldname, $setMethod, PasswordGenerator $passwordGenerator)
    {
        parent::__construct($fieldname, $setMethod);
        $this->passwordGenerator = $passwordGenerator;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        if (isset($row[$this->fieldname]) && strlen($row[$this->fieldname]) > 0) {
            $password = $this->passwordGenerator->hash($row[$this->fieldname]);
            return call_user_func([$user, $this->setMethod], $password);
        }
        return $user;
    }
}
