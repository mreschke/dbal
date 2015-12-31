# mreschke/dbal

- [Introduction](#introduction)
- [Collection Info](#collection)
- [Connection and Instantiation](#connection)
- [Entity based Integration](#entity-integration)
- [Adhoc Query Usage](#adhoc)


<a name="introduction"></a>
# Introduction
A Laravel based dbal helper library and query builder with easy class based
entity integration.

This was built before I used Laravel Query Builder or Eloquent and before
I built the far more robust and feature rich https://github.com/mreschke/repository

This still has a some advantages like:

* Mssql binary GUID conversions
* Cross/linked server support
* Easy and clean entity class integration (but NOT entity mapping)

but is mostly for legacy use now that I built `mreschke/repository`.  It was built
primarily for our MSSQL farm, but uses PDO and has MySQL support as well.

This dbal does have a "basic" query builder, but was also designed to throw large
RAW queries...much like you would in your SQL IDE.  So it can handle lots of
full and partial RAW SQL queries (most of which are NOT escaped, be careful).

If starting a new project just use Laravel Query Builder or Eloquent...or better
still, https://github.com/mreschke/repository ... seriously, use `mreschke/repository`
if you want anything advanced with a consistent and entity mapped API.
See [Entity based Integration](#entity-integration) below.



<a name="collection"></a>
# Collection Info

All multiple returns like `->get()`, `->all()`, `getArray()`, `getAssoc()`...will return
as a Laravel `Illuminate\Support\Collection`.  This means you can perform many
similar looking methods AFTER dbal has returned...don't confuse these with dbal methods.

Examples of collection usage

	$customers = $this->db->query("SELECT * FROM customers")->all();
	$customer->count(); //collection level count
	$customer->first(); //collection level first
	etc...

See https://laravel.com/api/master/Illuminate/Support/Collection.html for more



<a name="connection"></a>
# Connection and Instantiation

`Mreschke/dbal` is a Laravel library so it utilizes the existing database configuration
arrays found in Laravel `config/database.php`.  By default, if you use `Mreschke\Dbal\Mssql`
it will use the `sqlsrv` connection array and if you use `Mreschke\Dbal\Mysql` it will
use the `mysql` connection array.

To change the connection, much like Laravel query builder, use

	$this->db->connection('othercon')->query(...)->get()

`Mreschke/dbal` comes with 2 facades, `Mysql` and `Mssql`.  So you can simply use

	Mssql::query('SELECT * FROM customers')->get()

But of course the preferred method is through dependency injection
of `Mreschke\Dbal\Mssql` into your constructor.



<a name="entity-integration"></a>
# Entity based Integration

You can use `mreschke/dbal` for [Adhoc Query Usage](#adhoc).  Adhoc would
be synonymous with Laravel query builder (not eloquent).  Just use it on-the-fly whenever needed.

But an interesting integration comes about when you tie the `mreschke/dbal` query builder
with an individual class.  

Think of the class your "entity", like `Sso\User.php` for example.  And that
`User.php` class can of course have its own methods and properties, but it can also
be backed by mreschke/dbal tied to a database backend.  You can integrate
this class with `mreschke/dbal` by extending the Builder class to add
a fluent entity API like:

	$sso->user->find(45); // provided by dbal
	$sso->user->where('email', 'mail@example.com'); // provided by dbal
	$sso->user->byServer(1); // custom method in our User.php class, but it uses dbal behind the scenes

So this turns a basic PHP class or "entity" object into a fluent model.  Something like Eloquent does.

**NOTICE:** I firmly believe that an entity should be treated like an API and therefore
be consistent.  If you change the database column names, the return of the entity
should **not** change.  With this mreschke\dbal, the results are tied directly
to the database which is an issue for consistency.  This is why I created
https://github.com/mreschke/repository

`Mreschke/repository` is a much more advanced entity system than simply integrating
a database with an entity class.  `Mreschke/repository` adds active records, column and entity
mapping abstractions and repository style swappable backends.  It is highly recommended
to use `mreschke/repository` if you need this type of entity integration.

The prefered system is Laravel Query Builder (not eloquent) for adhoc queries and
`mreschke/repository` for entity management and APIs.


**This is how we would implement a SSO\User.php entity class backed by mreschke/dbal**

```php
<?php

use Mreschke\Dbal\Builder;
use Mreschke\Dbal\Mssql as Dbal;

class User extends Builder
{
	/**
	 * Database connection
	 * @var Dbal
	 */
	protected $db;

	/**
	 * Create a new User instance
	 * @param Dbal $db
	 */
	public function __construct(Dbal $db)
	{
		$this->db = $db;
		$this->db->connection('sso');
		$this->configureBuilder();
	}

	/**
	 * Configure the dbal query builder
	 * @return void
	 */
	public function configureBuilder()
	{
		$this->key = 'user_id';
		$this->select = ['tbl_user.*'];
		$this->from = 'tbl_user';
		$this->where = null;
		$this->groupBy = null;
		$this->having = null;
		$this->orderBy = 'email';
	}

	/**
	 * Return the dbal database instance
	 * @return DbalInterface
	 */
	public function dbInstance() {
		return $this->db;
	}

	/**
	 * Get one user by email address
	 * Automatically appends @dynatronsoftware.com if no domain specified
	 * @param  string $email
	 * @return dbal resource
	 */
	public function byEmail($email)
	{
		if (!str_contains($email, "@")) $email .= "@dynatronsoftware.com";
		$this->where("email = '$email'");
		$this->orderBy(null);
		return $this->execute();
	}

	/**
	 * Get dealer server manager(s)
	 * @param  int $dealerID dp dlr id
	 * @return dbal resource
	 */
	public function serviceManagers($dealerID)
	{
		$this->from('tbl_user
			INNER JOIN tbl_perm_group_link pgl on tbl_user.user_id = pgl.user_id
			INNER JOIN tbl_user_dealer_link udl on tbl_user.user_id = udl.user_id
		');
		$this->where('tbl_user.disabled', false);
		$this->where('udl.dp_dlr_id', $dealerID);
		$this->where('pgl.group_id', 10); #10 = Service Manager
		$this->distinct();
		return $this->execute();
	}
}
```

Because this class extends `Builder`, you get all the `->find()`, `->where()`, `->select()`,
`->orderBy`...methods plus the ability to add your own that utilize the builder..
like the `byEmail()` or `serviceManager()` methods above



<a name="adhoc"></a>
# Adhoc Query Usage

`Mreschke/dbal` also works great for adhoc queries.

```php
<?php

use Mreschke\Dbal\Mssql;

function __construct(Mssql $db)
{
	// Dependency injection.  Facades are also available.
	$this->db = $db;
}

function rawQueries()
{
	// Get all as collection of objects
	$customers = $this->db->query("SELECT * FROM customers")->get(); // or all()

	// Get all as collection of arrays
	$customers = $this->db->query("SELECT * FROM customers")->getArray(); // or getAssoc()

	// Get all as key/value array
	$customers = $this->db->query("SELECT * FROM customers")->lists('name', 'id');

	// Get first record as object
	$customers = $this->db->query("SELECT * FROM customers")->first();

	// Get first record as array
	$customers = $this->db->query("SELECT * FROM customers")->firstArray(); // or firstAssoc()

	// Get first column from first record (great for scalar queries)
	$customers = $this->db->query("SELECT TOP 1 name FROM customers")->pluck();

	// Get defined column from first record
	$customers = $this->db->query("SELECT TOP 1 * FROM customers")->pluck('adddress');

	// Count number of results
	// NOTICE: This will actually RUN the full query, so inefficient...
	// A SELECT count(*) is far more efficient.
	// So if you want the results too, get results, then count them yourself
	$count = $this->db->query("SELECT * FROM customers")->count(); // runs full query
	$count = $this->db->query("SELECT count(*) FROM customers")->pluck(); // db level, very efficient
	$customers = $this->db->query("SELECT * FROM customers")->get();
	count($customers) //or because collection, $customers->count();

	// Count number of columns
	$columnCount = $this->db->query("SELECT * FROM customers")->fieldCount();

	// Escape data for raw input
	$input = $this-db->escape($input);
	$this->db->query("INSERT INTO customers $input");
}

function queryBuilder()
{
	// Query build table does NOT work with databsae or schema names like DB.dbo.my_table
	// Much like the raw ->query() function above, the terminators are ->get,
	// ->all(), ->getAssoc(), ->getArray(), ->first()...
	// Most query builder methods allow RAW entries too, like ->where('raw = this or raw = that')...

	// TABLE and SELECT
		// Build can do basic ->table(), ->select() and ->addSelect().  If you want
		// complex joins, use a full RAW ->query() or add RAW to the ->table() method.
		// By default select is set to *

		// Get all records
		$customers = $this->db->table('customer')->get(); // or all()

		// Get all records, limited columns
		$customers = $this->db->select('id', 'name')->table('customer')->get();

		// Select as RAW, either as one parameter per column, or as one big string
		$customers = $this->db->select('id as UID', 'name as Customer')->table('customer')->get();
		$customers = $this->db->select('id as UID, name as Customer')->table('customer')->get();

		// Complex Raw table and select, but still using build style, not pure ->query()
		$customers = $this->db->select('c.*, r.name as Role')
			->table('customer c INNER JOIN roles r on c.role_id = r.role_id');
		$customers->addSelect('r.ID');
		$customers->distinct();
		$customers = $customers->get();


	// WHERE, ORDER GROUP, HAVING
		// By default, the ->where() method has 2 params, and the = opreator is assumed
		// But like eloquent, you can override the operator ->where('name', 'like', 'bob')...
		// Chaining ->where() is by default AND...but you can alter to OR...but it won't
		// do compled nested AND/OR combinations...for that I just use RAW queries.

		// Get one with WHERE statement
		$customers = $this->db->table('customer')->where('name', 'Bob')->first();

		// Multiple wheres (AND)
		$customers = $this->db->table('customer')->where('zip', 75067)->where('disabled', false)->first();

		// Multiple wheres (OR)
		$customers = $this->db->table('customer')->where('zip', '=', 75067, 'or')->where('zip', 75068)->get();
		$customers = $this->db->table('customer')->where('zip', '=', 75067)->orWhere('zip', 75068)->get();

		// Mixed in RAW where
		$customers = $this->db->table('customer')->where('(zip = 1 or zip = 2)')->where('disabled', false)->get();

		// Mixed RAW complex
		$customers = $this->db
			->table('customer')
			->select('name', 'count(*) as cnt')
			->where('(x = y AND a = b) OR (c = d)')
			->groupBy('name')
			->orderBy('cnt desc')
			->having('cnt > 1')

}

function procedures()
{
	// No params
	$customers = $this->db->procedure('GetAllCustomers')->get()

	// Params
	$customers = $this->db->procedure('GetCustomersByState', [
		['name' => 'state', 'value' => 'TX'],
		['name' => 'zip', 'value' => 75067],
	]);

	// No return
	$this->db->procedure('DeleteAllCustomers');
}
```
