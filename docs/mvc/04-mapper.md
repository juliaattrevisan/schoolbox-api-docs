# 4 Responsibilities of Mapper
A Mapper is a database abstraction layer; that is, it hides the way database queries are executed and the resultant output's generation before being passed back to the caller. In this way, the same business logic methods such as `findById()` obscure the mechanism by which it obtains data - whether that's from a csv file, a MySQL database or an API endpoint.

> Mappers contain methods which alter persistent storage; putting data in and getting data out of that storage into a format that's compatible with Controller methods in the domain (business logic) layer.

The Mapper classes themselves should contain a set of ubiquitous or common database queries in addition to the default set of `findById()` `findByColumnList()` et al. Here's an example of database queries that are useful in multiple parts of the application:

```php
/**
 * Folder Mapper 
 */
class Mapper extends BaseMapper {

    /**
     * Find a folder by its associated Folder Code
     */
    public function findByCode($code) {
        ...
    }
    
    /**
     * Find a folder's parent instance 
     */
    public function findParent(Folder $folder) {
        ...
    }
}
```

## 4.1 Mapper Usage
Mapper calls can be somewhat expensive to use due to the sheer amount of data being obtained. On very complex data sets this can lead to terrible load times (if it loads at all) and we should avoid this situation if possible. 

> Whenever you access the database try to obtain the minimum amount of data necessary and minimise the number of database calls needed to accomplish this. Avoid postprocessing data where possible.

There are some scenarios where you can't do this very effectively - namely when you can't apply sufficient conditionals in the query itself, such as data being affected by role-based permissions checks (we can't compute those in joins). 

### 4.1.1 Fetch minimal data set vs trimming away
Obtain the minimum data set instead of getting more than you need, only to delete records you don't want in a postprocessing loop. Use limits and conditionals to restrict the data set.

Using the predicate builder:

```php
/* Do: use limits and conditionals
 */
$pb = $mapper->getPredicateBuilder()
  ->equals('a', $value)
  ->greaterThan('size', 50)
  ->isFalse('visible');

$mapper->findByPredicates($pb, [
  'order' => [ 'b' => 'ASC' ],
  'limit' => 3
]);

/* Don't: get everything and then remove the stuff you don't want or need
 */
$mapper->findByColumn('a', $value)
       ->filter(function ($obj) { return $obj->getSize() > 50 && !$obj->isVisible(); })
       ->sortBy('b', 'ASC')
       ->limit(3);
```

Using normal queries:

```php
/* Do: Use the predicate builder (see above)
 * Alternatively, use a special query
 */
$allResults = $mapper->findBySomeSpecialName($value);

/* Don't: get everything and then remove the stuff you don't want or need
 */
$allResults = $mapper->findByColumn('a', $value);

foreach ($allResults as $current) {
    if (($current->getSize() < 50) || $current->isVisible()) {
        $filteredResults[] = $current;
    }
}
```

### 4.1.2 Fetching Scalars
There are convenience methods that allow you to get the values of a particular column/property so that you don't have to hydrate a whole bunch of stuff. 

```php
/* Do: Use this convenience method instead of hydrating everything
 */
$mapper->findIdsByColumn('a', $value);

/* Don't: Hydrate everything like this then throw away data
 */
$allResults = $mapper->findByColumn('a', $value);

foreach ($allResults as $current) {
    $ids[] = $current->getId();
}

/* Don't: this is also not nice
 */
$mapper->findByColumn('a', $value)->getColumnValues('id');
```

### 4.1.3 Queries in loops
Database connections cost time, as do their queries. Instead of repeatedly getting one record at a time, fetch them all in one go.

```php
/* Do: Use a set to find all the records such that column 'a' is one of
 * the values provided in the array
 */
$mapper->findByColumn('a', [1,2,3,4,5,6]);

/* Don't: Get them all individually 
 */
foreach ([1,2,3,4,5,6] as $current) {
    $allResults[] = $mapper->findByColumn('a', $current);
}
```

Note that if you are using your own special queries and PDO, there's a `WHERE IN` equivalent in prepared statements.  

```php
/* Do: Collate a set of values to obtain in one query
 */
$values = [1,2,3,4,5,6];

$query = "
    SELECT * 
    FROM table
    WHERE FIND_IN_SET(a, :values)
";

$statement = $this->database->prepare($query);
$statement->execute([
    'values' => implode(',', $values)
]);
```

## 4.2 Special Queries
Less common or otherwise unique queries should be stored in supporting classes instead, which utilise an instance of a corresponding Mapper; otherwise people might misuse it. 

One notable pitfall is taking a method that sort of does what you need, then you add what *you* need it to do without considering the impact it has elsewhere (or lack thereof).

Eventually this method loses its specific purpose (reduces cohesion), touches and integrally affects numerous locations (high coupling) and evolves into technical debt. There's been a real-life example of this in an earlier incarnation of `findByParent($id)` in Folder Mapper; not only does it get a parent, but also a count of assessments and a set of configurations.

> Break away specific responsibilities into other classes instead. Note that Mappers expose a method that executes raw sql queries: `$mapper->getDatabase()->prepare()`
 
An example class follows. Obviously this is a fairly contrived example, but you have an entire class in which to organise your algorithms.
 
```php
class Cat {
    const SPECIAL_FOLDER = 237;

    /**
     * Cat constructor.
     */
    public function __construct(Folder\Mapper $folderMapper) {
        $this->setFolderMapper($folderMapper);
    }
    
    /**
     * Do a very specific query that nobody else really needs to know about
     * You can still use these convenience methods to save you from some hardcoding
     */
    public function doSomething() {
        $query = "
            SELECT {$folderMapper->getColumnString()}
            FROM {$folderMapper->getTable()}
            WHERE {$folderMapper->getIdentifierColumn()} = :specialFolder
        ";
    
        $statement = $this->getFolderMapper()->getDatabase()->prepare($query);
        $statement->execute([
            'specialFolder' => self::SPECIAL_FOLDER
        ]);
        
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            new \Alaress_Probe($row);
        }
    }
}
``` 
 
## 4.3 Default Hydration
Some hierarchical objects require complex hydration to take place - for example, File instances need Folder, User and Mimetypes also hydrated for it to be meaningful. 

However, manually defining and constructing this every time you do a database query is longwinded and unnecessary because we can define the default hydration that applies to base `findBy*` methods. This also prevents the usual copy-paste that would otherwise be required. 

Instead, we only need to do it once by specifying three methods in our Mapper:

* `getSelectString()` This specifies which columns and their aliases are used in the query.
* `getFromString()` This specifies which tables and joins are used in the query.
* `hydrateSelect()` This specifies how objects are hydrated into the final result.

> Use `getSelectString()`, `getFromString()` and `hydrateSelect()` to specify a default hydration for each of your basic `findBy*` queries. This ensures a uniform behaviour in hydration and assembly.

An example can be found in `library/EOTC/EventMapper.php`. For the purposes of illustration I've distilled the contents somewhat: every EOTC event has an Author, Event Type and User (Teacher In Charge) instance hydrated into it.

```php
protected function getSelectString()
{
    $authorMapper          = $this->getJoinMapper('author');
    $eventTypeMapper       = $this->getJoinMapper('eventType');
    $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

    /* Note that $this->>getColumnString() cannot use an alias like the others
     * You'll get an "Unknown column" error if you try
     */
    return "
        SELECT
            {$this->getColumnString()},
            {$authorMapper->getColumnString('a')},
            {$teacherInChargeMapper->getColumnString('c')},
            {$eventTypeMapper->getColumnString('t')}
    ";
}


protected function getFromString()
{
    return "
        FROM `{$this->table}`
        LEFT JOIN {$this->getJoinSql('author', '', 'a', true)}
        LEFT JOIN {$this->getJoinSql('teacherInCharge', '', 'c', true)}
        LEFT JOIN {$this->getJoinSql('eventType', '', 't', true)}
    ";
}


protected function hydrateSelect(\PDOStatement $statement)
{
    $authorMapper          = $this->getJoinMapper('author');
    $eventTypeMapper       = $this->getJoinMapper('eventType');
    $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

    while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

        /* Assemble event pieces
         */
        $eventItem = $this->hydrate($row);

        /* Author
         */
        /** @var User $authorItem */
        $authorItem = $authorMapper->hydrate($row, 'a');
        $eventItem->setAuthor($authorItem);
        
        ...
        ...
        ...
    }
}
```

## 4.4 Caching
Due to the frequency of hydrations and the need to reduce computational overhead, there's a caching system built into all the base mapper methods prefixed by `find`, such as `findById()`, `findByColumn()`, `findByColumnList()` etc. 

The cache remembers global instantiations of objects. If it was retrieved at some point in the past, it won't be fetched and rehydrated. This saves on some processing at the expense of storage space. We used to make our own hashtables locally but that tradition has since been superseded by the mapper cache.

Unfortunately it also has the side effect of caching when you didn't want or realise, leading to anomalous behaviours. This isn't an error state, so you won't be alerted; you'll just have to be vigilant when testing. You can disable the cache like this:

```php
$uncached = $mapper->findByColumn('id', 1, ['cache' => false]);
```

You can disable the cache on a per-call basis for

* `findByColumn()`
* `findByColumnList()`
* `findOneByColumn()`
* `findOneByColumnList()`

> If you encounter strange mapper/instance behaviour, try turning off the mapper cache by passing the `cache => false` option.

## 4.5 Useful Methods
These base methods should solve most of your database retrieval needs. You can pass it an additional `$options` array for further configuration such as including soft-deleted objects, caching, ordering and limits.

* `findOneByColumn()` Find exactly one model to return for a specified column and value; throws exception otherwise.
* `findOneByColumnList()` Find exactly one model to return for an associative array of column-value pairs; throws exception otherwise.
* `findIdsByColumn()` Finds only the scalar ID value of records matched, rather than the full objects, for the specified column and value.
* `findIdsByColumn()` Finds only the scalar ID value of records matched, rather than the full objects, for associative array of column-value pairs.
* `findByPredicates()` Finds a set of models that match the provided predicates (builds a `WHERE` clause using objects)
* `findByIdWithFallback()` Finds exactly one model, even if it's been soft-deleted.
    
There are other public methods in the Mapper which you might find useful in more specific situations.

Note that some methods allow you to specify an `$options` array. In this array you can supply settings for ordering, limits, whether to bypass the cache and whether to include deleted records.

```php
$user = $userMapper->findByColumnList(
    ['username' => $username], 
    [
        'cache' => false,
        'deleted' => false,
        'limit' => 1,
        'order' => [
            'firstname' => 'ASC'
        ],
    ]
);
```