<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\IMapper;
use Schoolbox\User as UserModel;
use SensitiveParameter;

/**
 * Sets the nationality collection in the user based on the value of a key in the imported row,
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Nationality implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * Constructor
     *
     * @param string $fieldname
     */
    public function __construct($fieldname)
    {
        $this->fieldname = $fieldname;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(#[SensitiveParameter] array $row, UserModel $user): UserModel
    {

        $nationalityMapper = UserModel\Nationality\Mapper::getInstance();
        if (isset($row[$this->fieldname])) {
            $collection = $nationalityMapper->createCollection();
            $values = explode(',', $row[$this->fieldname]);
            foreach ($values as $value) {
                $value = trim($value);
                if ('' === $value) {
                    continue;
                }
                $collection->add(
                    $nationalityMapper
                        ->createModel()
                        ->setNationality($value)
                        ->setUser($user)
                );
            }
            return $user->setNationalityCollection($collection);
        }
        return $user;
    }
}
