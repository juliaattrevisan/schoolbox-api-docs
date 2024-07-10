<?php

/**
 * Template
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use Schoolbox;
use Schoolbox\Mapper\Exception;

/**
 * @method Template findById($identifierValue)
 * @method Template createModel($idValue = null)
 */
class TemplateMapper extends Schoolbox\Mapper
{
    /**
     * Define column/property mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('eportfolioTemplate');
        $this->hasModel(Template::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('layout', self::TYPE_INTEGER);
        $this->uses('left_column_width', self::TYPE_DECIMAL);
        $this->uses('right_column_width', self::TYPE_DECIMAL);
    }

    /**
     * Import a Collection of templates
     *
     * @param Schoolbox\Collection $allTemplates
     */
    public function import(Schoolbox\Collection $allTemplates)
    {
        foreach ($allTemplates as $currentTemplate) {
            $template = new Template();
            $template->setName($currentTemplate['name']);
            $template->setLeftColumnWidth($currentTemplate['leftColumnWidth']);
            $template->setRightColumnWidth($currentTemplate['rightColumnWidth']);

            $ids = $this->save($template);

            if (is_array($ids) && count($ids) && is_numeric($ids[0])) {
                $templateId = $ids[0];

                /* Process each component if the template saved correctly
                 */
                foreach ($currentTemplate['components'] as $currentComponent) {
                    $componentDefinition   = \Component_DefinitionMapper::getInstance()->findByColumn('class', $currentComponent['class']);
                    $componentDefinitionId = $componentDefinition->first()->getId();
                    /* Spawn a component instance for the template
                     */
                    $componentInstance = new \Component_Instance();
                    $componentInstance
                        ->setDefinitionId($componentDefinitionId)
                        ->setModule('eportfolio_template')
                        ->setIdentifier($templateId)
                        ->setPosition($currentComponent['keys']['position'])
                        ->setSequence($currentComponent['keys']['sequence']);

                    $instanceIds = \Component_InstanceMapper::getInstance()->save($componentInstance);

                    /* Apply config keys for each component
                     */
                    if (is_array($instanceIds) && count($instanceIds) && is_numeric($instanceIds[0])) {
                        $instanceId = $instanceIds[0];

                        foreach ($currentComponent['keys'] as $currentKey => $currentValue) {
                            $componentConfigDefinition = \Component_ConfigDefinitionMapper::getInstance()->findByColumnList(array(
                                'component_definition_id' => $componentDefinitionId,
                                'key' => $currentKey,
                            ));

                            /* Put in a new instance
                             */
                            if ($componentConfigDefinition->count()) {
                                $componentConfigInstance = new \Component_Config();
                                $componentConfigInstance
                                    ->setConfigDefinitionId($componentConfigDefinition->first()->getId())
                                    ->setInstanceId($instanceId)
                                    ->setValue($currentValue);

                                \Component_ConfigMapper::getInstance()->save($componentConfigInstance);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Deletes a component instance and its config in a single call
     *
     * @param Template $object
     * @return bool
     * @throws Exception
     */
    public function delete($object)
    {

        /* Deletes all config instances related to this eportfolio template */
        $query = "DELETE ci, cci FROM component_instance ci
                  LEFT JOIN component_config_instance cci ON cci.component_instance_id = ci.id
                  WHERE ci.module = :module
                  AND identifier = :identifier";

        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'module'     => 'eportfolio_template',
                'identifier' => $object->getId(),
            )
        );

        return parent::delete($object);
    }
}
