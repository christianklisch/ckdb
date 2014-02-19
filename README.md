# CKDB v. 0.2.0

CKDB is a key-object flat file database for php scripts

Use it:
* For small fast websites with simple data model
* If no external database available (e.g. free webspace)
* When small object-based database without complex configuration
* For fast copy between webservers

Features:
* Easy configuration
* Map your entity classes without change them
* key-object based
* Class relations
* Default database functions for insert, update, delete objects
* Repository for select by property value or ranges, order by properties
* Fast indexfile 
* Because of one file per entity parallel write access on different entities possible

## Installation

You can install the script manually using the `require` method:

```php
require 'CKDB.php';
```

* Add write permissions for database directory (in configuration 'databasepath')
* Check webspace, because filebased datastore need space

## Deploying

Include the script in your project either with Composer or via the manual `require` method and create a new instance of the class, using the appropriate parameters if needed:

```php
$em = new CKDB($config);
```

## Configure

Set:
*  array of paths to entity classes to persist in database (not selected entities won't be saved as own record in database)
*  path of database (check write permissions for script). Each entity gets own storage subdirectory
*  compression active (not implementet yet)

```php

$config = array(
    'entitypaths' => array('path/to/your/entities'),
    'databasepath' => 'database',
    'compression' => 1,
);

$em = new CKDB($config);
```

### Configure your data model

The following defined class properties will be added to database index.

Define ONE primary key for each class in array:
```php
$primkeys = array(
    'User' => 'id'
);
$em->setPrimaryKeys($primkeys);
```

Define foreign keys which you want to use in selection and order functions. Each array value is one field in entitiy 'User':
```php
$forkeys = array(
    'User' => array('id', 'firstname', 'email')
);
$em->setForeignKeys($forkeys);
```

Define referenced classes for properties. Instead of object primary key will be saved in database index.
If you don't define referenced classes, subclasses will not be saved as own database record and are not searchable by own repository.
In this example the field 'homeaddress' referenced to entities of class 'Address':
```php
$refclasses = array(
    'User' => array('homeaddress' => 'Address')
);
$em->setReferenceClasses($refclasses);
```


## Use CKDB Database

### Default functions

Persist an object with:
```php
$u = new User();
$u->setId('4711'); 
$u->setFirstname('George');
$u->setEmail('george@mail.com');  
                 
$em->persist($u);
```
Referenced objects will be persist automatically. After changing your object you have to persist it.

Delete an object with:
```php                 
$em->remove($u);
```
Object is identified by type of class and defined primary key! Don't change primary keys!


### Reindex database

To rebuild the whole index per entity class use the reIndex()-function;
```php
$em->reIndex('User');                                                  
```

Method should be called after much changes on index properties or object deletions. Don't call it on time critical processes.

### Search

To search for one or more stored objects you should use a repository:
```php
$userRepository = $em->getRepository('User')                                                
```

CKDB provides following methods to find entities after calling find() of repository:
* equals (matching array with field = > value)
* notEquals (matching array with field = > value)
* lt lte (lower than - matching array with field = > value)
* gt gte (lower than - matching array with field = > value)
* in (array key must contain field value)
* notIn (array key must not contain field value)

Furthermore you can sort the results with the sortBy()-method of repository.

Example selection:
```php
$userRepository->find()->equals(array('firstname' => 'George'))->gt(array('age' => '50'))->sortBy('age', SORT_DESC)->getResult();                                             
```
This selection searches in field 'firstname' for 'George' with an 'age' older than 50 years (greater than = gt). The users should be sordet descending. The getResult()-method returns an array of found objects in database.

Search in value list with in() and select all Martins and Georges:
```php
$userRepository->find()->in('firstname', array('Martin','George'))->getResult();                                             
```

Search in object list with in() and match entries in other array list (i.E. all childs of father Mo Miller):
```php

$fathers = $userRepository->find()->equals(array('firstname' => 'Mo', 'lastname' => 'Miller'))->getResult();    
$userRepository->find()->in('father', $fathers)->getResult();                                             
```

### Sort

The sortBy()-method sort the results before returning them. The first parameter defines the sort-field, the second the sort order. You can sort:
* SORT_ASC ascending
* SORT_DESC descending

Example
```php
$userRepository->find()->gt(array('age' => '50'))->sortBy('age', SORT_DESC)->getResult();  
```
Select all users older than 50 years and sort by age descending.

## Todos
* Documentation
* Add exceptions
* Add search-method like()
* Add joining referenced entities

## Contributors

* Christian Klisch http://www.christian-klisch.de


## Copyright and license

Copyright 2014 Christian Klisch, released under [the Apache](LICENSE) license.
