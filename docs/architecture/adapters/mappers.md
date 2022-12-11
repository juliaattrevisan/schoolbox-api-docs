# Mappers

Mappers are Schoolbox's implementation of an Object-Relational Mapper: they
allow create/read/update/delete actions on a database using objects which
represent a single row in a database table.

## Implementations

In practice, all mappers extend the `\Schoolbox\Mapper` base class: each
sub-class represents a mapping onto a single database table.

This class provides the following core methods:

- `find` methods, for finding records
- `count` methods, for fetching a count of records which match a set
- `save`, for creating new and updating existing records
- `delete`, for deleting already created records

## Instantiation

Mappers may be retrieved using the `\Schoolbox\MapperFactory` class:

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Schoolbox\MapperFactory $mapperFactory */
$mapperFactory = $di->get('mapperFactory');

$userMapper = $mapperFactory->get(\Schoolbox\UserMapper::class);
```

Less desirably, mappers are also accessible as singletons:
```php
$userMapper = \Schoolbox\UserMapper::getInstance();
```

## Usage

```php
/** @var \Schoolbox\UserMapper $userMapper **/

// Create a new user
$newUser = $userMapper->createModel();
$newUser->setFirstname('Test');
$userMapper->save($newUser);

// Fetch a single user and save
$existingUser = $userMapper->findById(1);
$existingUser->setFirstname('Test');
$userMapper->save($existingUser);

// Fetch multiple users and save
$userList = $userMapper->findByColumn('first_name', 'Test');
foreach ($userList as $user) {
    $user->setFirstname('Test2');
}
$userMapper->save($userList);

// Delete users
$userList = $userMapper->findByColumn('last_name', 'Test');
$userMapper->delete($userList);
```
