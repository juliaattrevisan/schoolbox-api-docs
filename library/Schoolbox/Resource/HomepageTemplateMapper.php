<?php

/**
 * This mapper created objects of type HomepageTemplate
 * The table contains a list of all the homepage components
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception\CallbackException;

/**
 * Class HomepageTemplateMapper
 *
 * @package Schoolbox\Resource
 * @method HomepageTemplate findById($id)
 * @method HomepageTemplate createModel($idValue = null)
 */
class HomepageTemplateMapper extends Mapper
{
    protected function mapping()
    {
        $this->hasModel(HomepageTemplate::class);
        $this->hasTable('homepageTemplate');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('left_column_width', self::TYPE_DECIMAL);
        $this->uses('right_column_width', self::TYPE_DECIMAL);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateName']);
    }

    /**
     * Deletes a component instance and its config in a single call
     *
     * @param HomepageTemplate $object
     * @return bool
     */
    public function delete($object)
    {

        /* Deletes all config instances related to this homepage template */
        $query = "DELETE ci, cci FROM component_instance ci
                  LEFT JOIN component_config_instance cci ON cci.component_instance_id = ci.id
                  WHERE ci.module = :module
                  AND identifier = :identifier";

        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'module'     => 'homepage_template',
                'identifier' => $object->getId(),
            )
        );

        return parent::delete($object);
    }


    /**
     * Find the superset of templates from which a Homepage Template may be administered
     * and also provide a No Homepage option which normally doesn't exist in administration
     *
     * @param array $options
     * @return \Schoolbox\GenericCollection
     * @throws Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function findWithNoHomepageTemplate($options = array())
    {
        $templates = parent::findAll($options);
        $templates->prepend($this->getNoHomepageTemplate());
        return $templates;
    }


    /**
     * @return HomepageTemplate
     * @throws Mapper\Exception
     */
    public function getNoHomepageTemplate()
    {
        $templateNone = $this->createModel();
        $templateNone->setId(HomepageTemplate::HOMEPAGE_NONE);
        $templateNone->setName("No Homepage");
        return $templateNone;
    }

    /**
     * @return HomepageTemplate
     * @throws Mapper\Exception
     */
    public function getKeepExistingHomepageTemplate()
    {
        $templateNone = $this->createModel();
        $templateNone->setId(HomepageTemplate::HOMEPAGE_KEEP_EXISTING);
        $templateNone->setName("Keep Existing");
        return $templateNone;
    }

    /**
     * Validates that the name is not empty and is unique
     *
     * @param HomepageTemplate $model
     * @return bool
     * @throws CallbackException
     */
    protected function validateName(HomepageTemplate $model): bool
    {
        if (trim($model->getName()) === '') {
            throw new CallbackException("Homepage Template name cannot be empty");
        }

        if (!$this->isUnique(['name' => $model->getName()], $model->getId())) {
            throw new CallbackException(
                sprintf(
                    'A homepage template with the name "%s" already exists, you must have a unique name',
                    $model->getName()
                )
            );
        }

        return true;
    }
}
