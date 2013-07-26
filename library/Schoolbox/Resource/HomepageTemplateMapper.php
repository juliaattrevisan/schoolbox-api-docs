<?php
/**
 * This mapper created objects of type HomepageTemplate
 * The table contains a list of all the homepage components
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;
use Schoolbox\Mapper;
use Schoolbox\Collection;


class HomepageTemplateMapper extends Mapper {

	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Resource\\HomepageTemplate');
		$this->hasTable('homepageTemplate');
		$this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
            'column'     => 'left_column_width',
            'property'   => 'leftColumnWidth',
            'type'       => self::TYPE_DECIMAL,
        ));
		$this->hasMap(array(
            'column'     => 'right_column_width',
            'property'   => 'rightColumnWidth',
            'type'       => self::TYPE_DECIMAL,
        ));
	}

    public function delete(HomepageTemplate $object) {

        /* Deletes all config instances related to this homepage template */
        $query = "DELETE ci, cci FROM component_instance ci
			      LEFT JOIN component_config_instance cci ON cci.component_instance_id = ci.id
			      WHERE ci.module = :module
                  AND identifier = :identifier";

        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'module'        => 'homepage_template',
                'identifier'   => $object->getId(),
            )
        );

        parent::delete($object);
    }
}