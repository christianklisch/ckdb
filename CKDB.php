<?php

/**
 * CKDB - a flat file nosql database
 *
 * @author      Christian Klisch <info@christian-klisch.de>
 * @copyright   2014 Christian Klisch
 * @link        https://github.com/christianklisch/CKDB
 * @license     https://github.com/christianklisch/CKDB/LICENSE
 * @version     0.2.0
 * @package     CKDB
 *
 * APACHE LICENSE
 *
 * Copyright (c) 2014 Christian Klisch
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * CKDB Entity Manager of flat file database.
 * @package CKDB
 * @author  Christian Klisch
 * @since   0.1.0
 */
class CKDB {

    /**
     * Settings
     * @var array
     */
    protected $settings;

    /**
     * @var array persisted classes
     */
    protected $classes;

    /**
     * @var array unused yet
     */
    protected $classKeys;

    /**
     * @var array foreign keys
     */
    protected $foreignkeys;

    /**
     * @var array
     */
    protected $primarykeys;

    /**
     * @var array
     */
    protected $referenceclasses;

    /**
     * @var index-array file
     */
    protected $indexfile = 'index.php';

    /**
     * @var extension of serialized objects on storage
     */
    protected $databaseextension = '.db';

    /**
     * @var array simple cache of already readed objects
     */
    protected $readCache = array();

    /*     * ******************************************************************************
     * Instantiation and Configuration
     * ***************************************************************************** */

    /**
     * Constructor
     * @param  array $settings Associative array of caching settings
     */
    public function __construct($settings = array()) {
        // Setup caching
        $this->settings = array_merge($this->getDefaultSettings(), $settings);

        $this->classLoading($this->getConfig('entitypaths'));
    }

    private function classLoading($directories) {
        $this->classes = array();
        foreach ($directories as $directory) {
            $files = glob($directory . '/*.php', GLOB_MARK);
            foreach ($files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    $this->classes[] = ucfirst(basename($file, '.php'));
                } else {
                    return false;
                }
            }
        }
    }

    /**
     * Get default ckdb settings
     * @return array
     */
    private function getDefaultSettings() {
        return array(
            'entitypaths' => array("entity"),
            'databasepath' => 'database',
            'compression' => 0,
        );
    }

    /**
     * Read and Write config
     * @param  $name setting name
     * @return string
     */
    public function getConfig($name) {
        return isset($this->settings[$name]) ? $this->settings[$name] : null;
    }

    /**
     * Setting primary keys
     * @param  array $primkeys Associative array of primary-keys
     */
    public function setPrimaryKeys($primkeys = array()) {
        $this->primarykeys = $primkeys;
    }

    /**
     * Setting foreign keys
     * @param  array $forkeys Associative array of  foreign-keys
     */
    public function setForeignKeys($forkeys = array()) {
        $this->foreignkeys = $forkeys;
    }

    /**
     * Setting reference classes for each reference property
     * @param  array $forkeys Associative array of reference classes and properties
     */
    public function setReferenceClasses($refclasses = array()) {
        $this->referenceclasses = $refclasses;
    }

    /**
     * Create and return database repository for defined class
     * @param  var $classname Name of class
     */
    public function getRepository($classname) {
        return new CKDBRepository($classname, $this->getConfig('databasepath'), $this->getConfig('compression'), $this->classes, $this->classKeys, $this->foreignkeys, $this->primarykeys, $this->referenceclasses, $this->indexfile, $this->databaseextension, $this->readCache);
    }

    /**
     * Persist object and referenced objects
     * @param  var $myObj Object to save/update
     */
    public function persist($myObj) {
        $classname = get_class($myObj);
        if (in_array($classname, $this->classes))
            $this->getRepository($classname)->persist($myObj);
    }

    /**
     * Find object by primary key value for defined class
     * @param  var $classname class type
     * @param  var $id primary key value
     */
    public function findById($classname, $id) {
        if (in_array($classname, $this->classes))
            return $this->getRepository($classname)->findById($id);
        return null;
    }

    /**
     * Recreate index file for class
     * @param  var $classname class type
     */
    public function reIndex($classname) {
        if (in_array($classname, $this->classes))
            $this->getRepository($classname)->reIndex();
    }

    /**
     * Delete object from storage
     * @param  var $myObj Object to delete
     */
    public function remove($myObj) {
        $classname = get_class($myObj);
        if (in_array($classname, $this->classes))
            return $this->getRepository($classname)->remove($myObj);
        return null;
    }

    /**
     * Find and return all objects of class
     * @param  var $classname class type
     */
    public function findAll($classname) {
        if (in_array($classname, $this->classes))
            return $this->getRepository($classname)->findAll();
        return null;
    }

    /**
     * Return repository for find by selection criteria
     * @param  var $classname class type
     */
    public function find($classname) {
        if (in_array($classname, $this->classes))
            return $this->getRepository($classname)->find();
        return null;
    }

}

/**
 * CKDB Repository to manage one entity class.
 * @package CKDB
 * @author  Christian Klisch
 * @since   0.1.0
 */
class CKDBRepository {

    /**
     * @var class name for repository
     */
    protected $classname;

    /**
     * @var array persisted classes
     */
    protected $classes;

    /**
     * @var array unused yet
     */
    protected $classKeys;

    /**
     * @var array foreign keys
     */
    protected $foreignkeys;

    /**
     * @var array
     */
    protected $primarykeys;

    /**
     * @var array
     */
    protected $referenceclasses;

    /**
     * @var index-array file
     */
    protected $indexfile = 'index.php';

    /**
     * @var extension of serialized objects on storage
     */
    protected $databaseextension = '.db';

    /**
     * @var array simple cache of already readed objects
     */
    protected $readCache = array();

    /**
     * Constructor
     * @param  array $settings Associative array of caching settings
     */
    public function __construct($classname, $databasepath, $compressionlevel, $classes, $classKeys, $foreignkeys, $primarykeys, $referenceclasses, $indexfile, $databaseextension, $readCache) {
        $this->classname = $classname;
        $this->databasepath = $databasepath;
        $this->compressionlevel = $compressionlevel;
        $this->classes = $classes;
        $this->classKeys = $classKeys;
        $this->foreignkeys = $foreignkeys;
        $this->primarykeys = $primarykeys;
        $this->referenceclasses = $referenceclasses;
        $this->indexfile = $indexfile;
        $this->databaseextension = $databaseextension;
        $this->readCache = $readCache;

        $this->workdirectory = $this->databasepath . '/' . $classname;

        if (!file_exists($this->workdirectory))
            mkdir($this->workdirectory, 755);
    }

    /**
     * Persist object and referenced objects
     * @param  var $myObj Object to save/update
     */
    public function persist($object) {
        $class = get_class($object);
        if ($class == $this->classname)
            $this->allToSave($object);
    }

    /**
     * Find object by primary key value for repository class
     * @param  var $id primary key value
     */
    public function findById($id) {
        if (in_array($this->classname, $this->classes)) {
            $savedir = $this->workdirectory;
            $savefile = $savedir . '/' . $id . $this->databaseextension;

            $resultobject = $this->readObject($savefile);

            $this->allToLoad($resultobject);

            return $resultobject;
        }
        return null;
    }

    private function allToLoad($myObj) {
        $class = get_class($myObj);
        $ref = new ReflectionClass($myObj);
        foreach (array_values($ref->getMethods()) as $method) {
            if ((0 === strpos($method->name, "get")) && $method->isPublic()) {
                $name = substr($method->name, 3);
                $name[0] = strtolower($name[0]);

                if (array_key_exists($class, $this->referenceclasses)) {
                    if (array_key_exists($name, $this->referenceclasses[$class])) {
                        $subclass = $this->referenceclasses[$class][$name];

                        $method = 'get' . ucfirst($name);
                        $methodobj = new ReflectionMethod($myObj, $method);
                        $subkey = $methodobj->invoke($myObj);

                        if ($subkey != null) {
                            $subRepository = new CKDBRepository($subclass, $this->databasepath, $this->compressionlevel, $this->classes, $this->classKeys, $this->foreignkeys, $this->primarykeys, $this->referenceclasses, $this->indexfile, $this->databaseextension, $this->readCache);
                            $value = $subRepository->findById($subkey);

                            $method = 'set' . ucfirst($name);
                            $methodobj = new ReflectionMethod($myObj, $method);
                            $methodobj->invoke($myObj, $value);
                        }
                    }
                }
            }
        }
    }

    private function allToSave($myObj) {
        $indexarray = array();
        $class = get_class($myObj);
        $ref = new ReflectionClass($myObj);
        foreach (array_values($ref->getMethods()) as $method) {
            if ((0 === strpos($method->name, "get")) && $method->isPublic()) {
                $name = substr($method->name, 3);
                $name[0] = strtolower($name[0]);

                $value = $method->invoke($myObj);

                if ("object" === gettype($value)) {
                    if (array_key_exists($class, $this->referenceclasses)) {
                        if (array_key_exists($name, $this->referenceclasses[$class])) {
                            $subclass = get_class($value);
                            if ($this->referenceclasses[$class][$name] == $subclass) {
                                $key = $this->findPrimaryKey($value);

                                $methodi = 'set' . ucfirst($name);
                                $methodobj = new ReflectionMethod($myObj, $methodi);
                                $methodobj->invoke($myObj, $key);

                                $this->allToSave($value);
                            }
                        }
                    }
                }

                $do_index = false;
                if (array_key_exists($class, $this->referenceclasses))
                    if (array_key_exists($name, $this->referenceclasses[$class]))
                        $do_index = true;
                if (array_key_exists($class, $this->foreignkeys))
                    if (in_array($name, $this->foreignkeys[$class]))
                        $do_index = true;
                if (array_key_exists($class, $this->primarykeys))
                    if ($this->primarykeys[$class] == $name)
                        $do_index = true;

                if ($do_index)
                    $indexarray[$name] = $method->invoke($myObj);
            }
        }
        $this->saveObject($myObj);
        $this->addToIndex($myObj, $indexarray);
    }

    private function addToIndex($myObj, $indexarray) {
        if (sizeof($indexarray) > 0) {
            $classname = get_class($myObj);
            $primkey = $this->findPrimaryKey($myObj);

            $savedir = $this->workdirectory;
            $indexstore = $savedir . '/' . $this->indexfile;

            if (!file_exists($indexstore)) {
                $newfilestart = "<?php\n\$index=array();\n";
                file_put_contents($indexstore, $newfilestart);
            }

            $indexline = "\$index['" . $primkey . "']=array(";
            foreach ($indexarray as $key => $value)
                $indexline .= "'" . $key . "' => '" . $value . "',";
            $indexline = rtrim($indexline, ",");
            $indexline .= ");\n";

            file_put_contents($indexstore, $indexline, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Recreate index file for repository class
     */
    public function reIndex() {
        $savedir = $this->workdirectory;
        $lastmodify = filemtime($savedir . '/' . $this->indexfile);

        $indexstore = $savedir . '/' . $this->indexfile . '.tmp';
        $newfilestart = "";
        $alllines = "";

        if (!file_exists($indexstore)) {
            $newfilestart = "<?php\n\$index=array();\n";
            file_put_contents($indexstore, $newfilestart);
        }

        $allElements = $this->findAllFlat();

        foreach ($allElements as $element) {
            set_time_limit(20);
            $indexarray = array();
            $ref = new ReflectionClass($element);
            foreach (array_values($ref->getMethods()) as $method) {
                if ((0 === strpos($method->name, "get")) && $method->isPublic()) {

                    $name = substr($method->name, 3);
                    $name[0] = strtolower($name[0]);

                    $value = $method->invoke($element);

                    // replace referenced objects (property) with their primary key.
                    if ("object" === gettype($value)) {
                        if (array_key_exists($this->classname, $this->referenceclasses)) {
                            if (array_key_exists($name, $this->referenceclasses[$this->classname])) {
                                $subclass = get_class($value);
                                if ($this->referenceclasses[$this->classname][$name] == $subclass) {
                                    $key = $this->findPrimaryKey($value);

                                    $methodi = 'set' . ucfirst($name);
                                    $methodobj = new ReflectionMethod($element, $methodi);
                                    $methodobj->invoke($element, $key);
                                }
                            }
                        }
                    }

                    // index
                    $do_index = false;
                    if (array_key_exists($this->classname, $this->referenceclasses))
                        if (array_key_exists($name, $this->referenceclasses[$this->classname]))
                            $do_index = true;
                    if (array_key_exists($this->classname, $this->foreignkeys))
                        if (in_array($name, $this->foreignkeys[$this->classname]))
                            $do_index = true;
                    if (array_key_exists($this->classname, $this->primarykeys))
                        if ($this->primarykeys[$this->classname] == $name)
                            $do_index = true;

                    if ($do_index)
                        $indexarray[$name] = $method->invoke($element);
                }
            }

            $primkey = $this->findPrimaryKey($element);

            $indexline = "\$index['" . $primkey . "']=array(";
            foreach ($indexarray as $key => $value)
                $indexline .= "'" . $key . "' => '" . $value . "',";
            $indexline = rtrim($indexline, ",");
            $indexline .= ");\n";

            $alllines .= $indexline;

            echo " ";
            ob_flush();
            flush();
        }

        file_put_contents($indexstore, $alllines, FILE_APPEND | LOCK_EX);

        //copy on long protocoll file, if no modifications in db
        if (filemtime($savedir . '/' . $this->indexfile) <= $lastmodify)
            rename($savedir . '/' . $this->indexfile . '.tmp', $savedir . '/' . $this->indexfile);
    }

    private function readObject($file) {
        if (array_key_exists($file, $this->readCache))
            return $this->readCache[$file];

        $serialized = file_get_contents($file);
        $object = unserialize($serialized);

        $this->readCache[$file] = $object;

        return $object;
    }

    private function saveObject($myObj) {
        $primkey = null;
        $class = get_class($myObj);

        if (array_key_exists($class, $this->primarykeys))
            $primkey = $this->primarykeys[$class];

        $savedir = $this->workdirectory;

        $method = 'get' . ucfirst($primkey);
        $methodobj = new ReflectionMethod($myObj, $method);
        $id = $methodobj->invoke($myObj, $method);

        $savefile = $savedir . '/' . $id . $this->databaseextension;

        $serialized = serialize($myObj);
        file_put_contents($savefile, $serialized);
    }

    /**
     * Delete object from storage
     * @param  var $myObj Object to delete
     */
    public function remove($object) {
        $class = get_class($object);

        if (in_array($class, $this->classes) && $class == $this->classname) {
            $id = $this->findPrimaryKey($object);

            $savedir = $this->workdirectory;
            $savefile = $savedir . '/' . $id;
            unlink($savefile);

            $indexstore = $savedir . '/' . $this->indexfile;
            $indexline = "unset(\$index['" . $primkey . "']);\n";
            file_put_contents($indexstore, $indexline, FILE_APPEND | LOCK_EX);
        }
    }

    private function findPrimaryKey($myObj) {
        $class = get_class($myObj);
        $primkey = null;
        if (array_key_exists($class, $this->primarykeys))
            $primkey = $this->primarykeys[$class];

        if ($primkey == null)
            return null;

        $method = 'get' . ucfirst($primkey);
        $methodobj = new ReflectionMethod($myObj, $method);
        $key = $methodobj->invoke($myObj);

        return $key;
    }

    /**
     * Find and return all objects of repository class
     */
    public function findAll() {
        if (in_array($this->classname, $this->classes)) {
            $resultid = array();
            $savedir = $this->workdirectory;
            $files = glob($savedir . '/*' . $this->databaseextension, GLOB_MARK);

            foreach ($files as $file) {
                $resultarray[] = $this->allToLoad($this->readObject($file));
            }

            return $resultarray;
        }
        return null;
    }

    private function findAllFlat() {
        if (in_array($this->classname, $this->classes)) {
            $resultarray = array();
            $savedir = $this->workdirectory;
            $files = glob($savedir . '/*' . $this->databaseextension, GLOB_MARK);

            foreach ($files as $file) {
                $resultarray[] = $this->readObject($file);
            }

            return $resultarray;
        }
        return null;
    }

    /**
     * Return Finder for using selection criteria
     */
    public function find() {
        include($this->workdirectory . '/' . $this->indexfile);
        return new CKDBFinder($index, $this);
    }

}

/**
 * CKDB Finder creating selection and sort criteria.
 * @package CKDB
 * @author  Christian Klisch
 * @since   0.1.0
 */
class CKDBFinder {

    protected $resultarray;
    protected $repository;
    protected $lastresults;
    protected $sortfield;

    public function __construct($lastresults, $repository) {
        $this->resultarray = array();
        $this->repository = $repository;
        $this->lastresults = $lastresults;
    }

    /**
     * Matching array $selection criteria. Array key is database field, array value is fieldvalue. All entries must be true.
     * @param  array $selection search criteria 
     */
    public function equals($selection) {
        foreach ($this->lastresults as $key => $value)
            if (count(array_intersect($selection, $value)) == count($selection))
                $this->resultarray[$key] = $value;

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching array $selection criteria. Array key is database field, array value is fieldvalue. All entries must be false.
     * @param  array $selection search criteria
     */
    public function notEquals($selection) {
        foreach ($this->lastresults as $key => $value)
            if (count(array_intersect($selection, $value)) == 0)
                $this->resultarray[$key] = $value;

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching array $selection criteria. Array key is database field, array value must be greater than fieldvalue. All entries must be true.
     * @param  array $selection search criteria
     */
    public function gt($selection) {
        $this->resultarray = $this->lastresults;

        foreach ($selection as $field => $val)
            $this->resultarray = array_filter($this->lastresults, function ($value) use ($field, $val) {
                        return ($value[$field] > $val);
                    });

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching array $selection criteria. Array key is database field, array value must be greater than or equals fieldvalue. All entries must be true.
     * @param  array $selection search criteria
     */
    public function gte($selection) {
        $this->resultarray = $this->lastresults;

        foreach ($selection as $field => $val)
            $this->resultarray = array_filter($this->lastresults, function ($value) use ($field, $val) {
                        return ($value[$field] >= $val);
                    });

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching array $selection criteria. Array key is database field, array value must be lower than fieldvalue. All entries must be true.
     * @param  array $selection search criteria
     */
    public function lt($selection) {
        $this->resultarray = $this->lastresults;

        foreach ($selection as $field => $val)
            $this->resultarray = array_filter($this->lastresults, function ($value) use ($field, $val) {
                        return ($value[$field] < $val);
                    });

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching array $selection criteria. Array key is database field, array value must be lower than or equals fieldvalue. All entries must be true.
     * @param  array $selection search criteria
     */
    public function lte($selection) {
        $this->resultarray = $this->lastresults;

        foreach ($selection as $field => $val)
            $this->resultarray = array_filter($this->lastresults, function ($value) use ($field, $val) {
                        return ($value[$field] <= $val);
                    });

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching all entries listed in $inArray. 
     * @param var $field matching field
     * @param array $inArray search criteria 
     */
    public function in($field, $inArray) {
        foreach ($this->lastresults as $key => $value)
            if (array_key_exists($value[$field], $inArray) || in_array($value[$field], $inArray))
                $this->resultarray[$key] = $value;

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Matching all entries not listed in $inArray. 
     * @param var $field matching field
     * @param array $inArray search criteria 
     */
    public function notIn($field, $inArray) {
        foreach ($this->lastresults as $key => $value)
            if (!array_key_exists($value[$field], $inArray) && !in_array($value[$field], $inArray))
                $this->resultarray[$key] = $value;

        return new CKDBFinder($this->resultarray, $this->repository);
    }

    /**
     * Sort by field ascending or descending
     * @param  var $sortfield field to sort for
     * @param  var $order SORT_ASC=ascending SORT_DESC=descending
     */
    public function sortBy($sortfield, $order = SORT_ASC) {
        $this->sortfield = $sortfield;

        if ($order == SORT_ASC)
            uasort($this->lastresults, function($a, $b) {
                        return $a[$this->sortfield] === $b[$this->sortfield] ? 0 : $a[$this->sortfield] > $b[$this->sortfield] ? 1 : -1;
                    });
        if ($order == SORT_DESC)
            uasort($this->lastresults, function($a, $b) {
                        return $a[$this->sortfield] === $b[$this->sortfield] ? 0 : $a[$this->sortfield] < $b[$this->sortfield] ? 1 : -1;
                    });

        return new CKDBFinder($this->lastresults, $this->repository);
    }

    /**
     * Return all found objects by Finder after select and sort.
     */
    public function getResult() {
        $result = array();
        foreach ($this->lastresults as $key => $value)
            $result[$key] = $this->repository->findById($key);
        return $result;
    }

}

?>