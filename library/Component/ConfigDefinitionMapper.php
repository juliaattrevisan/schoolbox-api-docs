<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 * @
 */

/**
 * Class Component_ConfigDefinitionMapper
 * @method Component_ConfigDefinition findOneByColumnList($columnList, array $options = [])
 */
class Component_ConfigDefinitionMapper extends Schoolbox\Mapper
{

    protected $table = 'component_config_definition';

    protected function mapping()
    {
        $this->hasTable('component_config_definition');
        $this->hasModel(Component_ConfigDefinition::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('component_definition_id', self::TYPE_INTEGER, null, 'definitionId');
        $this->uses('key', self::TYPE_STRING);
        $this->uses('default_value', self::TYPE_STRING);
        $this->uses('label', self::TYPE_STRING);
    }
}
