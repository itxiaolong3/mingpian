<?php  namespace Workerman\MySQL;
class Connection 
{
	protected $union = array( );
	protected $for_update = false;
	protected $cols = array( );
	protected $from = array( );
	protected $from_key = -1;
	protected $group_by = array( );
	protected $having = array( );
	protected $bind_having = array( );
	protected $paging = 10;
	protected $bind_values = array( );
	protected $where = array( );
	protected $bind_where = array( );
	protected $order_by = array( );
	protected $order_asc = true;
	protected $limit = 0;
	protected $offset = 0;
	protected $flags = array( );
	protected $table = NULL;
	protected $last_insert_id_names = array( );
	protected $col_values = NULL;
	protected $returning = array( );
	protected $type = "";
	protected $pdo = NULL;
	protected $sQuery = NULL;
	protected $settings = array( );
	protected $parameters = array( );
	protected $lastSql = "";
	protected $success = false;
	public function select($cols = "*") 
	{
		$this->type = "SELECT";
		if( !is_array($cols) ) 
		{
			$cols = explode(",", $cols);
		}
		$this->cols($cols);
		return $this;
	}
	public function delete($table) 
	{
		$this->type = "DELETE";
		$this->table = $this->quoteName($table);
		$this->fromRaw($this->quoteName($table));
		return $this;
	}
	public function update($table) 
	{
		$this->type = "UPDATE";
		$this->table = $this->quoteName($table);
		return $this;
	}
	public function insert($table) 
	{
		$this->type = "INSERT";
		$this->table = $this->quoteName($table);
		return $this;
	}
	public function calcFoundRows($enable = true) 
	{
		$this->setFlag("SQL_CALC_FOUND_ROWS", $enable);
		return $this;
	}
	public function cache($enable = true) 
	{
		$this->setFlag("SQL_CACHE", $enable);
		return $this;
	}
	public function noCache($enable = true) 
	{
		$this->setFlag("SQL_NO_CACHE", $enable);
		return $this;
	}
	public function straightJoin($enable = true) 
	{
		$this->setFlag("STRAIGHT_JOIN", $enable);
		return $this;
	}
	public function highPriority($enable = true) 
	{
		$this->setFlag("HIGH_PRIORITY", $enable);
		return $this;
	}
	public function smallResult($enable = true) 
	{
		$this->setFlag("SQL_SMALL_RESULT", $enable);
		return $this;
	}
	public function bigResult($enable = true) 
	{
		$this->setFlag("SQL_BIG_RESULT", $enable);
		return $this;
	}
	public function bufferResult($enable = true) 
	{
		$this->setFlag("SQL_BUFFER_RESULT", $enable);
		return $this;
	}
	public function forUpdate($enable = true) 
	{
		$this->for_update = (bool) $enable;
		return $this;
	}
	public function distinct($enable = true) 
	{
		$this->setFlag("DISTINCT", $enable);
		return $this;
	}
	public function lowPriority($enable = true) 
	{
		$this->setFlag("LOW_PRIORITY", $enable);
		return $this;
	}
	public function ignore($enable = true) 
	{
		$this->setFlag("IGNORE", $enable);
		return $this;
	}
	public function quick($enable = true) 
	{
		$this->setFlag("QUICK", $enable);
		return $this;
	}
	public function delayed($enable = true) 
	{
		$this->setFlag("DELAYED", $enable);
		return $this;
	}
	public function __toString() 
	{
		$union = "";
		if( $this->union ) 
		{
			$union = implode(" ", $this->union) . " ";
		}
		return $union . $this->build();
	}
	public function setPaging($paging) 
	{
		$this->paging = (int) $paging;
		return $this;
	}
	public function getPaging() 
	{
		return $this->paging;
	}
	public function getBindValues() 
	{
		switch( $this->type ) 
		{
			case "SELECT": return $this->getBindValuesSELECT();
			case "DELETE": case "UPDATE": case "INSERT": return $this->getBindValuesCOMMON();
			default: throw new \Exception("type err");
		}
	}
	public function getBindValuesSELECT() 
	{
		$bind_values = $this->bind_values;
		$i = 1;
		foreach( $this->bind_where as $val ) 
		{
			$bind_values[$i] = $val;
			$i++;
		}
		foreach( $this->bind_having as $val ) 
		{
			$bind_values[$i] = $val;
			$i++;
		}
		return $bind_values;
	}
	protected function addColSELECT($key, $val) 
	{
		if( is_string($key) ) 
		{
			$this->cols[$val] = $key;
		}
		else 
		{
			$this->addColWithAlias($val);
		}
	}
	protected function addColWithAlias($spec) 
	{
		$parts = explode(" ", $spec);
		$count = count($parts);
		if( $count == 2 && trim($parts[0]) != "" && trim($parts[1]) != "" ) 
		{
			$this->cols[$parts[1]] = $parts[0];
		}
		else 
		{
			if( $count == 3 && strtoupper($parts[1]) == "AS" ) 
			{
				$this->cols[$parts[2]] = $parts[0];
			}
			else 
			{
				$this->cols[] = trim($spec);
			}
		}
	}
	public function from($table) 
	{
		return $this->fromRaw($this->quoteName($table));
	}
	public function fromRaw($table) 
	{
		$this->from[] = array( $table );
		$this->from_key++;
		return $this;
	}
	public function fromSubSelect($table, $name) 
	{
		$this->from[] = array( "(" . $table . ") AS " . $this->quoteName($name) );
		$this->from_key++;
		return $this;
	}
	public function join($table, $cond = NULL, $type = "") 
	{
		return $this->joinInternal($type, $table, $cond);
	}
	protected function joinInternal($join, $table, $cond = NULL) 
	{
		if( !$this->from ) 
		{
			throw new \Exception("Cannot join() without from()");
		}
		$join = strtoupper(ltrim((string) $join . " JOIN"));
		$table = $this->quoteName($table);
		$cond = $this->fixJoinCondition($cond);
		$this->from[$this->from_key][] = rtrim((string) $join . " " . $table . " " . $cond);
		return $this;
	}
	protected function fixJoinCondition($cond) 
	{
		if( !$cond ) 
		{
			return "";
		}
		$cond = $this->quoteNamesIn($cond);
		if( strtoupper(substr(ltrim($cond), 0, 3)) == "ON " ) 
		{
			return $cond;
		}
		if( strtoupper(substr(ltrim($cond), 0, 6)) == "USING " ) 
		{
			return $cond;
		}
		return "ON " . $cond;
	}
	public function innerJoin($table, $cond = NULL) 
	{
		return $this->joinInternal("INNER", $table, $cond);
	}
	public function leftJoin($table, $cond = NULL) 
	{
		return $this->joinInternal("LEFT", $table, $cond);
	}
	public function rightJoin($table, $cond = NULL) 
	{
		return $this->joinInternal("RIGHT", $table, $cond);
	}
	public function joinSubSelect($join, $spec, $name, $cond = NULL) 
	{
		if( !$this->from ) 
		{
			throw new \Exception("Cannot join() without from() first.");
		}
		$join = strtoupper(ltrim((string) $join . " JOIN"));
		$name = $this->quoteName($name);
		$cond = $this->fixJoinCondition($cond);
		$this->from[$this->from_key][] = rtrim((string) $join . " (" . $spec . ") AS " . $name . " " . $cond);
		return $this;
	}
	public function groupBy(array $cols) 
	{
		foreach( $cols as $col ) 
		{
			$this->group_by[] = $this->quoteNamesIn($col);
		}
		return $this;
	}
	public function having($cond) 
	{
		$this->addClauseCondWithBind("having", "AND", func_get_args());
		return $this;
	}
	public function orHaving($cond) 
	{
		$this->addClauseCondWithBind("having", "OR", func_get_args());
		return $this;
	}
	public function page($page) 
	{
		$this->limit = 0;
		$this->offset = 0;
		$page = (int) $page;
		if( 0 < $page ) 
		{
			$this->limit = $this->paging;
			$this->offset = $this->paging * ($page - 1);
		}
		return $this;
	}
	public function union() 
	{
		$this->union[] = $this->build() . " UNION";
		$this->reset();
		return $this;
	}
	public function unionAll() 
	{
		$this->union[] = $this->build() . " UNION ALL";
		$this->reset();
		return $this;
	}
	protected function reset() 
	{
		$this->resetFlags();
		$this->cols = array( );
		$this->from = array( );
		$this->from_key = -1;
		$this->where = array( );
		$this->group_by = array( );
		$this->having = array( );
		$this->order_by = array( );
		$this->limit = 0;
		$this->offset = 0;
		$this->for_update = false;
	}
	protected function resetAll() 
	{
		$this->union = array( );
		$this->for_update = false;
		$this->cols = array( );
		$this->from = array( );
		$this->from_key = -1;
		$this->group_by = array( );
		$this->having = array( );
		$this->bind_having = array( );
		$this->paging = 10;
		$this->bind_values = array( );
		$this->where = array( );
		$this->bind_where = array( );
		$this->order_by = array( );
		$this->limit = 0;
		$this->offset = 0;
		$this->flags = array( );
		$this->table = "";
		$this->last_insert_id_names = array( );
		$this->col_values = array( );
		$this->returning = array( );
		$this->parameters = array( );
	}
	protected function buildSELECT() 
	{
		return "SELECT" . $this->buildFlags() . $this->buildCols() . $this->buildFrom() . $this->buildWhere() . $this->buildGroupBy() . $this->buildHaving() . $this->buildOrderBy() . $this->buildLimit() . $this->buildForUpdate();
	}
	protected function buildDELETE() 
	{
		return "DELETE" . $this->buildFlags() . $this->buildFrom() . $this->buildWhere() . $this->buildOrderBy() . $this->buildLimit() . $this->buildReturning();
	}
	protected function buildCols() 
	{
		if( !$this->cols ) 
		{
			throw new \Exception("No columns in the SELECT.");
		}
		$cols = array( );
		foreach( $this->cols as $key => $val ) 
		{
			if( is_int($key) ) 
			{
				$cols[] = $this->quoteNamesIn($val);
			}
			else 
			{
				$cols[] = $this->quoteNamesIn((string) $val . " AS " . $key);
			}
		}
		return $this->indentCsv($cols);
	}
	protected function buildFrom() 
	{
		if( !$this->from ) 
		{
			return "";
		}
		$refs = array( );
		foreach( $this->from as $from ) 
		{
			$refs[] = implode(" ", $from);
		}
		return " FROM" . $this->indentCsv($refs);
	}
	protected function buildGroupBy() 
	{
		if( !$this->group_by ) 
		{
			return "";
		}
		return " GROUP BY" . $this->indentCsv($this->group_by);
	}
	protected function buildHaving() 
	{
		if( !$this->having ) 
		{
			return "";
		}
		return " HAVING" . $this->indent($this->having);
	}
	protected function buildForUpdate() 
	{
		if( !$this->for_update ) 
		{
			return "";
		}
		return " FOR UPDATE";
	}
	public function where($cond) 
	{
		if( is_array($cond) ) 
		{
			foreach( $cond as $key => $val ) 
			{
				if( is_string($key) ) 
				{
					$this->addWhere("AND", array( $key, $val ));
				}
				else 
				{
					$this->addWhere("AND", array( $val ));
				}
			}
		}
		else 
		{
			$this->addWhere("AND", func_get_args());
		}
		return $this;
	}
	public function orWhere($cond) 
	{
		if( is_array($cond) ) 
		{
			foreach( $cond as $key => $val ) 
			{
				if( is_string($key) ) 
				{
					$this->addWhere("OR", array( $key, $val ));
				}
				else 
				{
					$this->addWhere("OR", array( $val ));
				}
			}
		}
		else 
		{
			$this->addWhere("OR", func_get_args());
		}
		return $this;
	}
	public function limit($limit) 
	{
		$this->limit = (int) $limit;
		return $this;
	}
	public function offset($offset) 
	{
		$this->offset = (int) $offset;
		return $this;
	}
	public function orderBy(array $cols) 
	{
		return $this->addOrderBy($cols);
	}
	public function orderByASC(array $cols, $order_asc = true) 
	{
		$this->order_asc = $order_asc;
		return $this->addOrderBy($cols);
	}
	public function orderByDESC(array $cols) 
	{
		$this->order_asc = false;
		return $this->addOrderBy($cols);
	}
	protected function indentCsv(array $list) 
	{
		return " " . implode(",", $list);
	}
	protected function indent(array $list) 
	{
		return " " . implode(" ", $list);
	}
	public function bindValues(array $bind_values) 
	{
		foreach( $bind_values as $key => $val ) 
		{
			$this->bindValue($key, $val);
		}
		return $this;
	}
	public function bindValue($name, $value) 
	{
		$this->bind_values[$name] = $value;
		return $this;
	}
	protected function buildFlags() 
	{
		if( !$this->flags ) 
		{
			return "";
		}
		return " " . implode(" ", array_keys($this->flags));
	}
	protected function setFlag($flag, $enable = true) 
	{
		if( $enable ) 
		{
			$this->flags[$flag] = true;
		}
		else 
		{
			unset($this->flags[$flag]);
		}
	}
	protected function resetFlags() 
	{
		$this->flags = array( );
	}
	protected function addWhere($andor, $conditions) 
	{
		$this->addClauseCondWithBind("where", $andor, $conditions);
		return $this;
	}
	protected function addClauseCondWithBind($clause, $andor, $conditions) 
	{
		$cond = array_shift($conditions);
		$cond = $this->quoteNamesIn($cond);
		$bind =& $this->
		{
			"bind_" . $clause}
		;
		foreach( $conditions as $value ) 
		{
			$bind[] = $value;
		}
		$clause =& $this->$clause;
		if( $clause ) 
		{
			$clause[] = (string) $andor . " " . $cond;
		}
		else 
		{
			$clause[] = $cond;
		}
	}
	protected function buildWhere() 
	{
		if( !$this->where ) 
		{
			return "";
		}
		return " WHERE" . $this->indent($this->where);
	}
	protected function addOrderBy(array $spec) 
	{
		foreach( $spec as $col ) 
		{
			$this->order_by[] = $this->quoteNamesIn($col);
		}
		return $this;
	}
	protected function buildOrderBy() 
	{
		if( !$this->order_by ) 
		{
			return "";
		}
		if( $this->order_asc ) 
		{
			return " ORDER BY" . $this->indentCsv($this->order_by) . " ASC";
		}
		return " ORDER BY" . $this->indentCsv($this->order_by) . " DESC";
	}
	protected function buildLimit() 
	{
		$has_limit = $this->type == "DELETE" || $this->type == "UPDATE";
		$has_offset = $this->type == "SELECT";
		if( $has_offset && $this->limit ) 
		{
			$clause = " LIMIT " . $this->limit;
			if( $this->offset ) 
			{
				$clause .= " OFFSET " . $this->offset;
			}
			return $clause;
		}
		if( $has_limit && $this->limit ) 
		{
			return " LIMIT " . $this->limit;
		}
		return "";
	}
	public function quoteName($spec) 
	{
		$spec = trim($spec);
		$seps = array( " AS ", " ", "." );
		foreach( $seps as $sep ) 
		{
			$pos = strripos($spec, $sep);
			if( $pos ) 
			{
				return $this->quoteNameWithSeparator($spec, $sep, $pos);
			}
		}
		return $this->replaceName($spec);
	}
	protected function quoteNameWithSeparator($spec, $sep, $pos) 
	{
		$len = strlen($sep);
		$part1 = $this->quoteName(substr($spec, 0, $pos));
		$part2 = $this->replaceName(substr($spec, $pos + $len));
		return (string) $part1 . $sep . $part2;
	}
	public function quoteNamesIn($text) 
	{
		$list = $this->getListForQuoteNamesIn($text);
		$last = count($list) - 1;
		$text = null;
		foreach( $list as $key => $val ) 
		{
			if( ($key + 1) % 3 ) 
			{
				$text .= $this->quoteNamesInLoop($val, $key == $last);
			}
		}
		return $text;
	}
	protected function getListForQuoteNamesIn($text) 
	{
		$apos = "'";
		$quot = "\"";
		return preg_split("/((" . $apos . "+|" . $quot . "+|\\" . $apos . "+|\\" . $quot . "+).*?\\2)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	}
	protected function quoteNamesInLoop($val, $is_last) 
	{
		if( $is_last ) 
		{
			return $this->replaceNamesAndAliasIn($val);
		}
		return $this->replaceNamesIn($val);
	}
	protected function replaceNamesAndAliasIn($val) 
	{
		$quoted = $this->replaceNamesIn($val);
		$pos = strripos($quoted, " AS ");
		if( $pos !== false ) 
		{
			$bracket = strripos($quoted, ")");
			if( $bracket === false ) 
			{
				$alias = $this->replaceName(substr($quoted, $pos + 4));
				$quoted = substr($quoted, 0, $pos) . " AS " . $alias;
			}
		}
		return $quoted;
	}
	protected function replaceName($name) 
	{
		$name = trim($name);
		if( $name == "*" ) 
		{
			return $name;
		}
		return "`" . $name . "`";
	}
	protected function replaceNamesIn($text) 
	{
		$is_string_literal = strpos($text, "'") !== false || strpos($text, "\"") !== false;
		if( $is_string_literal ) 
		{
			return $text;
		}
		$word = "[a-z_][a-z0-9_]*";
		$find = "/(\\b)(" . $word . ")\\.(" . $word . ")(\\b)/i";
		$repl = "\$1`\$2`.`\$3`\$4";
		$text = preg_replace($find, $repl, $text);
		return $text;
	}
	public function setLastInsertIdNames(array $last_insert_id_names) 
	{
		$this->last_insert_id_names = $last_insert_id_names;
	}
	public function into($table) 
	{
		$this->table = $this->quoteName($table);
		return $this;
	}
	protected function buildINSERT() 
	{
		return "INSERT" . $this->buildFlags() . $this->buildInto() . $this->buildValuesForInsert() . $this->buildReturning();
	}
	protected function buildInto() 
	{
		return " INTO " . $this->table;
	}
	public function getLastInsertIdName($col) 
	{
		$key = str_replace("`", "", $this->table) . "." . $col;
		if( isset($this->last_insert_id_names[$key]) ) 
		{
			return $this->last_insert_id_names[$key];
		}
		return null;
	}
	public function col($col) 
	{
		return call_user_func_array(array( $this, "addCol" ), func_get_args());
	}
	public function cols(array $cols) 
	{
		if( $this->type == "SELECT" ) 
		{
			foreach( $cols as $key => $val ) 
			{
				$this->addColSELECT($key, $val);
			}
			return $this;
		}
		else 
		{
			return $this->addCols($cols);
		}
	}
	public function set($col, $value) 
	{
		return $this->setCol($col, $value);
	}
	protected function buildValuesForInsert() 
	{
		return " (" . $this->indentCsv(array_keys($this->col_values)) . ") VALUES (" . $this->indentCsv(array_values($this->col_values)) . ")";
	}
	public function table($table) 
	{
		$this->table = $this->quoteName($table);
		return $this;
	}
	protected function build() 
	{
		switch( $this->type ) 
		{
			case "DELETE": return $this->buildDELETE();
			case "INSERT": return $this->buildINSERT();
			case "UPDATE": return $this->buildUPDATE();
			case "SELECT": return $this->buildSELECT();
		}
		throw new \Exception("type empty");
	}
	protected function buildUPDATE() 
	{
		return "UPDATE" . $this->buildFlags() . $this->buildTable() . $this->buildValuesForUpdate() . $this->buildWhere() . $this->buildOrderBy() . $this->buildLimit() . $this->buildReturning();
	}
	protected function buildTable() 
	{
		return " " . $this->table;
	}
	protected function buildValuesForUpdate() 
	{
		$values = array( );
		foreach( $this->col_values as $col => $value ) 
		{
			$values[] = (string) $col . " = " . $value;
		}
		return " SET" . $this->indentCsv($values);
	}
	public function getBindValuesCOMMON() 
	{
		$bind_values = $this->bind_values;
		$i = 1;
		foreach( $this->bind_where as $val ) 
		{
			$bind_values[$i] = $val;
			$i++;
		}
		return $bind_values;
	}
	protected function addCol($col) 
	{
		$key = $this->quoteName($col);
		$this->col_values[$key] = ":" . $col;
		$args = func_get_args();
		if( 1 < count($args) ) 
		{
			$this->bindValue($col, $args[1]);
		}
		return $this;
	}
	protected function addCols(array $cols) 
	{
		foreach( $cols as $key => $val ) 
		{
			if( is_int($key) ) 
			{
				$this->addCol($val);
			}
			else 
			{
				$this->addCol($key, $val);
			}
		}
		return $this;
	}
	protected function setCol($col, $value) 
	{
		if( $value === null ) 
		{
			$value = "NULL";
		}
		$key = $this->quoteName($col);
		$value = $this->quoteNamesIn($value);
		$this->col_values[$key] = $value;
		return $this;
	}
	protected function addReturning(array $cols) 
	{
		foreach( $cols as $col ) 
		{
			$this->returning[] = $this->quoteNamesIn($col);
		}
		return $this;
	}
	protected function buildReturning() 
	{
		if( !$this->returning ) 
		{
			return "";
		}
		return " RETURNING" . $this->indentCsv($this->returning);
	}
	public function __construct($host, $port, $user, $password, $db_name, $charset = "utf8") 
	{
		$this->settings = array( "host" => $host, "port" => $port, "user" => $user, "password" => $password, "dbname" => $db_name, "charset" => $charset );
		$this->connect();
	}
	protected function connect() 
	{
		$dsn = "mysql:dbname=" . $this->settings["dbname"] . ";host=" . $this->settings["host"] . ";port=" . $this->settings["port"];
		$this->pdo = new \PDO($dsn, $this->settings["user"], $this->settings["password"], array( \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . ((!empty($this->settings["charset"]) ? $this->settings["charset"] : "utf8")) ));
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
		$this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
	}
	public function closeConnection() 
	{
		$this->pdo = null;
	}
	protected function execute($query, $parameters = "") 
	{
		try 
		{
			if( is_null($this->pdo) ) 
			{
				$this->connect();
			}
			$this->sQuery = $this->pdo->prepare($query);
			$this->bindMore($parameters);
			if( !empty($this->parameters) ) 
			{
				foreach( $this->parameters as $param ) 
				{
					$parameters = explode("", $param);
					$this->sQuery->bindParam($parameters[0], $parameters[1]);
				}
			}
			$this->success = $this->sQuery->execute();
		}
		catch( \PDOException $e ) 
		{
			if( $e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013 ) 
			{
				$this->closeConnection();
				$this->connect();
				try 
				{
					$this->sQuery = $this->pdo->prepare($query);
					$this->bindMore($parameters);
					if( !empty($this->parameters) ) 
					{
						foreach( $this->parameters as $param ) 
						{
							$parameters = explode("", $param);
							$this->sQuery->bindParam($parameters[0], $parameters[1]);
						}
					}
					$this->success = $this->sQuery->execute();
				}
				catch( \PDOException $ex ) 
				{
					$this->rollBackTrans();
					throw $ex;
				}
			}
			else 
			{
				$this->rollBackTrans();
				$msg = $e->getMessage();
				$err_msg = "SQL:" . $this->lastSQL() . " " . $msg;
				$exception = new \PDOException($err_msg, (int) $e->getCode());
				throw $exception;
			}
		}
		$this->parameters = array( );
	}
	public function bind($para, $value) 
	{
		if( is_string($para) ) 
		{
			$this->parameters[sizeof($this->parameters)] = ":" . $para . "" . $value;
		}
		else 
		{
			$this->parameters[sizeof($this->parameters)] = $para . "" . $value;
		}
	}
	public function bindMore($parray) 
	{
		if( empty($this->parameters) && is_array($parray) ) 
		{
			$columns = array_keys($parray);
			foreach( $columns as $i => &$column ) 
			{
				$this->bind($column, $parray[$column]);
			}
		}
	}
	public function query($query = "", $params = NULL, $fetchmode = \PDO::FETCH_ASSOC) 
	{
		$query = trim($query);
		if( empty($query) ) 
		{
			$query = $this->build();
			if( !$params ) 
			{
				$params = $this->getBindValues();
			}
		}
		$this->resetAll();
		$this->lastSql = $query;
		$this->execute($query, $params);
		$rawStatement = explode(" ", $query);
		$statement = strtolower(trim($rawStatement[0]));
		if( $statement === "select" || $statement === "show" ) 
		{
			return $this->sQuery->fetchAll($fetchmode);
		}
		if( $statement === "update" || $statement === "delete" ) 
		{
			return $this->sQuery->rowCount();
		}
		if( $statement === "insert" ) 
		{
			if( 0 < $this->sQuery->rowCount() ) 
			{
				return $this->lastInsertId();
			}
			return null;
		}
		return null;
	}
	public function column($query = "", $params = NULL) 
	{
		$query = trim($query);
		if( empty($query) ) 
		{
			$query = $this->build();
			if( !$params ) 
			{
				$params = $this->getBindValues();
			}
		}
		$this->resetAll();
		$this->lastSql = $query;
		$this->execute($query, $params);
		$columns = $this->sQuery->fetchAll(\PDO::FETCH_NUM);
		$column = null;
		foreach( $columns as $cells ) 
		{
			$column[] = $cells[0];
		}
		return $column;
	}
	public function row($query = "", $params = NULL, $fetchmode = \PDO::FETCH_ASSOC) 
	{
		$query = trim($query);
		if( empty($query) ) 
		{
			$query = $this->build();
			if( !$params ) 
			{
				$params = $this->getBindValues();
			}
		}
		$this->resetAll();
		$this->lastSql = $query;
		$this->execute($query, $params);
		return $this->sQuery->fetch($fetchmode);
	}
	public function single($query = "", $params = NULL) 
	{
		$query = trim($query);
		if( empty($query) ) 
		{
			$query = $this->build();
			if( !$params ) 
			{
				$params = $this->getBindValues();
			}
		}
		$this->resetAll();
		$this->lastSql = $query;
		$this->execute($query, $params);
		return $this->sQuery->fetchColumn();
	}
	public function lastInsertId() 
	{
		return $this->pdo->lastInsertId();
	}
	public function lastSQL() 
	{
		return $this->lastSql;
	}
	public function beginTrans() 
	{
		try 
		{
			if( is_null($this->pdo) ) 
			{
				$this->connect();
			}
			return $this->pdo->beginTransaction();
		}
		catch( \PDOException $e ) 
		{
			if( $e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013 ) 
			{
				$this->closeConnection();
				$this->connect();
				return $this->pdo->beginTransaction();
			}
			throw $e;
		}
	}
	public function commitTrans() 
	{
		return $this->pdo->commit();
	}
	public function rollBackTrans() 
	{
		if( $this->pdo->inTransaction() ) 
		{
			return $this->pdo->rollBack();
		}
		return true;
	}
}
?>