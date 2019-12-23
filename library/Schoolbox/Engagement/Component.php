<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class Component
{
    /**
     * @var Database
     */
    private $database;

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function componentInstances($module = 'homepage') {
        $query = "SELECT component_definition.name, (
                    SELECT COUNT(*)
                    FROM component_instance WHERE component_definition.id = component_instance.`component_definition_id`
                    ) AS total
                  FROM component_definition
                  WHERE component_definition.module=:module
                  ORDER BY total DESC";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':module', $module);

        $statement->execute();
        return $statement->fetchAll();
    }

}
