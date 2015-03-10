<?php
ini_set('display_errors', true);
require_once '../vendor/autoload.php';
error_reporting(2047);
$DB = \rollingWolf\QueryablePHP\QueryablePHP::open(array('dbName' => 'test.db', 'dbDir' => realpath('.')));
//$DB->insert('{"key":"keys can be Anything", "comment":"fields don\'t have to match"}');
//print_r($DB->find('{sex:{"$ne": "f"}}'));
//$DB->insert('[{president:"George Washington",took_office:1789},{president:"John Adams",took_office:1797},{president:"Thomas Jefferson",took_office:1801},{president:"James Madison",took_office:1809}]');
//$DB->insert('{president:"Bob Dylan", took_office:1965}');
//$DB->update('{comment:"/don\'t HAVE/i"}', '{$set: {something:"other"}}');
//$DB->update('{president:"/^J/"}', '{$set: {a:"b"}}', '{multi:true}');

print_r($DB->find());
echo $DB->getID();
//$DB->save();

