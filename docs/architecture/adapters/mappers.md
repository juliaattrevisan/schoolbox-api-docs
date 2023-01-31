# Mappers

Mappers are Schoolbox's implementation of an Object-Relational Mapper: they
allow create/read/update/delete actions on a database using objects which
represent a single row in a database table.

## Definition

In practice, all mappers extend the `\Schoolbox\Mapper` base class: each
sub-class represents a mapping onto a single database table.

This class provides the following core methods:

- `find` methods, for finding records
- `count` methods, for fetching a count of records which match a set
- `save`, for creating new and updating existing records
- `delete`, for deleting already created records

### Mapper

```php
declare(strict_types=1);

namespace Schoolbox\MyModule;

class Mapper extends \Schoolbox\Mapper
{
    /**
     * Mappers must at least implement this method, for defining the mapping
     * onto a database table.
     */
    public function mapping()
    {
        // This mapper maps onto this table
        $this->hasTable('my_table');

        // This mapper returns and accepts
        $this->hasModel(Model::class);

        // maps column "id" as an int and as the identifier:
        // thus `findById` looks up on this column, and if this property is
        // blank in a model when saved, a new row is inserted.
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        // maps column "first_name" as a string:
        // by default, this maps onto get/set methods matching the camel-cased
        // field name (in this case, `firstName`)
        $this->uses('first_name', self::TYPE_STRING);

        // maps column "surname" as a string onto property `lastName`
        $this->uses('surname', self::TYPE_STRING, null, 'lastName');

        // maps column "type" as a set of enumerable values
        $this->uses('type', self::TYPE_STRING, self::COLUMN_ENUM, null, [...]);

        // maps standard created/updated/deleted fields:
        // these update automatically on create/update/delete of the row
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // join on a foreign key relation
        $this->joins(
            // The foreign key column on this mapper's table
            'related_table_id',
            // The class name of the mapper which maps the table to join onto
            RelatedMapper::class,
            // The foreign key column on the other table: usually just the ID
            'id',
            // Hydrator: nearly always null
            null,
            // Options: nearly always null
            null,
            // Property via which the joined model will be accessible on this
            // mapper's models
            'relatedModel',
            // Name for this join: used when building queries
            'relation',
        );
    }

    /**
     * If there are any joined models which should be hydrated as part of a
     * standard `find` action: the below three methods should be defined.
     *
     * This method defines the `SELECT` part of the query.
     */
    protected function getSelectString(): string
    {
        // Fetch this model's fields, and the relation's fields prefixed with
        // alias "r".
        return "
            SELECT
            {$this->getColumnString()},
            {$this->getJoinColumnString('relation', 'r')}
        ";
    }

    /**
     * This method defines the `FROM` part of the query.
     */
    protected function getFromString(): string
    {
        // Fetch from this model's table, and join onto the relation's table
        // with alias "r".
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('relation', '', 'r', true)}
        ";
    }

    /**
     * This method defines how a row should be hydrated into a model once
     * returned.
     */
    protected function hydrateSelect(\PDOStatement $statement): \Schoolbox\Collection
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);
            $model->setRelatedModel(
                $this->hydrateJoin($row, 'relation', 'r')
            );
            $collection[$model->getId()] = $model;
        }
        return $collection;
    }
}
```

### Model

```php
declare(strict_types=1);

namespace Schoolbox\MyModule;

class Model
{
    // Standard traits for standard fields
    use \Schoolbox\Mapper\Traits\Model\Id;
    use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
    use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
    use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;

    // These properties should match what is defined in the model
    private string $firstName;
    private string $lastName;
    private string $type;
    private ?RelatedModel $relatedModel;

    // Getters and setters are needed for each field

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    ...
}
```

## Instantiation

Mappers may be retrieved using the `\Schoolbox\MapperFactory` class:

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Schoolbox\MapperFactory $mapperFactory */
$mapperFactory = $di->get('mapperFactory');

$mapper = $mapperFactory->get(\Schoolbox\MyModule\Mapper::class);
```

Less desirably, mappers are also accessible as singletons:
```php
$mapper = \Schoolbox\MyModule\Mapper::getInstance();
```

## Usage

```php
/** @var \Schoolbox\MyModule\Mapper $mapper **/

// Create a new row
$newModel = $mapper->createModel();
$newModel->setFirstname('Test');
$mapper->save($newModel);

// Fetch a single row and save
$existingModel = $mapper->findById(1);
$existingModel->setFirstname('Test');
$mapper->save($existingModel);

// Fetch multiple rows and save
$modelList = $mapper->findByColumn('first_name', 'Test');
foreach ($modelList as $model) {
    $model->setFirstname('Test2');
}
$mapper->save($modelList);

// Delete rows
// If the mapper has a soft-delete column, this field will be set instead of
// deleting the row completely.
$modelList = $mapper->findByColumn('last_name', 'Test');
$mapper->delete($modelList);
```
