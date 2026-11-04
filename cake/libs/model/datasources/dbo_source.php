<?php
/**
 * Dbo Source
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       cake
 * @subpackage    cake.cake.libs.model.datasources
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::import('Core', ['Set', 'CakeText']);

/**
 * DboSource
 *
 * Creates DBO-descendant objects from a given db connection configuration
 *
 * @package       cake
 * @subpackage    cake.cake.libs.model.datasources
 */
class DboSource extends DataSource {

/**
 * Description string for this Database Data Source.
 *
 * @var string
 * @access public
 */
	public $description = "Database Data Source";

/**
 * index definition, standard cake, primary, index, unique
 *
 * @var array
 */
	public $index = ['PRI' => 'primary', 'MUL' => 'index', 'UNI' => 'unique'];

/**
 * Database keyword used to assign aliases to identifiers.
 *
 * @var string
 * @access public
 */
	public $alias = 'AS ';

/**
 * Caches result from query parsing operations.  Cached results for both DboSource::name() and
 * DboSource::conditions() will be stored here.  Method caching uses `md5()`. If you have
 * problems with collisions, set DboSource::$cacheMethods to false.
 *
 * @var array
 * @access public
 */
	public $methodCache = [];

/**
 * Whether or not to cache the results of DboSource::name() and DboSource::conditions()
 * into the memory cache.  Set to false to disable the use of the memory cache.
 *
 * @var boolean.
 * @access public
 */
	public $cacheMethods = true ;

/**
 * Bypass automatic adding of joined fields/associations.
 *
 * @var boolean
 * @access private
 */
	public $__bypass = false;

/**
 * The set of valid SQL operations usable in a WHERE statement
 *
 * @var array
 * @access private
 */
	public $__sqlOps = ['like', 'ilike', 'or', 'not', 'in', 'between', 'regexp', 'similar to'];

/**
 * Index of basic SQL commands
 *
 * @var array
 * @access protected
 */
	public $_commands = [
		'begin' => 'BEGIN',
		'commit' => 'COMMIT',
		'rollback' => 'ROLLBACK'
	];

/**
 * Separator string for virtualField composition
 *
 * @var string
 */
	public $virtualFieldSeparator = '__';

/**
 * List of table engine specific parameters used on table creating
 *
 * @var array
 * @access public
 */
	public $tableParameters = [];

/**
 * List of engine specific additional field parameters used on table creating
 *
 * @var array
 * @access public
 */
	public $fieldParameters = [];

/**
 * Constructor
 *
 * @param array $config Array of configuration information for the Datasource.
 * @param boolean $autoConnect Whether or not the datasource should automatically connect.
 * @access public
 */
	public function __construct($config = null, $autoConnect = true) {
		$config['prefix'] ??= '';
		parent::__construct($config);
		$this->fullDebug = Configure::read() > 1;
		if (!$this->enabled()) {
			trigger_error(sprintf(__('%s - Selected driver is not enabled', true), static::class), E_USER_ERROR);
			return false;
		}
		if ($autoConnect) {
			return $this->connect();
		} else {
			return true;
		}
	}

/**
 * Reconnects to database server with optional new settings
 *
 * @param array $config An array defining the new configuration settings
 * @return boolean True on success, false on failure
 * @access public
 */
	public function reconnect($config = []) {
		$this->disconnect();
		$this->setConfig($config);
		$this->_sources = null;

		return $this->connect();
	}

/**
 * Prepares a value, or an array of values for database queries by quoting and escaping them.
 *
 * @param mixed $data A value or an array of values to prepare.
 * @param string $column The column into which this data will be inserted
 * @param boolean $read Value to be used in READ or WRITE context
 * @return mixed Prepared value or array of values.
 * @access public
 */
	public function value($data, $column = null, $read = true) {
		if (is_array($data) && !empty($data)) {
			return array_map(
				[&$this, 'value'],
				$data, array_fill(0, count($data), $column), array_fill(0, count($data), $read)
			);
		} elseif (is_object($data) && isset($data->type)) {
			if ($data->type == 'identifier') {
				return $this->name($data->value);
			} elseif ($data->type == 'expression') {
				return $data->value;
			}
		} elseif (in_array($data, ['{$__cakeID__$}', '{$__cakeForeignKey__$}'], true)) {
			return $data;
		} else {
			return null;
		}
	}

/**
 * Returns an object to represent a database identifier in a query
 *
 * @param string $identifier
 * @return object An object representing a database identifier to be used in a query
 * @access public
 */
	public function identifier($identifier) {
		$obj = new stdClass();
		$obj->type = 'identifier';
		$obj->value = $identifier;
		return $obj;
	}

/**
 * Returns an object to represent a database expression in a query
 *
 * @param string $expression
 * @return object An object representing a database expression to be used in a query
 * @access public
 */
	public function expression($expression) {
		$obj = new stdClass();
		$obj->type = 'expression';
		$obj->value = $expression;
		return $obj;
	}

/**
 * Executes given SQL statement.
 *
 * @param string $sql SQL statement
 * @return boolean
 * @access public
 */
	public function rawQuery($sql) {
		$this->took = $this->error = $this->numRows = false;
		return $this->execute($sql);
	}

/**
 * Queries the database with given SQL statement, and obtains some metadata about the result
 * (rows affected, timing, any errors, number of rows in resultset). The query is also logged.
 * If Configure::read('debug') is set, the log is shown all the time, else it is only shown on errors.
 *
 * ### Options
 *
 * - stats - Collect meta data stats for this query. Stats include time take, rows affected,
 *   any errors, and number of rows returned. Defaults to `true`.
 * - log - Whether or not the query should be logged to the memory log.
 *
 * @param string $sql
 * @param array $options
 * @return mixed Resource or object representing the result set, or false on failure
 * @access public
 */
	public function execute($sql, $options = []) {
		$defaults = ['stats' => true, 'log' => $this->fullDebug];
		$options = array_merge($defaults, $options);

		$t = getMicrotime();
		$this->_result = $this->_execute($sql);
		if ($options['stats']) {
			$this->took = round((getMicrotime() - $t) * 1000, 0);
			$this->affected = $this->lastAffected();
			$this->error = $this->lastError();
			$this->numRows = $this->lastNumRows();
		}

		if ($options['log']) {
			$this->logQuery($sql);
		}

		if ($this->error) {
			$this->showQuery($sql);
			return false;
		}
		return $this->_result;
	}

/**
 * DataSource Query abstraction
 *
 * @return resource Result resource identifier.
 * @access public
 */
	public function query() {
		$args	  = func_get_args();
		$fields	  = null;
		$order	  = null;
		$limit	  = null;
		$page	  = null;
		$recursive = null;

		if (count($args) == 1) {
			return $this->fetchAll($args[0]);

		} elseif (count($args) > 1 && (str_starts_with(strtolower($args[0]), 'findby') || str_starts_with(strtolower($args[0]), 'findallby'))) {
			$params = $args[1];

			if (str_starts_with(strtolower($args[0]), 'findby')) {
				$all  = false;
				$field = Inflector::underscore(preg_replace('/^findBy/i', '', $args[0]));
			} else {
				$all  = true;
				$field = Inflector::underscore(preg_replace('/^findAllBy/i', '', $args[0]));
			}

			$or = (str_contains($field, '_or_'));
			if ($or) {
				$field = explode('_or_', $field);
			} else {
				$field = explode('_and_', $field);
			}
			$off = count($field) - 1;

			if (isset($params[1 + $off])) {
				$fields = $params[1 + $off];
			}

			if (isset($params[2 + $off])) {
				$order = $params[2 + $off];
			}

			if (!array_key_exists(0, $params)) {
				return false;
			}

			$c = 0;
			$conditions = [];

			foreach ($field as $f) {
				$conditions[$args[2]->alias . '.' . $f] = $params[$c];
				$c++;
			}

			if ($or) {
				$conditions = ['OR' => $conditions];
			}

			if ($all) {
				if (isset($params[3 + $off])) {
					$limit = $params[3 + $off];
				}

				if (isset($params[4 + $off])) {
					$page = $params[4 + $off];
				}

				if (isset($params[5 + $off])) {
					$recursive = $params[5 + $off];
				}
				return $args[2]->find('all', compact('conditions', 'fields', 'order', 'limit', 'page', 'recursive'));
			} else {
				if (isset($params[3 + $off])) {
					$recursive = $params[3 + $off];
				}
				return $args[2]->find('first', compact('conditions', 'fields', 'order', 'recursive'));
			}
		} else {
			if (isset($args[1]) && $args[1] === true) {
				return $this->fetchAll($args[0], true);
			} else if (isset($args[1]) && !is_array($args[1]) ) {
				return $this->fetchAll($args[0], false);
			} else if (isset($args[1]) && is_array($args[1])) {
				$offset = 0;
				if (isset($args[2])) {
					$cache = $args[2];
				} else {
					$cache = true;
				}
				$args[1] = array_map([&$this, 'value'], $args[1]);
				return $this->fetchAll(CakeText::insert($args[0], $args[1]), $cache);
			}
		}
	}

/**
 * Returns a row from current resultset as an array
 *
 * @return array The fetched row as an array
 * @access public
 */
	public function fetchRow($sql = null) {
		if (!empty($sql) && is_string($sql) && strlen($sql) > 5) {
			if (!$this->execute($sql)) {
				return null;
			}
		}

		if ($this->hasResult()) {
			$this->resultSet($this->_result);
			$resultRow = $this->fetchResult();
			if (!empty($resultRow)) {
				$this->fetchVirtualField($resultRow);
			}
			return $resultRow;
		} else {
			return null;
		}
	}

/**
 * Returns an array of all result rows for a given SQL query.
 * Returns false if no rows matched.
 *
 * @param string $sql SQL statement
 * @param boolean $cache Enables returning/storing cached query results
 * @return array Array of resultset rows, or false if no rows matched
 * @access public
 */
	public function fetchAll($sql, $cache = true, $modelName = null) {
		if ($cache && isset($this->_queryCache[$sql])) {
			if (preg_match('/^\s*select/i', $sql)) {
				return $this->_queryCache[$sql];
			}
		}

		if ($this->execute($sql)) {
			$out = [];

			$first = $this->fetchRow();
			if ($first != null) {
				$out[] = $first;
			}
			while ($this->hasResult() && $item = $this->fetchResult()) {
				$this->fetchVirtualField($item);
				$out[] = $item;
			}

			if ($cache) {
				if (preg_match('/^\s*select/i', $sql)) {
					$this->_queryCache[$sql] = $out;
				}
			}
			if (empty($out) && is_bool($this->_result)) {
				return $this->_result;
			}
			return $out;
		} else {
			return false;
		}
	}

/**
 * Modifies $result array to place virtual fields in model entry where they belongs to
 *
 * @param array $resut REference to the fetched row
 * @return void
 */
	public function fetchVirtualField(&$result) {
		if (isset($result[0]) && is_array($result[0])) {
			foreach ($result[0] as $field => $value) {
				if (!str_contains($field, $this->virtualFieldSeparator)) {
					continue;
				}
				[$alias, $virtual] = explode($this->virtualFieldSeparator, $field);

				if (!ClassRegistry::isKeySet($alias)) {
					return;
				}
				$model = ClassRegistry::getObject($alias);
				if ($model->isVirtualField($virtual)) {
					$result[$alias][$virtual] = $value;
					unset($result[0][$field]);
				}
			}
			if (empty($result[0])) {
				unset($result[0]);
			}
		}
	}

/**
 * Returns a single field of the first of query results for a given SQL query, or false if empty.
 *
 * @param string $name Name of the field
 * @param string $sql SQL query
 * @return mixed Value of field read.
 * @access public
 */
	public function field($name, $sql) {
		$data = $this->fetchRow($sql);
		if (!isset($data[$name]) || empty($data[$name])) {
			return false;
		} else {
			return $data[$name];
		}
	}

/**
 * Empties the method caches.
 * These caches are used by DboSource::name() and DboSource::conditions()
 *
 * @return void
 */
	public function flushMethodCache() {
		$this->methodCache = [];
	}

/**
 * Cache a value into the methodCaches.  Will respect the value of DboSource::$cacheMethods.
 * Will retrieve a value from the cache if $value is null.
 *
 * If caching is disabled and a write is attempted, the $value will be returned.
 * A read will either return the value or null.
 *
 * @param string $method Name of the method being cached.
 * @param string $key The keyname for the cache operation.
 * @param mixed $value The value to cache into memory.
 * @return mixed Either null on failure, or the value if its set.
 */
	public function cacheMethod($method, $key, $value = null) {
		if ($this->cacheMethods === false) {
			return $value;
		}
		if ($value === null) {
			return $this->methodCache[$method][$key] ?? null;
		}
		return $this->methodCache[$method][$key] = $value;
	}

/**
 * Returns a quoted name of $data for use in an SQL statement.
 * Strips fields out of SQL functions before quoting.
 *
 * Results of this method are stored in a memory cache.  This improves performance, but
 * because the method uses a simple hashing algorithm it can infrequently have collisions.
 * Setting DboSource::$cacheMethods to false will disable the memory cache.
 *
 * @param mixed $data Either a string with a column to quote. An array of columns to quote or an
 *   object from DboSource::expression() or DboSource::identifier()
 * @return string SQL field
 * @access public
 */
	public function name($data) {
		if (is_object($data) && isset($data->type)) {
			return $data->value;
		}
		if ($data === '*') {
			return '*';
		}
		if (is_array($data)) {
			foreach ($data as $i => $dataItem) {
				$data[$i] = $this->name($dataItem);
			}
			return $data;
		}
		$cacheKey = md5($this->startQuote.$data.$this->endQuote);
		if ($return = $this->cacheMethod(__FUNCTION__, $cacheKey)) {
			return $return;
		}
		$data = trim($data);
		if (preg_match('/^[\w-]+(?:\.[^ \*]*)*$/', $data)) { // string, string.string
			if (!str_contains($data, '.')) { // string
				return $this->cacheMethod(__FUNCTION__, $cacheKey, $this->startQuote . $data . $this->endQuote);
			}
			$items = explode('.', $data);
			return $this->cacheMethod(__FUNCTION__, $cacheKey,
				$this->startQuote . implode($this->endQuote . '.' . $this->startQuote, $items) . $this->endQuote
			);
		}
		if (preg_match('/^[\w-]+\.\*$/', $data)) { // string.*
			return $this->cacheMethod(__FUNCTION__, $cacheKey,
				$this->startQuote . str_replace('.*', $this->endQuote . '.*', $data)
			);
		}
		if (preg_match('/^([\w-]+)\((.*)\)$/', $data, $matches)) { // Functions
			return $this->cacheMethod(__FUNCTION__, $cacheKey,
				 $matches[1] . '(' . $this->name($matches[2]) . ')'
			);
		}
		if (
			preg_match('/^([\w-]+(\.[\w-]+|\(.*\))*)\s+' . preg_quote($this->alias) . '\s*([\w-]+)$/i', $data, $matches
		)) {
			return $this->cacheMethod(
				__FUNCTION__, $cacheKey,
				preg_replace(
					'/\s{2,}/', ' ', $this->name($matches[1]) . ' ' . $this->alias . ' ' . $this->name($matches[3])
				)
			);
		}
		if (preg_match('/^[\w\-_\s]*[\w\-_]+/', $data)) {
			return $this->cacheMethod(__FUNCTION__, $cacheKey, $this->startQuote . $data . $this->endQuote);
		}
		return $this->cacheMethod(__FUNCTION__, $cacheKey, $data);
	}

/**
 * Checks if the source is connected to the database.
 *
 * @return boolean True if the database is connected, else false
 * @access public
 */
	public function isConnected() {
		return $this->connected;
	}

/**
 * Checks if the result is valid
 *
 * @return boolean True if the result is valid else false
 * @access public
 */
	public function hasResult() {
		return is_resource($this->_result);
	}

/**
 * Get the query log as an array.
 *
 * @param boolean $sorted Get the queries sorted by time taken, defaults to false.
 * @return array Array of queries run as an array
 * @access public
 */
	public function getLog($sorted = false, $clear = true) {
		if ($sorted) {
			$log = sortByKey($this->_queriesLog, 'took', 'desc', SORT_NUMERIC);
		} else {
			$log = $this->_queriesLog;
		}
		if ($clear) {
			$this->_queriesLog = [];
		}
		return ['log' => $log, 'count' => $this->_queriesCnt, 'time' => $this->_queriesTime];
	}

/**
 * Outputs the contents of the queries log. If in a non-CLI environment the sql_log element
 * will be rendered and output.  If in a CLI environment, a plain text log is generated.
 *
 * @param boolean $sorted Get the queries sorted by time taken, defaults to false.
 * @return void
 */
	public function showLog($sorted = false) {
		$log = $this->getLog($sorted, false);
		if (empty($log['log'])) {
			return;
		}
		if (PHP_SAPI != 'cli') {
			App::import('Core', 'View');
			$controller = null;
			$View = new View($controller, false);
			$View->set('logs', [$this->configKeyName => $log]);
			echo $View->element('sql_dump', ['_forced_from_dbo_' => true]);
		} else {
			foreach ($log['log'] as $k => $i) {
				print (($k + 1) . ". {$i['query']} {$i['error']}\n");
			}
		}
	}

/**
 * Log given SQL query.
 *
 * @param string $sql SQL statement
 * @todo: Add hook to log errors instead of returning false
 * @access public
 */
	public function logQuery($sql) {
		$this->_queriesCnt++;
		$this->_queriesTime += $this->took;
		$this->_queriesLog[] = [
			'query' => $sql,
			'error'		=> $this->error,
			'affected'	=> $this->affected,
			'numRows'	=> $this->numRows,
			'took'		=> $this->took
		];
		if (count($this->_queriesLog) > $this->_queriesLogMax) {
			array_pop($this->_queriesLog);
		}
		if ($this->error) {
			return false;
		}
	}

/**
 * Output information about an SQL query. The SQL statement, number of rows in resultset,
 * and execution time in microseconds. If the query fails, an error is output instead.
 *
 * @param string $sql Query to show information on.
 * @access public
 */
	public function showQuery($sql) {
		$error = $this->error;
		if (strlen($sql) > 200 && !$this->fullDebug && Configure::read() > 1) {
			$sql = substr($sql, 0, 200) . '[...]';
		}
		if (Configure::read() > 0) {
			$out = null;
			if ($error) {
				trigger_error('<span style="color:Red;text-align:left"><b>' . __('SQL Error:', true) . "</b> {$this->error}</span>", E_USER_WARNING);
			} else {
				$out = ('<small>[' . sprintf(__('Aff:%s Num:%s Took:%sms', true), $this->affected, $this->numRows, $this->took) . ']</small>');
			}
			pr(sprintf('<p style="text-align:left"><b>' . __('Query:', true) . '</b> %s %s</p>', $sql, $out));
		}
	}

/**
 * Gets full table name including prefix
 *
 * @param mixed $model Either a Model object or a string table name.
 * @param boolean $quote Whether you want the table name quoted.
 * @return string Full quoted table name
 * @access public
 */
	public function fullTableName($model, $quote = true) {
		if (is_object($model)) {
			$table = $model->tablePrefix . $model->table;
		} elseif (isset($this->config['prefix'])) {
			$table = $this->config['prefix'] . strval($model);
		} else {
			$table = strval($model);
		}
		if ($quote) {
			return $this->name($table);
		}
		return $table;
	}

/**
 * The "C" in CRUD
 *
 * Creates new records in the database.
 *
 * @param Model $model Model object that the record is for.
 * @param array $fields An array of field names to insert. If null, $model->data will be
 *   used to generate field names.
 * @param array $values An array of values with keys matching the fields. If null, $model->data will
 *   be used to generate values.
 * @return boolean Success
 * @access public
 */
	public function create(&$model, $fields = null, $values = null) {
		$id = null;

		if ($fields == null) {
			unset($fields, $values);
			$fields = array_keys($model->data);
			$values = array_values($model->data);
		}
		$count = count($fields);

		for ($i = 0; $i < $count; $i++) {
			$valueInsert[] = $this->value($values[$i], $model->getColumnType($fields[$i]), false);
			$fieldInsert[] = $this->name($fields[$i]);
			if ($fields[$i] == $model->primaryKey) {
				$id = $values[$i];
			}
		}
		$query = [
			'table' => $this->fullTableName($model),
			'fields' => implode(', ', $fieldInsert),
			'values' => implode(', ', $valueInsert)
		];

		if ($this->execute($this->renderStatement('create', $query))) {
			if (empty($id)) {
				$id = $this->lastInsertId($this->fullTableName($model, false), $model->primaryKey);
			}
			$model->setInsertID($id);
			$model->id = $id;
			return true;
		} else {
			$model->onError();
			return false;
		}
	}

/**
 * The "R" in CRUD
 *
 * Reads record(s) from the database.
 *
 * @param Model $model A Model object that the query is for.
 * @param array $queryData An array of queryData information containing keys similar to Model::find()
 * @param integer $recursive Number of levels of association
 * @return mixed boolean false on error/failure.  An array of results on success.
 */
	public function read(&$model, $queryData = [], $recursive = null) {
		$queryData = $this->__scrubQueryData($queryData);

		$null = null;
		$array = ['callbacks' => $queryData['callbacks']];
		$linkedModels = [];
		$this->__bypass = false;
		$this->__booleans = [];

		if ($recursive === null && isset($queryData['recursive'])) {
			$recursive = $queryData['recursive'];
		}

		if (!is_null($recursive)) {
			$_recursive = $model->recursive;
			$model->recursive = $recursive;
		}

		if (!empty($queryData['fields'])) {
			$this->__bypass = true;
			$queryData['fields'] = $this->fields($model, null, $queryData['fields']);
		} else {
			$queryData['fields'] = $this->fields($model);
		}

		$_associations = $model->__associations;

		if ($model->recursive == -1) {
			$_associations = [];
		} else if ($model->recursive == 0) {
			unset($_associations[2], $_associations[3]);
		}

		foreach ($_associations as $type) {
			foreach ($model->{$type} as $assoc => $assocData) {
				$linkModel =& $model->{$assoc};
				$external = isset($assocData['external']);

				if ($model->useDbConfig == $linkModel->useDbConfig) {
					if (true === $this->generateAssociationQuery($model, $linkModel, $type, $assoc, $assocData, $queryData, $external, $null)) {
						$linkedModels[$type . '/' . $assoc] = true;
					}
				}
			}
		}

		$query = $this->generateAssociationQuery($model, $null, null, null, null, $queryData, false, $null);

		$resultSet = $this->fetchAll($query, $model->cacheQueries, $model->alias);

		if ($resultSet === false) {
			$model->onError();
			return false;
		}

		$filtered = [];

		if ($queryData['callbacks'] === true || $queryData['callbacks'] === 'after') {
			$filtered = $this->__filterResults($resultSet, $model);
		}

		if ($model->recursive > -1) {
			foreach ($_associations as $type) {
				foreach ($model->{$type} as $assoc => $assocData) {
					$linkModel =& $model->{$assoc};

					if (empty($linkedModels[$type . '/' . $assoc])) {
						if ($model->useDbConfig == $linkModel->useDbConfig) {
							$db =& $this;
						} else {
							$db =& ConnectionManager::getDataSource($linkModel->useDbConfig);
						}
					} elseif ($model->recursive > 1 && ($type == 'belongsTo' || $type == 'hasOne')) {
						$db =& $this;
					}

					if (isset($db) && method_exists($db, 'queryAssociation')) {
						$stack = [$assoc];
						$db->queryAssociation($model, $linkModel, $type, $assoc, $assocData, $array, true, $resultSet, $model->recursive - 1, $stack);
						unset($db);

						if ($type === 'hasMany') {
							$filtered []= $assoc;
						}
					}
				}
			}
			if ($queryData['callbacks'] === true || $queryData['callbacks'] === 'after') {
				$this->__filterResults($resultSet, $model, $filtered);
			}
		}

		if (!is_null($recursive)) {
			$model->recursive = $_recursive;
		}
		return $resultSet;
	}

/**
 * Passes association results thru afterFind filters of corresponding model
 *
 * @param array $results Reference of resultset to be filtered
 * @param object $model Instance of model to operate against
 * @param array $filtered List of classes already filtered, to be skipped
 * @return array Array of results that have been filtered through $model->afterFind
 * @access private
 */
	public function __filterResults(&$results, &$model, $filtered = []) {
		$filtering = [];
		$count = count($results);

		for ($i = 0; $i < $count; $i++) {
			if (is_array($results[$i])) {
				$classNames = array_keys($results[$i]);
				$count2 = count($classNames);

				for ($j = 0; $j < $count2; $j++) {
					$className = $classNames[$j];
					if ($model->alias != $className && !in_array($className, $filtered)) {
						if (!in_array($className, $filtering)) {
							$filtering[] = $className;
						}

						if (isset($model->{$className}) && is_object($model->{$className})) {
							$data = $model->{$className}->afterFind([[$className => $results[$i][$className]]], false);
						}
						if (isset($data[0][$className])) {
							$results[$i][$className] = $data[0][$className];
						}
					}
				}
			}
		}
		return $filtering;
	}

/**
 * Queries associations.  Used to fetch results on recursive models.
 *
 * @param Model $model Primary Model object
 * @param Model $linkModel Linked model that
 * @param string $type Association type, one of the model association types ie. hasMany
 * @param unknown_type $association
 * @param unknown_type $assocData
 * @param array $queryData
 * @param boolean $external Whether or not the association query is on an external datasource.
 * @param array $resultSet Existing results
 * @param integer $recursive Number of levels of association
 * @param array $stack
 */
	public function queryAssociation(&$model, &$linkModel, $type, $association, $assocData, &$queryData, $external = false, &$resultSet = null, $recursive = null, $stack = null) {
		if ($query = $this->generateAssociationQuery($model, $linkModel, $type, $association, $assocData, $queryData, $external, $resultSet)) {
			if (!isset($resultSet) || !is_array($resultSet)) {
				if (Configure::read() > 0) {
					echo '<div style = "font: Verdana bold 12px; color: #FF0000">' . sprintf(__('SQL Error in model %s:', true), $model->alias) . ' ';
					if (isset($this->error) && $this->error != null) {
						echo $this->error;
					}
					echo '</div>';
				}
				return null;
			}
			$count = count($resultSet);

			if ($type === 'hasMany' && empty($assocData['limit']) && !empty($assocData['foreignKey'])) {
				$ins = $fetch = [];
				for ($i = 0; $i < $count; $i++) {
					if ($in = $this->insertQueryData('{$__cakeID__$}', $resultSet[$i], $association, $assocData, $model, $linkModel, $stack)) {
						$ins[] = $in;
					}
				}

				if (!empty($ins)) {
					$ins = array_unique($ins);
					$fetch = $this->fetchAssociated($model, $query, $ins);
				}

				if (!empty($fetch) && is_array($fetch)) {
					if ($recursive > 0) {
						foreach ($linkModel->__associations as $type1) {
							foreach ($linkModel->{$type1} as $assoc1 => $assocData1) {
								$deepModel =& $linkModel->{$assoc1};
								$tmpStack = $stack;
								$tmpStack[] = $assoc1;

								if ($linkModel->useDbConfig === $deepModel->useDbConfig) {
									$db =& $this;
								} else {
									$db =& ConnectionManager::getDataSource($deepModel->useDbConfig);
								}
								$db->queryAssociation($linkModel, $deepModel, $type1, $assoc1, $assocData1, $queryData, true, $fetch, $recursive - 1, $tmpStack);
							}
						}
					}
				}
				if ($queryData['callbacks'] === true || $queryData['callbacks'] === 'after') {
					$this->__filterResults($fetch, $model);
				}
				return $this->__mergeHasMany($resultSet, $fetch, $association, $model, $linkModel, $recursive);
			} elseif ($type === 'hasAndBelongsToMany') {
				$ins = $fetch = [];
				for ($i = 0; $i < $count; $i++) {
					if ($in = $this->insertQueryData('{$__cakeID__$}', $resultSet[$i], $association, $assocData, $model, $linkModel, $stack)) {
						$ins[] = $in;
					}
				}
				if (!empty($ins)) {
					$ins = array_unique($ins);
					if (count($ins) > 1) {
						$query = str_replace('{$__cakeID__$}', '(' .implode(', ', $ins) .')', $query);
						$query = str_replace('= (', 'IN (', $query);
					} else {
						$query = str_replace('{$__cakeID__$}',$ins[0], $query);
					}

					$query = str_replace(' WHERE 1 = 1', '', $query);
				}

				$foreignKey = $model->hasAndBelongsToMany[$association]['foreignKey'];
				$joinKeys = [$foreignKey, $model->hasAndBelongsToMany[$association]['associationForeignKey']];
				[$with, $habtmFields] = $model->joinModel($model->hasAndBelongsToMany[$association]['with'], $joinKeys);
				$habtmFieldsCount = count($habtmFields);
				$q = $this->insertQueryData($query, null, $association, $assocData, $model, $linkModel, $stack);

				if ($q != false) {
					$fetch = $this->fetchAll($q, $model->cacheQueries, $model->alias);
				} else {
					$fetch = null;
				}
			}

			for ($i = 0; $i < $count; $i++) {
				$row =& $resultSet[$i];

				if ($type !== 'hasAndBelongsToMany') {
					$q = $this->insertQueryData($query, $resultSet[$i], $association, $assocData, $model, $linkModel, $stack);
					if ($q != false) {
						$fetch = $this->fetchAll($q, $model->cacheQueries, $model->alias);
					} else {
						$fetch = null;
					}
				}
				$selfJoin = false;

				if ($linkModel->name === $model->name) {
					$selfJoin = true;
				}

				if (!empty($fetch) && is_array($fetch)) {
					if ($recursive > 0) {
						foreach ($linkModel->__associations as $type1) {
							foreach ($linkModel->{$type1} as $assoc1 => $assocData1) {
								$deepModel =& $linkModel->{$assoc1};

								if (($type1 === 'belongsTo') || ($deepModel->alias === $model->alias && $type === 'belongsTo') || ($deepModel->alias != $model->alias)) {
									$tmpStack = $stack;
									$tmpStack[] = $assoc1;
									if ($linkModel->useDbConfig == $deepModel->useDbConfig) {
										$db =& $this;
									} else {
										$db =& ConnectionManager::getDataSource($deepModel->useDbConfig);
									}
									$db->queryAssociation($linkModel, $deepModel, $type1, $assoc1, $assocData1, $queryData, true, $fetch, $recursive - 1, $tmpStack);
								}
							}
						}
					}
					if ($type == 'hasAndBelongsToMany') {
						$uniqueIds = $merge = [];

						foreach ($fetch as $j => $data) {
							if (
								(isset($data[$with]) && $data[$with][$foreignKey] === $row[$model->alias][$model->primaryKey])
							) {
								if ($habtmFieldsCount <= 2) {
									unset($data[$with]);
								}
								$merge[] = $data;
							}
						}
						if (empty($merge) && !isset($row[$association])) {
							$row[$association] = $merge;
						} else {
							$this->__mergeAssociation($resultSet[$i], $merge, $association, $type);
						}
					} else {
						$this->__mergeAssociation($resultSet[$i], $fetch, $association, $type, $selfJoin);
					}
					if (isset($resultSet[$i][$association])) {
						$resultSet[$i][$association] = $linkModel->afterFind($resultSet[$i][$association], false);
					}
				} else {
					$tempArray[0][$association] = false;
					$this->__mergeAssociation($resultSet[$i], $tempArray, $association, $type, $selfJoin);
				}
			}
		}
	}

/**
 * A more efficient way to fetch associations.	Woohoo!
 *
 * @param model $model Primary model object
 * @param string $query Association query
 * @param array $ids Array of IDs of associated records
 * @return array Association results
 * @access public
 */
	public function fetchAssociated($model, $query, $ids) {
		$query = str_replace('{$__cakeID__$}', implode(', ', $ids), $query);
		if (count($ids) > 1) {
			$query = str_replace('= (', 'IN (', $query);
		}
		return $this->fetchAll($query, $model->cacheQueries, $model->alias);
	}

/**
 * mergeHasMany - Merge the results of hasMany relations.
 *
 *
 * @param array $resultSet Data to merge into
 * @param array $merge Data to merge
 * @param string $association Name of Model being Merged
 * @param object $model Model being merged onto
 * @param object $linkModel Model being merged
 * @return void
 */
	public function __mergeHasMany(&$resultSet, $merge, $association, &$model, &$linkModel) {
		foreach ($resultSet as $i => $value) {
			$count = 0;
			$merged[$association] = [];
			foreach ($merge as $j => $data) {
				if (isset($value[$model->alias]) && $value[$model->alias][$model->primaryKey] === $data[$association][$model->hasMany[$association]['foreignKey']]) {
					if (count($data) > 1) {
						$data = array_merge($data[$association], $data);
						unset($data[$association]);
						foreach ($data as $key => $name) {
							if (is_numeric($key)) {
								$data[$association][] = $name;
								unset($data[$key]);
							}
						}
						$merged[$association][] = $data;
					} else {
						$merged[$association][] = $data[$association];
					}
				}
				$count++;
			}
			if (isset($value[$model->alias])) {
				$resultSet[$i] = Set::pushDiff($resultSet[$i], $merged);
				unset($merged);
			}
		}
	}

/**
 * Enter description here...
 *
 * @param unknown_type $data
 * @param unknown_type $merge
 * @param unknown_type $association
 * @param unknown_type $type
 * @param boolean $selfJoin
 * @access private
 */
	public function __mergeAssociation(&$data, $merge, $association, $type, $selfJoin = false) {
		if (isset($merge[0]) && !isset($merge[0][$association])) {
			$association = Inflector::pluralize($association);
		}

		if ($type == 'belongsTo' || $type == 'hasOne') {
			if (isset($merge[$association])) {
				$data[$association] = $merge[$association][0];
			} else {
				if (is_array($merge[0][$association]) && count($merge[0][$association]) > 1) {
					foreach ($merge[0] as $assoc => $data2) {
						if ($assoc != $association) {
							$merge[0][$association][$assoc] = $data2;
						}
					}
				}
				if (!isset($data[$association])) {
					if ($merge[0][$association] != null) {
						$data[$association] = $merge[0][$association];
					} else {
						$data[$association] = [];
					}
				} else {
					if (is_array($merge[0][$association])) {
						foreach ($data[$association] as $k => $v) {
							if (!is_array($v)) {
								$dataAssocTmp[$k] = $v;
							}
						}

						foreach ($merge[0][$association] as $k => $v) {
							if (!is_array($v)) {
								$mergeAssocTmp[$k] = $v;
							}
						}
						$dataKeys = array_keys($data);
						$mergeKeys = array_keys($merge[0]);

						if ($mergeKeys[0] === $dataKeys[0] || $mergeKeys === $dataKeys) {
							$data[$association][$association] = $merge[0][$association];
						} else {
							$diff = Set::diff($dataAssocTmp, $mergeAssocTmp);
							$data[$association] = array_merge($merge[0][$association], $diff);
						}
					} elseif ($selfJoin && array_key_exists($association, $merge[0])) {
						$data[$association] = array_merge($data[$association], [$association => []]);
					}
				}
			}
		} else {
			if (isset($merge[0][$association]) && $merge[0][$association] === false) {
				$data[$association] ??= [];
			} else {
				foreach ($merge as $i => $row) {
					$insert = [];
					if (count($row) === 1) {
						$insert = $row[$association];
					} elseif (isset($row[$association])) {
						$insert = array_merge($row[$association], $row);
						unset($insert[$association]);
					}

					if (empty($data[$association]) || (isset($data[$association]) && !in_array($insert, $data[$association], true))) {
						$data[$association][] = $insert;
					}
				}
			}
		}
	}

/**
 * Generates an array representing a query or part of a query from a single model or two associated models
 *
 * @param Model $model
 * @param Model $linkModel
 * @param string $type
 * @param string $association
 * @param array $assocData
 * @param array $queryData
 * @param boolean $external
 * @param array $resultSet
 * @return mixed
 * @access public
 */
	public function generateAssociationQuery(&$model, &$linkModel, $type, $association = null, $assocData = [], &$queryData = null, $external = false, &$resultSet = null) {
		$queryData = $this->__scrubQueryData($queryData);
		$assocData = $this->__scrubQueryData($assocData);

		if (empty($queryData['fields'])) {
			$queryData['fields'] = $this->fields($model, $model->alias);
		} elseif (!empty($model->hasMany) && $model->recursive > -1) {
			$assocFields = $this->fields($model, $model->alias, ["{$model->alias}.{$model->primaryKey}"]);
			$passedFields = $this->fields($model, $model->alias, $queryData['fields']);
			if (count($passedFields) === 1) {
				$match = strpos($passedFields[0], (string) $assocFields[0]);
				$match1 = (bool)preg_match('/^[a-z]+\(/i', $passedFields[0]);

				if ($match === false && $match1 === false) {
					$queryData['fields'] = array_merge($passedFields, $assocFields);
				} else {
					$queryData['fields'] = $passedFields;
				}
			} else {
				$queryData['fields'] = array_merge($passedFields, $assocFields);
			}
			unset($assocFields, $passedFields);
		}

		if ($linkModel == null) {
			return $this->buildStatement(
				[
					'fields' => array_unique($queryData['fields']),
					'table' => $this->fullTableName($model),
					'alias' => $model->alias,
					'limit' => $queryData['limit'],
					'offset' => $queryData['offset'],
					'joins' => $queryData['joins'],
					'conditions' => $queryData['conditions'],
					'order' => $queryData['order'],
					'group' => $queryData['group']
				],
				$model
			);
		}
		if ($external && !empty($assocData['finderQuery'])) {
			return $assocData['finderQuery'];
		}

		$alias = $association;
		$self = ($model->name == $linkModel->name);
		$fields = [];

		if ((!$external && in_array($type, ['hasOne', 'belongsTo']) && $this->__bypass === false) || $external) {
			$fields = $this->fields($linkModel, $alias, $assocData['fields']);
		}
		if (empty($assocData['offset']) && !empty($assocData['page'])) {
			$assocData['offset'] = ($assocData['page'] - 1) * $assocData['limit'];
		}
		$assocData['limit'] = $this->limit($assocData['limit'], $assocData['offset']);

		switch ($type) {
			case 'hasOne':
			case 'belongsTo':
				$conditions = $this->__mergeConditions(
					$assocData['conditions'],
					$this->getConstraint($type, $model, $linkModel, $alias, array_merge($assocData, compact('external', 'self')))
				);

				if (!$self && $external) {
					foreach ($conditions as $key => $condition) {
						if (is_numeric($key) && str_contains($condition, $model->alias . '.')) {
							unset($conditions[$key]);
						}
					}
				}

				if ($external) {
					$query = array_merge($assocData, [
						'conditions' => $conditions,
						'table' => $this->fullTableName($linkModel),
						'fields' => $fields,
						'alias' => $alias,
						'group' => null
					]);
					$query = array_merge(['order' => $assocData['order'], 'limit' => $assocData['limit']], $query);
				} else {
					$join = [
						'table' => $linkModel,
						'alias' => $alias,
						'type' => $assocData['type'] ?? 'LEFT',
						'conditions' => trim($this->conditions($conditions, true, false, $model))
					];
					$queryData['fields'] = array_merge($queryData['fields'], $fields);

					if (!empty($assocData['order'])) {
						$queryData['order'][] = $assocData['order'];
					}
					if (!in_array($join, $queryData['joins'])) {
						$queryData['joins'][] = $join;
					}
					return true;
				}
			break;
			case 'hasMany':
				$assocData['fields'] = $this->fields($linkModel, $alias, $assocData['fields']);
				if (!empty($assocData['foreignKey'])) {
					$assocData['fields'] = array_merge($assocData['fields'], $this->fields($linkModel, $alias, ["{$alias}.{$assocData['foreignKey']}"]));
				}
				$query = [
					'conditions' => $this->__mergeConditions($this->getConstraint('hasMany', $model, $linkModel, $alias, $assocData), $assocData['conditions']),
					'fields' => array_unique($assocData['fields']),
					'table' => $this->fullTableName($linkModel),
					'alias' => $alias,
					'order' => $assocData['order'],
					'limit' => $assocData['limit'],
					'group' => null
				];
			break;
			case 'hasAndBelongsToMany':
				$joinFields = [];
				$joinAssoc = null;

				if (isset($assocData['with']) && !empty($assocData['with'])) {
					$joinKeys = [$assocData['foreignKey'], $assocData['associationForeignKey']];
					[$with, $joinFields] = $model->joinModel($assocData['with'], $joinKeys);

					$joinTbl = $model->{$with};
					$joinAlias = $joinTbl;

					if (is_array($joinFields) && !empty($joinFields)) {
						$joinFields = $this->fields($model->{$with}, $model->{$with}->alias, $joinFields);
						$joinAssoc = $joinAlias = $model->{$with}->alias;
					} else {
						$joinFields = [];
					}
				} else {
					$joinTbl = $assocData['joinTable'];
					$joinAlias = $this->fullTableName($assocData['joinTable']);
				}
				$query = [
					'conditions' => $assocData['conditions'],
					'limit' => $assocData['limit'],
					'table' => $this->fullTableName($linkModel),
					'alias' => $alias,
					'fields' => array_merge($this->fields($linkModel, $alias, $assocData['fields']), $joinFields),
					'order' => $assocData['order'],
					'group' => null,
					'joins' => [[
						'table' => $joinTbl,
						'alias' => $joinAssoc,
						'conditions' => $this->getConstraint('hasAndBelongsToMany', $model, $linkModel, $joinAlias, $assocData, $alias)
					]]
				];
			break;
		}
		if (isset($query)) {
			return $this->buildStatement($query, $model);
		}
		return null;
	}

/**
 * Returns a conditions array for the constraint between two models
 *
 * @param string $type Association type
 * @param object $model Model object
 * @param array $association Association array
 * @return array Conditions array defining the constraint between $model and $association
 * @access public
 */
	public function getConstraint($type, $model, $linkModel, $alias, $assoc, $alias2 = null) {
		$assoc = array_merge(['external' => false, 'self' => false], $assoc);

		if (array_key_exists('foreignKey', $assoc) && empty($assoc['foreignKey'])) {
			return [];
		}

		switch (true) {
			case ($assoc['external'] && $type == 'hasOne'):
				return ["{$alias}.{$assoc['foreignKey']}" => '{$__cakeID__$}'];
			break;
			case ($assoc['external'] && $type == 'belongsTo'):
				return ["{$alias}.{$linkModel->primaryKey}" => '{$__cakeForeignKey__$}'];
			break;
			case (!$assoc['external'] && $type == 'hasOne'):
				return ["{$alias}.{$assoc['foreignKey']}" => $this->identifier("{$model->alias}.{$model->primaryKey}")];
			break;
			case (!$assoc['external'] && $type == 'belongsTo'):
				return ["{$model->alias}.{$assoc['foreignKey']}" => $this->identifier("{$alias}.{$linkModel->primaryKey}")];
			break;
			case ($type == 'hasMany'):
				return ["{$alias}.{$assoc['foreignKey']}" => ['{$__cakeID__$}']];
			break;
			case ($type == 'hasAndBelongsToMany'):
				return [
					["{$alias}.{$assoc['foreignKey']}" => '{$__cakeID__$}'],
					["{$alias}.{$assoc['associationForeignKey']}" => $this->identifier("{$alias2}.{$linkModel->primaryKey}")]
				];
			break;
		}
		return [];
	}

/**
 * Builds and generates a JOIN statement from an array.	 Handles final clean-up before conversion.
 *
 * @param array $join An array defining a JOIN statement in a query
 * @return string An SQL JOIN statement to be used in a query
 * @access public
 * @see DboSource::renderJoinStatement()
 * @see DboSource::buildStatement()
 */
	public function buildJoinStatement($join) {
		$data = array_merge([
			'type' => null,
			'alias' => null,
			'table' => 'join_table',
			'conditions' => []
		], $join);

		if (!empty($data['alias'])) {
			$data['alias'] = $this->alias . $this->name($data['alias']);
		}
		if (!empty($data['conditions'])) {
			$data['conditions'] = trim($this->conditions($data['conditions'], true, false));
		}
		if (!empty($data['table'])) {
			$data['table'] = $this->fullTableName($data['table']);
		}
		return $this->renderJoinStatement($data);
	}

/**
 * Builds and generates an SQL statement from an array.	 Handles final clean-up before conversion.
 *
 * @param array $query An array defining an SQL query
 * @param object $model The model object which initiated the query
 * @return string An executable SQL statement
 * @access public
 * @see DboSource::renderStatement()
 */
	public function buildStatement($query, &$model) {
		$query = array_merge(['offset' => null, 'joins' => []], $query);
		if (!empty($query['joins'])) {
			$count = count($query['joins']);
			for ($i = 0; $i < $count; $i++) {
				if (is_array($query['joins'][$i])) {
					$query['joins'][$i] = $this->buildJoinStatement($query['joins'][$i]);
				}
			}
		}
		return $this->renderStatement('select', [
			'conditions' => $this->conditions($query['conditions'], true, true, $model),
			'fields' => implode(', ', $query['fields']),
			'table' => $query['table'],
			'alias' => $this->alias . $this->name($query['alias']),
			'order' => $this->order($query['order'], 'ASC', $model),
			'limit' => $this->limit($query['limit'], $query['offset']),
			'joins' => implode(' ', $query['joins']),
			'group' => $this->group($query['group'], $model)
		]);
	}

/**
 * Renders a final SQL JOIN statement
 *
 * @param array $data
 * @return string
 * @access public
 */
	public function renderJoinStatement($data) {
		extract($data);
		return trim("{$type} JOIN {$table} {$alias} ON ({$conditions})");
	}

/**
 * Renders a final SQL statement by putting together the component parts in the correct order
 *
 * @param string $type type of query being run.  e.g select, create, update, delete, schema, alter.
 * @param array $data Array of data to insert into the query.
 * @return string Rendered SQL expression to be run.
 * @access public
 */
	public function renderStatement($type, $data) {
		extract($data);
		$aliases = null;

		switch (strtolower($type)) {
			case 'select':
				return "SELECT {$fields} FROM {$table} {$alias} {$joins} {$conditions} {$group} {$order} {$limit}";
			break;
			case 'create':
				return "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
			break;
			case 'update':
				if (!empty($alias)) {
					$aliases = "{$this->alias}{$alias} {$joins} ";
				}
				return "UPDATE {$table} {$aliases}SET {$fields} {$conditions}";
			break;
			case 'delete':
				if (!empty($alias)) {
					$aliases = "{$this->alias}{$alias} {$joins} ";
				}
				return "DELETE {$alias} FROM {$table} {$aliases}{$conditions}";
			break;
			case 'schema':
				foreach (['columns', 'indexes', 'tableParameters'] as $var) {
					if (is_array(${$var})) {
						${$var} = "\t" . join(",\n\t", array_filter(${$var}));
					} else {
						${$var} = '';
					}
				}
				if (trim($indexes) != '') {
					$columns .= ',';
				}
				return "CREATE TABLE {$table} (\n{$columns}{$indexes}){$tableParameters};";
			break;
			case 'alter':
			break;
		}
	}

/**
 * Merges a mixed set of string/array conditions
 *
 * @return array
 * @access private
 */
	public function __mergeConditions($query, $assoc) {
		if (empty($assoc)) {
			return $query;
		}

		if (is_array($query)) {
			return array_merge((array)$assoc, $query);
		}

		if (!empty($query)) {
			$query = [$query];
			if (is_array($assoc)) {
				$query = array_merge($query, $assoc);
			} else {
				$query[] = $assoc;
			}
			return $query;
		}

		return $assoc;
	}

/**
 * Generates and executes an SQL UPDATE statement for given model, fields, and values.
 * For databases that do not support aliases in UPDATE queries.
 *
 * @param Model $model
 * @param array $fields
 * @param array $values
 * @param mixed $conditions
 * @return boolean Success
 * @access public
 */
	public function update(&$model, $fields = [], $values = null, $conditions = null) {
		if ($values == null) {
			$combined = $fields;
		} else {
			$combined = array_combine($fields, $values);
		}

		$fields = implode(', ', $this->_prepareUpdateFields($model, $combined, empty($conditions)));

		$alias = $joins = null;
		$table = $this->fullTableName($model);
		$conditions = $this->_matchRecords($model, $conditions);

		if ($conditions === false) {
			return false;
		}
		$query = compact('table', 'alias', 'joins', 'fields', 'conditions');

		if (!$this->execute($this->renderStatement('update', $query))) {
			$model->onError();
			return false;
		}
		return true;
	}

/**
 * Quotes and prepares fields and values for an SQL UPDATE statement
 *
 * @param Model $model
 * @param array $fields
 * @param boolean $quoteValues If values should be quoted, or treated as SQL snippets
 * @param boolean $alias Include the model alias in the field name
 * @return array Fields and values, quoted and preparted
 * @access protected
 */
	public function _prepareUpdateFields(&$model, $fields, $quoteValues = true, $alias = false) {
		$quotedAlias = $this->startQuote . $model->alias . $this->endQuote;

		$updates = [];
		foreach ($fields as $field => $value) {
			if ($alias && !str_contains($field, '.')) {
				$quoted = $model->escapeField($field);
			} elseif (!$alias && str_contains($field, '.')) {
				$quoted = $this->name(str_replace($quotedAlias . '.', '', str_replace(
					$model->alias . '.', '', $field
				)));
			} else {
				$quoted = $this->name($field);
			}

			if ($value === null) {
				$updates[] = $quoted . ' = NULL';
				continue;
			}
			$update = $quoted . ' = ';

			if ($quoteValues) {
				$update .= $this->value($value, $model->getColumnType($field), false);
			} elseif (!$alias) {
				$update .= str_replace($quotedAlias . '.', '', str_replace(
					$model->alias . '.', '', $value
				));
			} else {
				$update .= $value;
			}
			$updates[] =  $update;
		}
		return $updates;
	}

/**
 * Generates and executes an SQL DELETE statement.
 * For databases that do not support aliases in UPDATE queries.
 *
 * @param Model $model
 * @param mixed $conditions
 * @return boolean Success
 * @access public
 */
	public function delete(&$model, $conditions = null) {
		$alias = $joins = null;
		$table = $this->fullTableName($model);
		$conditions = $this->_matchRecords($model, $conditions);

		if ($conditions === false) {
			return false;
		}

		if ($this->execute($this->renderStatement('delete', compact('alias', 'table', 'joins', 'conditions'))) === false) {
			$model->onError();
			return false;
		}
		return true;
	}

/**
 * Gets a list of record IDs for the given conditions.	Used for multi-record updates and deletes
 * in databases that do not support aliases in UPDATE/DELETE queries.
 *
 * @param Model $model
 * @param mixed $conditions
 * @return array List of record IDs
 * @access protected
 */
	public function _matchRecords(&$model, $conditions = null) {
		if ($conditions === true) {
			$conditions = $this->conditions(true);
		} elseif ($conditions === null) {
			$conditions = $this->conditions($this->defaultConditions($model, $conditions, false), true, true, $model);
		} else {
			$noJoin = true;
			foreach ($conditions as $field => $value) {
				$originalField = $field;
				if (str_contains($field, '.')) {
					[$alias, $field] = explode('.', $field);
					$field = ltrim($field, $this->startQuote);
					$field = rtrim($field, $this->endQuote);
				}
				if (!$model->hasField($field)) {
					$noJoin = false;
					break;
				}
				if ($field !== $originalField) {
					$conditions[$field] = $value;
					unset($conditions[$originalField]);
				}
			}
			if ($noJoin === true) {
				return $this->conditions($conditions);
			}
			$idList = $model->find('all', [
				'fields' => "{$model->alias}.{$model->primaryKey}",
				'conditions' => $conditions
			]);

			if (empty($idList)) {
				return false;
			}
			$conditions = $this->conditions([
				$model->primaryKey => Set::extract($idList, "{n}.{$model->alias}.{$model->primaryKey}")
			]);
		}
		return $conditions;
	}

/**
 * Returns an array of SQL JOIN fragments from a model's associations
 *
 * @param object $model
 * @return array
 * @access protected
 */
	public function _getJoins($model) {
		$join = [];
		$joins = array_merge($model->getAssociated('hasOne'), $model->getAssociated('belongsTo'));

		foreach ($joins as $assoc) {
			if (isset($model->{$assoc}) && $model->useDbConfig == $model->{$assoc}->useDbConfig) {
				$assocData = $model->getAssociated($assoc);
				$join[] = $this->buildJoinStatement([
					'table' => $model->{$assoc},
					'alias' => $assoc,
					'type' => $assocData['type'] ?? 'LEFT',
					'conditions' => trim($this->conditions(
						$this->__mergeConditions($assocData['conditions'], $this->getConstraint($assocData['association'], $model, $model->{$assoc}, $assoc, $assocData)),
						true, false, $model
					))
				]);
			}
		}
		return $join;
	}

/**
 * Returns an SQL calculation, i.e. COUNT() or MAX()
 *
 * @param model $model
 * @param string $func Lowercase name of SQL function, i.e. 'count' or 'max'
 * @param array $params Function parameters (any values must be quoted manually)
 * @return string An SQL calculation function
 * @access public
 */
	public function calculate(&$model, $func, $params = []) {
		$params = (array)$params;

		switch (strtolower($func)) {
			case 'count':
				$params[0] ??= '*';
				$params[1] ??= 'count';
				if (is_object($model) && $model->isVirtualField($params[0])){
					$arg = $this->__quoteFields($model->getVirtualField($params[0]));
				} else {
					$arg = $this->name($params[0]);
				}
				return 'COUNT(' . $arg . ') AS ' . $this->name($params[1]);
			case 'max':
			case 'min':
				$params[1] ??= $params[0];
				if (is_object($model) && $model->isVirtualField($params[0])) {
					$arg = $this->__quoteFields($model->getVirtualField($params[0]));
				} else {
					$arg = $this->name($params[0]);
				}
				return strtoupper($func) . '(' . $arg . ') AS ' . $this->name($params[1]);
			break;
		}
	}

/**
 * Deletes all the records in a table and resets the count of the auto-incrementing
 * primary key, where applicable.
 *
 * @param mixed $table A string or model class representing the table to be truncated
 * @return boolean	SQL TRUNCATE TABLE statement, false if not applicable.
 * @access public
 */
	public function truncate($table) {
		return $this->execute('TRUNCATE TABLE ' . $this->fullTableName($table));
	}

/**
 * Begin a transaction
 *
 * @param model $model
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 * @access public
 */
	public function begin(&$model) {
		if (parent::begin($model) && $this->execute($this->_commands['begin'])) {
			$this->_transactionStarted = true;
			return true;
		}
		return false;
	}

/**
 * Commit a transaction
 *
 * @param model $model
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 * @access public
 */
	public function commit(&$model) {
		if (parent::commit($model) && $this->execute($this->_commands['commit'])) {
			$this->_transactionStarted = false;
			return true;
		}
		return false;
	}

/**
 * Rollback a transaction
 *
 * @param model $model
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 * @access public
 */
	public function rollback(&$model) {
		if (parent::rollback($model) && $this->execute($this->_commands['rollback'])) {
			$this->_transactionStarted = false;
			return true;
		}
		return false;
	}

/**
 * Creates a default set of conditions from the model if $conditions is null/empty.
 * If conditions are supplied then they will be returned.  If a model doesn't exist and no conditions
 * were provided either null or false will be returned based on what was input.
 *
 * @param object $model
 * @param mixed $conditions Array of conditions, conditions string, null or false. If an array of conditions,
 *   or string conditions those conditions will be returned.  With other values the model's existance will be checked.
 *   If the model doesn't exist a null or false will be returned depending on the input value.
 * @param boolean $useAlias Use model aliases rather than table names when generating conditions
 * @return mixed Either null, false, $conditions or an array of default conditions to use.
 * @see DboSource::update()
 * @see DboSource::conditions()
 * @access public
 */
	public function defaultConditions(&$model, $conditions, $useAlias = true) {
		if (!empty($conditions)) {
			return $conditions;
		}
		$exists = $model->exists();
		if (!$exists && ($conditions !== null || !empty($model->__safeUpdateMode))) {
			return false;
		} elseif (!$exists) {
			return null;
		}
		$alias = $model->alias;

		if (!$useAlias) {
			$alias = $this->fullTableName($model, false);
		}
		return ["{$alias}.{$model->primaryKey}" => $model->getID()];
	}

/**
 * Returns a key formatted like a string Model.fieldname(i.e. Post.title, or Country.name)
 *
 * @param unknown_type $model
 * @param unknown_type $key
 * @param unknown_type $assoc
 * @return string
 * @access public
 */
	public function resolveKey($model, $key, $assoc = null) {
		if (empty($assoc)) {
			$assoc = $model->alias;
		}
		if (!strpos('.', (string) $key)) {
			return $this->name($model->alias) . '.' . $this->name($key);
		}
		return $key;
	}

/**
 * Private helper method to remove query metadata in given data array.
 *
 * @param array $data
 * @return array
 * @access public
 */
	public function __scrubQueryData($data) {
		foreach (['conditions', 'fields', 'joins', 'order', 'limit', 'offset', 'group'] as $key) {
			if (empty($data[$key])) {
				$data[$key] = [];
			}
		}
		if (!array_key_exists('callbacks', $data)) {
			$data['callbacks'] = null;
		}
		return $data;
	}

/**
 * Converts model virtual fields into sql expressions to be fetched later
 *
 * @param Model $model
 * @param string $alias Alias tablename
 * @param mixed $fields virtual fields to be used on query
 * @return array
 */
	public function _constructVirtualFields(&$model, $alias, $fields) {
		$virtual = [];
		foreach ($fields as $field) {
			$virtualField = $this->name($alias . $this->virtualFieldSeparator . $field);
			$expression = $this->__quoteFields($model->getVirtualField($field));
			$virtual[] = '(' . $expression . ") {$this->alias} {$virtualField}";
		}
		return $virtual;
	}

/**
 * Generates the fields list of an SQL query.
 *
 * @param Model $model
 * @param string $alias Alias tablename
 * @param mixed $fields
 * @param boolean $quote If false, returns fields array unquoted
 * @return array
 * @access public
 */
	public function fields(&$model, $alias = null, $fields = [], $quote = true) {
		if (empty($alias)) {
			$alias = $model->alias;
		}
		$cacheKey = [
			$model->useDbConfig,
			$model->table,
			array_keys($model->schema()),
			$model->name,
			$model->getVirtualField(),
			$alias,
			$fields,
			$quote
		];
		$cacheKey = md5(serialize($cacheKey));
		if ($return = $this->cacheMethod(__FUNCTION__, $cacheKey)) {
			return $return;
		}
		$allFields = empty($fields);
		if ($allFields) {
			$fields = array_keys($model->schema());
		} elseif (!is_array($fields)) {
			$fields = CakeText::tokenize($fields);
		}
		$fields = array_values(array_filter($fields));
		$allFields = $allFields || in_array('*', $fields) || in_array($model->alias . '.*', $fields);

		$virtual = [];
		$virtualFields = $model->getVirtualField();
		if (!empty($virtualFields)) {
			$virtualKeys = array_keys($virtualFields);
			foreach ($virtualKeys as $field) {
				$virtualKeys[] = $model->alias . '.' . $field;
			}
			$virtual = ($allFields) ? $virtualKeys : array_intersect($virtualKeys, $fields);
			foreach ($virtual as $i => $field) {
				if (str_contains($field, '.')) {
					$virtual[$i] = str_replace($model->alias . '.', '', $field);
				}
				$fields = array_diff($fields, [$field]);
			}
			$fields = array_values($fields);
		}

		if (!$quote) {
			if (!empty($virtual)) {
				$fields = array_merge($fields, $this->_constructVirtualFields($model, $alias, $virtual));
			}
			return $fields;
		}
		$count = count($fields);

		if ($count >= 1 && !in_array($fields[0], ['*', 'COUNT(*)'])) {
			for ($i = 0; $i < $count; $i++) {
				if (is_string($fields[$i]) && in_array($fields[$i], $virtual)) {
					unset($fields[$i]);
					continue;
				}
				if (is_object($fields[$i]) && isset($fields[$i]->type) && $fields[$i]->type === 'expression') {
					$fields[$i] = $fields[$i]->value;
				} elseif (preg_match('/^\(.*\)\s' . $this->alias . '.*/i', $fields[$i])){
					continue;
				} elseif (!preg_match('/^.+\\(.*\\)/', $fields[$i])) {
					$prepend = '';

					if (str_contains($fields[$i], 'DISTINCT')) {
						$prepend = 'DISTINCT ';
						$fields[$i] = trim(str_replace('DISTINCT', '', $fields[$i]));
					}
					$dot = strpos($fields[$i], '.');

					if ($dot === false) {
						$prefix = !(
							str_contains($fields[$i], ' ') ||
							str_contains($fields[$i], '(')
						);
						$fields[$i] = $this->name(($prefix ? $alias . '.' : '') . $fields[$i]);
					} else {
						$value = [];
						$comma = strpos($fields[$i], ',');
						if ($comma === false) {
							$build = explode('.', $fields[$i]);
							if (!Set::numeric($build)) {
								$fields[$i] = $this->name(implode('.', $build));
							}
						}
					}
					$fields[$i] = $prepend . $fields[$i];
				} elseif (preg_match('/\(([\.\w]+)\)/', $fields[$i], $field)) {
					if (isset($field[1])) {
						if (!str_contains($field[1], '.')) {
							$field[1] = $this->name($alias . '.' . $field[1]);
						} else {
							$field[0] = explode('.', $field[1]);
							if (!Set::numeric($field[0])) {
								$field[0] = implode('.', array_map([&$this, 'name'], $field[0]));
								$fields[$i] = preg_replace('/\(' . $field[1] . '\)/', '(' . $field[0] . ')', $fields[$i], 1);
							}
						}
					}
				}
			}
		}
		if (!empty($virtual)) {
			$fields = array_merge($fields, $this->_constructVirtualFields($model, $alias, $virtual));
		}
		return $this->cacheMethod(__FUNCTION__, $cacheKey, array_unique($fields));
	}

/**
 * Creates a WHERE clause by parsing given conditions data.  If an array or string
 * conditions are provided those conditions will be parsed and quoted.  If a boolean
 * is given it will be integer cast as condition.  Null will return 1 = 1.
 *
 * Results of this method are stored in a memory cache.  This improves performance, but
 * because the method uses a simple hashing algorithm it can infrequently have collisions.
 * Setting DboSource::$cacheMethods to false will disable the memory cache.
 *
 * @param mixed $conditions Array or string of conditions, or any value.
 * @param boolean $quoteValues If true, values should be quoted
 * @param boolean $where If true, "WHERE " will be prepended to the return value
 * @param Model $model A reference to the Model instance making the query
 * @return string SQL fragment
 * @access public
 */
	public function conditions($conditions, $quoteValues = true, $where = true, $model = null) {
		if (is_object($model)) {
			$cacheKey = [
				$model->useDbConfig,
				$model->table,
				$model->schema(),
				$model->name,
				$model->getVirtualField(),
				$conditions,
				$quoteValues,
				$where
			];
		} else {
			$cacheKey = [$conditions, $quoteValues, $where];
		}
		$cacheKey = md5(serialize($cacheKey));
		if ($return = $this->cacheMethod(__FUNCTION__, $cacheKey)) {
			return $return;
		}

		$clause = $out = '';

		if ($where) {
			$clause = ' WHERE ';
		}

		if (is_array($conditions) && !empty($conditions)) {
			$out = $this->conditionKeysToString($conditions, $quoteValues, $model);

			if (empty($out)) {
				return $this->cacheMethod(__FUNCTION__, $cacheKey, $clause . ' 1 = 1');
			}
			return $this->cacheMethod(__FUNCTION__, $cacheKey, $clause . implode(' AND ', $out));
		}
		if ($conditions === false || $conditions === true) {
			return $this->cacheMethod(__FUNCTION__, $cacheKey,  $clause . (int)$conditions . ' = 1');
		}

		if (empty($conditions) || trim($conditions) == '') {
			return $this->cacheMethod(__FUNCTION__, $cacheKey, $clause . '1 = 1');
		}
		$clauses = '/^WHERE\\x20|^GROUP\\x20BY\\x20|^HAVING\\x20|^ORDER\\x20BY\\x20/i';

		if (preg_match($clauses, $conditions, $match)) {
			$clause = '';
		}
		if (trim($conditions) == '') {
			$conditions = ' 1 = 1';
		} else {
			$conditions = $this->__quoteFields($conditions);
		}
		return $this->cacheMethod(__FUNCTION__, $cacheKey, $clause . $conditions);
	}

/**
 * Creates a WHERE clause by parsing given conditions array.  Used by DboSource::conditions().
 *
 * @param array $conditions Array or string of conditions
 * @param boolean $quoteValues If true, values should be quoted
 * @param Model $model A reference to the Model instance making the query
 * @return string SQL fragment
 * @access public
 */
	public function conditionKeysToString($conditions, $quoteValues = true, $model = null) {
		$c = 0;
		$out = [];
		$data = $columnType = null;
		$bool = ['and', 'or', 'not', 'and not', 'or not', 'xor', '||', '&&'];

		foreach ($conditions as $key => $value) {
			$join = ' AND ';
			$not = null;

			if (is_array($value)) {
				$valueCount = count($value);
				$valueInsert = (!empty($value) && (substr_count($key, '?') === $valueCount || (substr_count($key, ':') === $valueCount && preg_match("/(\:){" . $valueCount . "}/", $key) === 0)));
			}

			if (is_numeric($key) && empty($value)) {
				continue;
			} elseif (is_numeric($key) && is_string($value)) {
				$out[] = $not . $this->__quoteFields($value);
			} elseif ((is_numeric($key) && is_array($value)) || in_array(strtolower(trim($key)), $bool)) {
				if (in_array(strtolower(trim($key)), $bool)) {
					$join = ' ' . strtoupper($key) . ' ';
				} else {
					$key = $join;
				}
				$value = $this->conditionKeysToString($value, $quoteValues, $model);

				if (str_contains($join, 'NOT')) {
					if (strtoupper(trim($key)) == 'NOT') {
						$key = 'AND ' . trim($key);
					}
					$not = 'NOT ';
				}

				if (empty($value[1])) {
					if ($not) {
						$out[] = $not . '(' . $value[0] . ')';
					} else {
						$out[] = $value[0] ;
					}
				} else {
					$out[] = '(' . $not . '(' . implode(') ' . strtoupper($key) . ' (', $value) . '))';
				}

			} else {
				if (is_object($value) && isset($value->type)) {
					if ($value->type == 'identifier') {
						$data .= $this->name($key) . ' = ' . $this->name($value->value);
					} elseif ($value->type == 'expression') {
						if (is_numeric($key)) {
							$data .= $value->value;
						} else {
							$data .= $this->name($key) . ' = ' . $value->value;
						}
					}
				} elseif (is_array($value) && !empty($value) && !$valueInsert) {
					$keys = array_keys($value);
					if ($keys === array_values($keys)) {
						$count = count($value);
						if ($count === 1) {
							$data = $this->__quoteFields($key) . ' = (';
						} else {
							$data = $this->__quoteFields($key) . ' IN (';
						}
						if ($quoteValues) {
							if (is_object($model)) {
								$columnType = $model->getColumnType($key);
							}
							$data .= implode(', ', $this->value($value, $columnType));
						}
						$data .= ')';
					} else {
						$ret = $this->conditionKeysToString($value, $quoteValues, $model);
						if (count($ret) > 1) {
							$data = '(' . implode(') AND (', $ret) . ')';
						} elseif (isset($ret[0])) {
							$data = $ret[0];
						}
					}
				} elseif (is_numeric($key) && !empty($value)) {
					$data = $this->__quoteFields($value);
				} else {
					$data = $this->__parseKey($model, trim($key), $value);
				}

				if ($data != null) {
					$out[] = $data;
					$data = null;
				}
			}
			$c++;
		}
		return $out;
	}

/**
 * Extracts a Model.field identifier and an SQL condition operator from a string, formats
 * and inserts values, and composes them into an SQL snippet.
 *
 * @param Model $model Model object initiating the query
 * @param string $key An SQL key snippet containing a field and optional SQL operator
 * @param mixed $value The value(s) to be inserted in the string
 * @return string
 * @access private
 */
	public function __parseKey(&$model, $key, $value) {
		$operatorMatch = '/^(((' . implode(')|(', $this->__sqlOps);
		$operatorMatch .= ')\\x20?)|<[>=]?(?![^>]+>)\\x20?|[>=!]{1,3}(?!<)\\x20?)/is';
		$bound = (str_contains($key, '?') || (is_array($value) && str_contains($key, ':')));

		if (!strpos($key, ' ')) {
			$operator = '=';
		} else {
			[$key, $operator] = explode(' ', trim($key), 2);

			if (!preg_match($operatorMatch, trim($operator)) && str_contains($operator, ' ')) {
				$key = $key . ' ' . $operator;
				$split = strrpos($key, ' ');
				$operator = substr($key, $split);
				$key = substr($key, 0, $split);
			}
		}

		$virtual = false;
		if (is_object($model) && $model->isVirtualField($key)) {
			$key = $this->__quoteFields($model->getVirtualField($key));
			$virtual = true;
		}

		$type = (is_object($model) ? $model->getColumnType($key) : null);

		$null = ($value === null || (is_array($value) && empty($value)));

		if (strtolower($operator) === 'not') {
			$data = $this->conditionKeysToString(
				[$operator => [$key => $value]], true, $model
			);
			return $data[0];
		}

		$value = $this->value($value, $type);

		if (!$virtual && $key !== '?') {
			$isKey = (str_contains($key, '(') || str_contains($key, ')'));
			$key = $isKey ? $this->__quoteFields($key) : $this->name($key);
		}

		if ($bound) {
			return  CakeText::insert($key . ' ' . trim($operator), $value);
		}

		if (!preg_match($operatorMatch, trim($operator))) {
			$operator .= ' =';
		}
		$operator = trim($operator);

		if (is_array($value)) {
			$value = implode(', ', $value);

			switch ($operator) {
				case '=':
					$operator = 'IN';
				break;
				case '!=':
				case '<>':
					$operator = 'NOT IN';
				break;
			}
			$value = "({$value})";
		} elseif ($null) {
			switch ($operator) {
				case '=':
					$operator = 'IS';
				break;
				case '!=':
				case '<>':
					$operator = 'IS NOT';
				break;
			}
		}
		if ($virtual) {
			return "({$key}) {$operator} {$value}";
		}
		return "{$key} {$operator} {$value}";
	}

/**
 * Quotes Model.fields
 *
 * @param string $conditions
 * @return string or false if no match
 * @access private
 */
	public function __quoteFields($conditions) {
		$start = $end  = null;
		$original = $conditions;

		if (!empty($this->startQuote)) {
			$start = preg_quote($this->startQuote);
		}
		if (!empty($this->endQuote)) {
			$end = preg_quote($this->endQuote);
		}
		$conditions = str_replace([$start, $end], '', $conditions);
		$conditions = preg_replace_callback('/(?:[\'\"][^\'\"\\\]*(?:\\\.[^\'\"\\\]*)*[\'\"])|([a-z0-9_' . $start . $end . ']*\\.[a-z0-9_' . $start . $end . ']*)/i', [&$this, '__quoteMatchedField'], $conditions);

		if ($conditions !== null) {
			return $conditions;
		}
		return $original;
	}

/**
 * Auxiliary function to quote matches `Model.fields` from a preg_replace_callback call
 *
 * @param string matched string
 * @return string quoted strig
 * @access private
 */
	public function __quoteMatchedField($match) {
		if (is_numeric($match[0])) {
			return $match[0];
		}
		return $this->name($match[0]);
	}

/**
 * Returns a limit statement in the correct format for the particular database.
 *
 * @param integer $limit Limit of results returned
 * @param integer $offset Offset from which to start results
 * @return string SQL limit/offset statement
 * @access public
 */
	public function limit($limit, $offset = null) {
		if ($limit) {
			$rt = '';
			if (!strpos(strtolower($limit), 'limit') || str_starts_with(strtolower($limit), 'limit')) {
				$rt = ' LIMIT';
			}

			if ($offset) {
				$rt .= ' ' . $offset . ',';
			}

			$rt .= ' ' . $limit;
			return $rt;
		}
		return null;
	}

/**
 * Returns an ORDER BY clause as a string.
 *
 * @param string $key Field reference, as a key (i.e. Post.title)
 * @param string $direction Direction (ASC or DESC)
 * @param object $model model reference (used to look for virtual field)
 * @return string ORDER BY clause
 * @access public
 */
	public function order($keys, $direction = 'ASC', $model = null) {
		if (!is_array($keys)) {
			$keys = [$keys];
		}
		$keys = array_filter($keys);
		$result = [];
		while (!empty($keys)) {
			$key = key($keys);
			$dir = current($keys);
			array_shift($keys);

			if (is_numeric($key)) {
				$key = $dir;
				$dir = $direction;
			}

			if (is_string($key) && strpos($key, ',') && !preg_match('/\(.+\,.+\)/', $key)) {
				$key = array_map('trim', explode(',', $key));
			}
			if (is_array($key)) {
				//Flatten the array
				$key = array_reverse($key, true);
				foreach ($key as $k => $v) {
					if (is_numeric($k)) {
						array_unshift($keys, $v);
					} else {
						$keys = [$k => $v] + $keys;
					}
				}
				continue;
			} elseif (is_object($key) && isset($key->type) && $key->type === 'expression') {
				$result[] = $key->value;
				continue;
			}

			if (preg_match('/\\x20(ASC|DESC).*/i', $key, $_dir)) {
				$dir = $_dir[0];
				$key = preg_replace('/\\x20(ASC|DESC).*/i', '', $key);
			}

			$key = trim($key);

			if (is_object($model) && $model->isVirtualField($key)) {
				$key =  '(' . $this->__quoteFields($model->getVirtualField($key)) . ')';
			}

			if (strpos($key, '.')) {
				$key = preg_replace_callback('/([a-zA-Z0-9_-]{1,})\\.([a-zA-Z0-9_-]{1,})/', [&$this, '__quoteMatchedField'], $key);
			}
			if (!preg_match('/\s/', $key) && !strpos($key, '.')) {
				$key = $this->name($key);
			}
			$key .= ' ' . trim($dir);
			$result[] = $key;
		}
		if (!empty($result)) {
			return ' ORDER BY ' . implode(', ', $result);
		}
		return '';
	}

/**
 * Create a GROUP BY SQL clause
 *
 * @param string $group Group By Condition
 * @return mixed string condition or null
 * @access public
 */
	public function group($group, $model = null) {
		if ($group) {
			if (!is_array($group)) {
				$group = [$group];
			}
			foreach($group as $index => $key) {
				if (is_object($model) && $model->isVirtualField($key)) {
					$group[$index] = '(' . $model->getVirtualField($key) . ')';
				}
			}
			$group = implode(', ', $group);
			return ' GROUP BY ' . $this->__quoteFields($group);
		}
		return null;
	}

/**
 * Disconnects database, kills the connection and says the connection is closed.
 *
 * @return void
 * @access public
 */
	public function close() {
		$this->disconnect();
	}

/**
 * Checks if the specified table contains any record matching specified SQL
 *
 * @param Model $model Model to search
 * @param string $sql SQL WHERE clause (condition only, not the "WHERE" part)
 * @return boolean True if the table has a matching record, else false
 * @access public
 */
	public function hasAny(&$Model, $sql) {
		$sql = $this->conditions($sql);
		$table = $this->fullTableName($Model);
		$alias = $this->alias . $this->name($Model->alias);
		$where = $sql ? "{$sql}" : ' WHERE 1 = 1';
		$id = $Model->escapeField();

		$out = $this->fetchRow("SELECT COUNT({$id}) {$this->alias}count FROM {$table} {$alias}{$where}");

		if (is_array($out)) {
			return $out[0]['count'];
		}
		return false;
	}

/**
 * Gets the length of a database-native column description, or null if no length
 *
 * @param string $real Real database-layer column type (i.e. "varchar(255)")
 * @return mixed An integer or string representing the length of the column
 * @access public
 */
	public function length($real) {
		if (!preg_match_all('/([\w\s]+)(?:\((\d+)(?:,(\d+))?\))?(\sunsigned)?(\szerofill)?/', $real, $result)) {
			trigger_error(__("FIXME: Can't parse field: " . $real, true), E_USER_WARNING);
			$col = str_replace([')', 'unsigned'], '', $real);
			$limit = null;

			if (str_contains($col, '(')) {
				[$col, $limit] = explode('(', $col);
			}
			if ($limit != null) {
				return intval($limit);
			}
			return null;
		}

		$types = [
			'int' => 1, 'tinyint' => 1, 'smallint' => 1, 'mediumint' => 1, 'integer' => 1, 'bigint' => 1
		];

		[$real, $type, $length, $offset, $sign, $zerofill] = $result;
		$typeArr = $type;
		$type = $type[0];
		$length = $length[0];
		$offset = $offset[0];

		$isFloat = in_array($type, ['dec', 'decimal', 'float', 'numeric', 'double']);
		if ($isFloat && $offset) {
			return $length.','.$offset;
		}

		if (($real[0] == $type) && (count($real) == 1)) {
			return null;
		}

		if (isset($types[$type])) {
			$length += $types[$type];
			if (!empty($sign)) {
				$length--;
			}
		} elseif (in_array($type, ['enum', 'set'])) {
			$length = 0;
			foreach ($typeArr as $key => $enumValue) {
				if ($key == 0) {
					continue;
				}
				$tmpLength = strlen($enumValue);
				if ($tmpLength > $length) {
					$length = $tmpLength;
				}
			}
		}
		return intval($length);
	}

/**
 * Translates between PHP boolean values and Database (faked) boolean values
 *
 * @param mixed $data Value to be translated
 * @return mixed Converted boolean value
 * @access public
 */
	public function boolean($data) {
		if ($data === true || $data === false) {
			if ($data === true) {
				return 1;
			}
			return 0;
		} else {
			return !empty($data);
		}
	}

/**
 * Inserts multiple values into a table
 *
 * @param string $table
 * @param string $fields
 * @param array $values
 * @access protected
 */
	public function insertMulti($table, $fields, $values) {
		$table = $this->fullTableName($table);
		if (is_array($fields)) {
			$fields = implode(', ', array_map([&$this, 'name'], $fields));
		}
		$count = count($values);
		for ($x = 0; $x < $count; $x++) {
			$this->query("INSERT INTO {$table} ({$fields}) VALUES {$values[$x]}");
		}
	}

/**
 * Returns an array of the indexes in given datasource name.
 *
 * @param string $model Name of model to inspect
 * @return array Fields in table. Keys are column and unique
 * @access public
 */
	public function index($model) {
		return false;
	}

/**
 * Generate a database-native schema for the given Schema object
 *
 * @param object $schema An instance of a subclass of CakeSchema
 * @param string $tableName Optional.  If specified only the table name given will be generated.
 *   Otherwise, all tables defined in the schema are generated.
 * @return string
 * @access public
 */
	public function createSchema($schema, $tableName = null) {
		if (!is_a($schema, 'CakeSchema')) {
			trigger_error(__('Invalid schema object', true), E_USER_WARNING);
			return null;
		}
		$out = '';

		foreach ($schema->tables as $curTable => $columns) {
			if (!$tableName || $tableName == $curTable) {
				$cols = $colList = $indexes = $tableParameters = [];
				$primary = null;
				$table = $this->fullTableName($curTable);

				foreach ($columns as $name => $col) {
					if (is_string($col)) {
						$col = ['type' => $col];
					}
					if (isset($col['key']) && $col['key'] == 'primary') {
						$primary = $name;
					}
					if ($name !== 'indexes' && $name !== 'tableParameters') {
						$col['name'] = $name;
						$col['type'] ??= 'string';
						$cols[] = $this->buildColumn($col);
					} elseif ($name == 'indexes') {
						$indexes = array_merge($indexes, $this->buildIndex($col, $table));
					} elseif ($name == 'tableParameters') {
						$tableParameters = array_merge($tableParameters, $this->buildTableParameters($col, $table));
					}
				}
				if (empty($indexes) && !empty($primary)) {
					$col = ['PRIMARY' => ['column' => $primary, 'unique' => 1]];
					$indexes = array_merge($indexes, $this->buildIndex($col, $table));
				}
				$columns = $cols;
				$out .= $this->renderStatement('schema', compact('table', 'columns', 'indexes', 'tableParameters')) . "\n\n";
			}
		}
		return $out;
	}

/**
 * Generate a alter syntax from	 CakeSchema::compare()
 *
 * @param unknown_type $schema
 * @return boolean
 */
	public function alterSchema($compare, $table = null) {
		return false;
	}

/**
 * Generate a "drop table" statement for the given Schema object
 *
 * @param object $schema An instance of a subclass of CakeSchema
 * @param string $table Optional.  If specified only the table name given will be generated.
 *   Otherwise, all tables defined in the schema are generated.
 * @return string
 * @access public
 */
	public function dropSchema($schema, $table = null) {
		if (!is_a($schema, 'CakeSchema')) {
			trigger_error(__('Invalid schema object', true), E_USER_WARNING);
			return null;
		}
		$out = '';

		foreach ($schema->tables as $curTable => $columns) {
			if (!$table || $table == $curTable) {
				$out .= 'DROP TABLE ' . $this->fullTableName($curTable) . ";\n";
			}
		}
		return $out;
	}

/**
 * Generate a database-native column schema string
 *
 * @param array $column An array structured like the following: array('name'=>'value', 'type'=>'value'[, options]),
 *   where options can be 'default', 'length', or 'key'.
 * @return string
 * @access public
 */
	public function buildColumn($column) {
		$name = $type = null;
		extract(array_merge(['null' => true], $column));

		if (empty($name) || empty($type)) {
			trigger_error(__('Column name or type not defined in schema', true), E_USER_WARNING);
			return null;
		}

		if (!isset($this->columns[$type])) {
			trigger_error(sprintf(__('Column type %s does not exist', true), $type), E_USER_WARNING);
			return null;
		}

		$real = $this->columns[$type];
		$out = $this->name($name) . ' ' . $real['name'];

		if (isset($real['limit']) || isset($real['length']) || isset($column['limit']) || isset($column['length'])) {
			if (isset($column['length'])) {
				$length = $column['length'];
			} elseif (isset($column['limit'])) {
				$length = $column['limit'];
			} elseif (isset($real['length'])) {
				$length = $real['length'];
			} else {
				$length = $real['limit'];
			}
			$out .= '(' . $length . ')';
		}

		if (($column['type'] == 'integer' || $column['type'] == 'float' ) && isset($column['default']) && $column['default'] === '') {
			$column['default'] = null;
		}
		$out = $this->_buildFieldParameters($out, $column, 'beforeDefault');

		if (isset($column['key']) && $column['key'] == 'primary' && $type == 'integer') {
			$out .= ' ' . $this->columns['primary_key']['name'];
		} elseif (isset($column['key']) && $column['key'] == 'primary') {
			$out .= ' NOT NULL';
		} elseif (isset($column['default']) && isset($column['null']) && $column['null'] == false) {
			$out .= ' DEFAULT ' . $this->value($column['default'], $type) . ' NOT NULL';
		} elseif (isset($column['default'])) {
			$out .= ' DEFAULT ' . $this->value($column['default'], $type);
		} elseif ($type !== 'timestamp' && !empty($column['null'])) {
			$out .= ' DEFAULT NULL';
		} elseif ($type === 'timestamp' && !empty($column['null'])) {
			$out .= ' NULL';
		} elseif (isset($column['null']) && $column['null'] == false) {
			$out .= ' NOT NULL';
		}
		if ($type == 'timestamp' && isset($column['default']) && strtolower($column['default']) == 'current_timestamp') {
			$out = str_replace(["'CURRENT_TIMESTAMP'", "'current_timestamp'"], 'CURRENT_TIMESTAMP', $out);
		}
		$out = $this->_buildFieldParameters($out, $column, 'afterDefault');
		return $out;
	}

/**
 * Build the field parameters, in a position
 *
 * @param string $columnString The partially built column string
 * @param array $columnData The array of column data.
 * @param string $position The position type to use. 'beforeDefault' or 'afterDefault' are common
 * @return string a built column with the field parameters added.
 * @access public
 */
	public function _buildFieldParameters($columnString, $columnData, $position) {
		foreach ($this->fieldParameters as $paramName => $value) {
			if (isset($columnData[$paramName]) && $value['position'] == $position) {
				if (isset($value['options']) && !in_array($columnData[$paramName], $value['options'])) {
					continue;
				}
				$val = $columnData[$paramName];
				if ($value['quote']) {
					$val = $this->value($val);
				}
				$columnString .= ' ' . $value['value'] . $value['join'] . $val;
			}
		}
		return $columnString;
	}

/**
 * Format indexes for create table
 *
 * @param array $indexes
 * @param string $table
 * @return array
 * @access public
 */
	public function buildIndex($indexes, $table = null) {
		$join = [];
		foreach ($indexes as $name => $value) {
			$out = '';
			if ($name == 'PRIMARY') {
				$out .= 'PRIMARY ';
				$name = null;
			} else {
				if (!empty($value['unique'])) {
					$out .= 'UNIQUE ';
				}
				$name = $this->startQuote . $name . $this->endQuote;
			}
			if (is_array($value['column'])) {
				$out .= 'KEY ' . $name . ' (' . implode(', ', array_map([&$this, 'name'], $value['column'])) . ')';
			} else {
				$out .= 'KEY ' . $name . ' (' . $this->name($value['column']) . ')';
			}
			$join[] = $out;
		}
		return $join;
	}

/**
 * Read additional table parameters
 *
 * @param array $parameters
 * @param string $table
 * @return array
 * @access public
 */
	public function readTableParameters($name) {
		$parameters = [];
		if ($this->isInterfaceSupported('listDetailedSources')) {
			$currentTableDetails = $this->listDetailedSources($name);
			foreach ($this->tableParameters as $paramName => $parameter) {
				if (!empty($parameter['column']) && !empty($currentTableDetails[$parameter['column']])) {
					$parameters[$paramName] = $currentTableDetails[$parameter['column']];
				}
			}
		}
		return $parameters;
	}

/**
 * Format parameters for create table
 *
 * @param array $parameters
 * @param string $table
 * @return array
 * @access public
 */
	public function buildTableParameters($parameters, $table = null) {
		$result = [];
		foreach ($parameters as $name => $value) {
			if (isset($this->tableParameters[$name])) {
				if ($this->tableParameters[$name]['quote']) {
					$value = $this->value($value);
				}
				$result[] = $this->tableParameters[$name]['value'] . $this->tableParameters[$name]['join'] . $value;
			}
		}
		return $result;
	}

/**
 * Guesses the data type of an array
 *
 * @param string $value
 * @return void
 * @access public
 */
	public function introspectType($value) {
		if (!is_array($value)) {
			if ($value === true || $value === false) {
				return 'boolean';
			}
			if (is_float($value) && floatval($value) === $value) {
				return 'float';
			}
			if (is_int($value) && intval($value) === $value) {
				return 'integer';
			}
			if (is_string($value) && strlen($value) > 255) {
				return 'text';
			}
			return 'string';
		}

		$isAllFloat = $isAllInt = true;
		$containsFloat = $containsInt = $containsString = false;
		foreach ($value as $key => $valElement) {
			$valElement = trim($valElement);
			if (!is_float($valElement) && !preg_match('/^[\d]+\.[\d]+$/', $valElement)) {
				$isAllFloat = false;
			} else {
				$containsFloat = true;
				continue;
			}
			if (!is_int($valElement) && !preg_match('/^[\d]+$/', $valElement)) {
				$isAllInt = false;
			} else {
				$containsInt = true;
				continue;
			}
			$containsString = true;
		}

		if ($isAllFloat) {
			return 'float';
		}
		if ($isAllInt) {
			return 'integer';
		}

		if ($containsInt && !$containsString) {
			return 'integer';
		}
		return 'string';
	}
}
