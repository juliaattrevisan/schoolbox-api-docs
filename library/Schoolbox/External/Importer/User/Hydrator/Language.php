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
 * Sets the language collection in the user based on the value of a key in the imported row
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Language implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * @var IMapper
     */
    protected $mapper;

    /**
     * Constructor
     *
     * @param string $fieldname
     */
    public function __construct($fieldname, $mapper)
    {
        $this->fieldname = $fieldname;
        $this->mapper = $mapper;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(#[SensitiveParameter] array $row, UserModel $user): UserModel
    {
        if (isset($row[$this->fieldname])) {
            $collection = $this->mapper->createCollection();
            $values = explode(',', $row[$this->fieldname]);
            foreach ($values as $value) {
                $value = trim($value);
                if ('' === $value) {
                    continue;
                }
                $collection->add(
                    $this->mapper->createModel()
                        ->setLanguage($value)
                        ->setUser($user)
                );
            }
            return $user->setLanguageCollection($collection);
        }
        return $user;
    }
}
