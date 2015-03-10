QueryablePHP
================================================

# PHP port of [Queryable](https://github.com/gmn/queryable)

This is a PHP port of QUeryable a tiny NoSQL-like database that allows
structured querying of an array of objects. It stores as a JSON string.

## Examples

###

```php
$config = array(
    'dbPath' => 'db.db',
    'dbName' => 'test'
);
$DB = \rollingWolf\QueryablePHP\QueryablePHP::open($config);
$DB->insert('[{president:"George Washington",took_office:1789},{president:"John Adams",took_office:1797},{president:"Thomas Jefferson",took_office:1801},{president:"James Madison",took_office:1809}]'');
$DB->save();
```
