Some examples of using queryable.js in node.js:

var queryable = require( 'queryable' );

// There are multiple ways to open a db.
// The simplest is to provide a full path to where you want your database file.
var db = queryable.open( "~/code/mydata.db" );

// ..or you can simply name it, which will start an empty db with this name:
var db = queryable.open( "Database_Name" );

// You can load from a json object that is an Array of Objects.
var db = queryable.open( {db_name:"name",data: [{key:val,key2:val2},{key:val},...] } );

// insert any type of key:value pairs you want
db.insert( {key:"keys can be Anything", comment:"fields don't have to match"} );

// Like collections, many independent db can be open at the same time
var db2 = queryable.open( "~/another.db" );

// It handles any value types
db2.insert( {subarray:[1,2,'buckle',{'my':'shoe'}]} );

// find() works like Mongo; RegExp's are fine
var res = db.find( {key:/regex/} );

// SELECT * WHERE (age = 40);
var res = db.find( {age:40} );

// all rows where age is over 40
// supports: $gt, $lt, $gte, $lte, $ne, $exists
var res = db.find( {age: {'$gt':40}} );

// the first 10 rows where age is over 40 and 'name' exists, sorted by name
var res = db.find({age:{$gt:40},name:{'$exists':true}}).sort({name:1}).limit(10);

// find() returns db_result, which has a length property and rows[] array
// as well as chainable methods like: .sort(), .limit(), .skip(), ..
console.log( 'got ' + res.length + ' rows' );


/*
 * a real example - populate from string
 */
// literal data can be a string or an object
var json_string = '[
  {"name":"Cathy"},
  {"name":"Carol","sex":"f"},
  {"name":"John","sex":"m"},
  {"name":"Cornelius","sex":"m"}]';

var queryable = require('queryable');

var db = queryable.open({db_name:"MyDatabase",data:json_string});

// delete a row
db.remove({name:'Cathy'});

// get names that start with 'C'
db.find({name:/^C/}, function(res) {
  console.log( db.db_name + ' contains these names that start with C:' );
  res.rows.forEach(function(x){
    console.log(' ' + x.name);
  });
});

/* outputs:
MyDatabase contains these names that start with C:
 Carol
 Cornelius
*/
