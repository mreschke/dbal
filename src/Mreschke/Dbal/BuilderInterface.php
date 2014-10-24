<?php namespace Mreschke\Dbal;

/**
 * Provides a contractual interface for query builder implementations
 * @copyright 2014 Matthew Reschke
 * @license http://mreschke.com/license/mit
 * @author Matthew Reschke <mail@mreschke.com>
 */
interface BuilderInterface
{

	/**
	 * Configure the dbal query builder
	 * @return void
	 */
	public function configureBuilder();

	/**
	 * Return the dbal database instance
	 * @return DbalInterface
	 */
	public function dbInstance();

	/**
	 * Build and return the query
	 * @return string
	 */
	public function queryBuilder();

	/**
	 * Alias to queryBuilder
	 * @return string
	 */
	public function toSql();
	
	/**
	 * Set a new select statement
	 * @param  string $sql select statement
	 * @return self chainable
	 */
	public function select($sql);

	/**
	 * Add a new column to the select query
	 * @param [type] $column [description]
	 */
	public function addSelect($column);

	/**
	 * Set a new from statement
	 * @param  string $sql from statement
	 * @return self chainable
	 */
	public function from($sql);

	/**
	 * Alias to from
	 * @param  string $sql from statement
	 * @return self chainable
	 */
	public function table($sql);

	/**
	 * Set a new primary key
	 * @param  mixed $sql from statement
	 * @return self chainable
	 */
	public function key($sql);

	/**
	 * Add a where clause to the query
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @return self chainable
	 */
	public function where($column, $operator = null, $value = null, $boolean = 'and');

	/**
	 * Add an or where clause to the query
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @return selc chainable
	 */
	public function orWhere($column, $operator = null, $value = null);

	/**
	 * Add a new group by statement
	 * @param  string $sql group by statement
	 * @return self chainable
	 */
	public function groupBy($sql);

	/**
	 * Set a new having statement
	 * @param  string $sql having statement
	 * @return self chainable
	 */
	public function having($sql);

	/**
	 * Set a new order by statement
	 * @param  string $sql order bystatement
	 * @return self chainable
	 */
	public function orderBy($sql);

	/**
	 * Alias to execute
	 * @return dbal resource
	 */
	public function query();

	/**
	 * Execute query builder
	 * @param  string $query option if not set use query builder
	 * @return dbal resource
	 */
	public function execute($query = null);

	/**
	 * Alias to execute
	 * @return dbal resource
	 */
	public function all();

	/**
	 * Return one row by one or more primary keys
	 * @param  mixed $id
	 * @return dbal resource
	 */
	public function find($id);

	/**
	 * Insert new record.
	 * @param  object $record
	 * @return mixed new primary key inserted
	 */
	public function insert($record);

	/**
	 * Update one record by one or more primary keys
	 * @param  object $record
	 * @return void
	 */
	public function update($record);

	/**
	 * Delete one record by one or more primary keys
	 * @param  mixed $id
	 * @return void
	 */
	public function delete($id);

}
