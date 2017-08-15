<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * @
 */
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Realtime\Message\NSMapper;

/**
 * Class Component_DefinitionMapper
 *
 * @method Component_Definition findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 * @method Component_Definition findById($identifierValue)
 * @method Component_Definition hydrate(array $row, $alias = '', $callback = null)
 */
class Component_DefinitionMapper extends Schoolbox\Mapper {

    /**
     * Define this mapper's mappings
     */
	protected function mapping ()
    {
		$this->hasTable('component_definition');
		$this->hasModel(Component_Definition::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('class', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('module', self::TYPE_STRING);
        $this->uses('visible', self::TYPE_STRING);
        $this->uses('multiple', self::TYPE_STRING);
        $this->uses('component_category', self::TYPE_STRING);
        $this->joins('msg_namespace_slug', NSMapper::class, 'slug', null, null, 'namespace', 'namespace');
	}


    /**
     * Default to sorting by module, then name
     *
     * @param array $options
     * @return GenericCollection
     */
    public function findAll($options = [])
    {
        if (empty($options['sort'])) {
            $options['order'] = [
                'module' => 'ASC',
                'name'   => 'ASC'
            ];
        }

        return parent::findAll($options);
    }

    /**
     * Return the first part of the string required to select from this mapper.
     * Contains a join to a ridonkeykonk lookup table that defies ALL conventions
     *
     * @throws \Schoolbox\Mapper\DefinitionException
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()}, 
                   hcc.homepageComponentCategoryID `hcc.id`, hcc.name `hcc.name`,
                   {$this->getJoinColumnString('namespace', 'ns')}
            FROM {$this->getTable()}
            LEFT JOIN homepageComponentCategory hcc ON component_definition.component_category = hcc.homepageComponentCategoryID
            LEFT JOIN {$this->getJoinSql('namespace', '', 'ns')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @throws \Schoolbox\Mapper\Exception
     * @return GenericCollection|Component_Definition[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            $model = $this->hydrate($row);

            if ($row['hcc.id']) {
                // handle the obscene lookup
                $model->setComponentCategory($row['hcc.id']);
                $model->setComponentCategoryName($row['hcc.name']);
            }

            if ($row['ns.slug']) {
                // if null, leave it as null since it's optional
                $model->setNamespace($this->hydrateJoin($row, 'namespace', 'ns'));
            }

            $c->add($model);
        }

        return $c;
    }


    /**
     * Builds a where part and params for this query out of a column list
     *
     * @param PredicateBuilder $predicate
     * @param  array           $columnList
     * @param  array           $options
     *
     * @return array where:  The where parts of the query
     *               params: the parameters
     * @throws \UnderflowException
     */
    protected function buildWherePart(PredicateBuilder $predicate, $columnList, $options)
    {
        // hide invisible components from all queries EXCEPT the ones that explicitly ask for it
        // it's like a soft-delete, but with an inverted boolenum, because SOMEONE thought that was ever a good idea...
        if (!(isset($options['hidden']) && $options['hidden'])) {
            $predicate->equals('visible', 'true'); // boolenums... urgh...
        }

        return parent::buildWherePart($predicate, $columnList, $options);
    }


	/**
	 * Find all component definitions including the hidden ones (uses in admin 'Components' list editor)
	 *
	 * @return \Schoolbox\Collection
	 */
	public function findAllIncludeHidden()
    {
	    return $this->findAll([ 'hidden' => true ]);
	}

	/**
	 * Gets all component definitions for this module.
	 *
	 * @param string $module
	 * @param string $sortBy
	 *
	 * @return \Schoolbox\Collection
	 */
	public function findAllForModule($module, $sortBy = '')
    {
	    return $this->findByColumn('module', $module, [
	        'order' => [
	            $sortBy => 'ASC',
                'name'  => 'ASC'
            ]
        ]);
	}


	/**
	 * Create new custom components that need to be inserted AFTER the other new definitions
	 *
	 * Takes component structures in the following format (yes i know it should be in a Definition Object.. but it doesnt have support for config)
	 *
	 * $classList = array(
				'name'  => 'Class List',
				'module' => 'homepage',
				'component_category' => '3',
				'class' => 'Component_Homepage_ClassListController',
				'description' => 'Show your students portraits on your class homepage',
				'multiple' => 'false',
				'config' => array(
					'displayPortraits' => array(
						'default_value' => 'grid',
						'label' => 'Layout Style'
					),
				)
			);
	 *
	 */
	public function create($component) {


		if (isset($component['config'])) {
			$config = $component['config'];
			unset($component['config']);
		}

		$pdoFields = array();
		foreach (array_keys($component) as $f) {
			$pdoFields[] = ':' . $f;
		}
		$pdoString = implode(', ', $pdoFields);
		$colString = implode('`, `', array_keys($component));

		$sql = "INSERT INTO `component_definition` (`$colString`) VALUES ($pdoString)";

		if ($this->database->prepare($sql)->execute($component)) {
			if (is_array($config)) {
				$defnID = $this->database->lastInsertId();
				$sql = "
					INSERT INTO `component_config_definition`
					  (`component_definition_id`, `key`, `default_value`, `label`)
					VALUES
					  (:ccid, :key, :default_value, :label)
					ON DUPLICATE KEY UPDATE `default_value` = :default_value, `label` = :label";
				$stmt = $this->database->prepare($sql);


				foreach ($config as $key => $c) {
					$stmt->execute(
						array(
							'ccid' => $defnID,
							'key' => $key,
							'default_value' => $c['default_value'],
							'label' => $c['label']
						)
					);
				}
			}
		}
	}
}
