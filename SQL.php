<?php
	class SQL {
		private $connectionType;
		private $sql;
		private $connected;
		private $result;
		private $oracleSequencePattern;
		private $oracleAutoCommit
		
		public function __construct($type, $db = false, $user = 'root', $password = 'root', $host = 'localhost', $port = 'default') {
			$this->connected = false;
			$this->sql = false;
			$this->connectionType = false;
			$this->result = false;
			if($db !== false) {
				switch(strtolower($type)) {
					case 'mysql':
						if(function_exists('mysqli_connect')) {
							if($port == 'default')
								$port = 3306;
							$this->sql = new mysqli($host, $user, $password, $db, $port);
							if($this->sql)
								$this->connectionType = 'MySQL';
							else
								throw new Exception($this->sql->connect_error);
						} else
							throw new Exception('mysqli driver not installed');
						break;
					case 'postgresql':
						if(function_exists('pg_connect')) {
							if($port == 'default')
								$port = 5432;
							$connString = "host=$host port=$port dbname=$db user=$user password=$password options='--client_encoding=UTF8'";
							$this->sql = pg_connect($connString);
							if($this->sql)
								$this->connectionType = 'PostgreSQL';
							else
								throw new Exception("Couldn't connect to PostgreSQL Server. Connection String given: " . $connString);
						} else
							throw new Exception('postgresql driver not installed');
						break;
					case 'mssql':
						if(function_exists('sqlsrv_connect')) {
							if($port == 'default')
								$port = 1433;
							
							$serverName = $host . ', ' . $port;
							$connInfo = array(
								'Database' => $db,
								'UID' => $user,
								'PWD' => $password,
								'CharacterSet' => 'UTF-8'
							);	
							$this->sql = sqlsrv_connect($serverName, $connInfo);
							if($this->sql)
								$this->connectionType = 'MSSQL';
							else
								throw new Exception("Couldn't connect to MSSQL Server. Use 'print_r( sqlsrv_errors(), true)' for more information.");
						} else
							throw new Exception('mssql driver not installed');
						break;
					case 'oracle':
						if(function_exists('oci_connect')) {
							if($port == 'default')
								$port = 1521;
							$connString = "$host:$port/$db";
							$this->sql = oci_connect($user, $password, $connString);
							if($this->sql) {
								$this->connectionType = 'Oracle';
								$this->oracleSequencePattern = 'seq_{table}';
								$this->oracleAutoCommit = true;
							} else {
								$e = oci_error();
								throw new Exception($e['message']);
							}
						} else
							throw new Exception('oci driver not installed');
						break;
				}
			}
		}
		
		public function query($query = false) {
			if($query !== false) {
				switch($this->connectionType) {
					case 'MySQL':
						$this->result = $this->sql->query($query);
						return $this->result;
					case 'PostgreSQL':
						$this->result = pg_query($this->sql, $query);
						return $this->result;
					case 'MSSQL':
						$this->result = sqlsrv_query($this->sql, $query);
						return $this->result;
					case 'Oracle':
						$this->result = oci_parse($this->sql, $query);
						$exec = false;
						if($this->oracleAutoCommit)
							$exec = oci_execute($this->result);
						else
							$exec = oci_execute($this->result, OCI_NO_AUTO_COMMIT);
						
						if(!$exec)
							$this->result = false;
						return $this->result;
				}
			}
			return false;
		}
		
		public function freeResult() {
			if($this->result != false) {
				switch($this->connectionType) {
					case 'MySQL':
						$this->result->free();
						return true;
					case 'PostgreSQL':
						return pg_free_result($this->result);
					case 'MSSQL':
						return sqlsrv_free_stmt($this->result);
					case 'Oracle':
						return oci_free_statement($this->result);
				}
			}
			return false;
		}
		
		public function fetchAssoc() {
			if($this->result != false) {
				$data = array();
				switch($this->connectionType) {
					case 'MySQL':
						while($r = $this->result->fetch_assoc())
							$data[] = $r;
						return $data;
					case 'PostgreSQL':
						return pg_fetch_all($this->result);
					case 'MSSQL':
						while($r = sqlsrv_fetch_array($this->result, SQLSRV_FETCH_ASSOC))
							$data[] = $r;
						return $data;
					case 'Oracle':
						$numRows = oci_fetch_all($stid, $data, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
						return $data;
				}
			}
			return false;
		}
		
		public function fetchRow() {
			if($this->result != false) {
				$data = array();
				switch($this->connectionType) {
					case 'MySQL':
						return $this->result->fetch_row();
					case 'PostgreSQL':
						return pg_fetch_row($this->result);
					case 'MSSQL':
						return sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC);
					case 'Oracle':
						return oci_fetch_row($this->result);
				}
			}
			return false;
		}
		
		public function fetchArray() {
			if($this->result != false) {
				$data = array();
				switch($this->connectionType) {
					case 'MySQL':
						while($r = $this->result->fetch_array())
							$data[] = $r;
						return $data;
					case 'PostgreSQL':
						while($r = pg_fetch_array($this->result))
							$data[] = $r;
						return $data;
					case 'MSSQL':
						while($r = sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC))
							$data[] = $r;
						return $data;
					case 'Oracle':
						$numRows = oci_fetch_all($this->result, $data, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_NUM);
						return $data;
				}
			}
			return false;
		}
		
		public function close() {
			switch($this->connectionType) {
				case 'MySQL':
					return $this->sql->close();
				case 'PostgreSQL':
					return pg_close($this->sql);
				case 'MSSQL':
					return sqlsrv_close($this->sql);
				case 'Oracle':
					return oci_close($this->sql);
			}
		}
		
		private function addQuotes($fieldValue = false) {
			$fieldValue = array_map(function($value) {
				$value = trim($value, "'\"");
				if(is_numeric($value))
					return $value;
				else if(strtolower(substr($value, 0, 4)) == '{fn}')
					return str_ireplace('{fn}', '', $value);
				else
					return "'$value'";
			}, $fieldValue);
			return $fieldValue;
		}
		
		public function insert($table = false, $fieldValue = false) {
			if($table !== false && is_array($fieldValue)) {
				$query = "INSERT INTO $table (" . implode(', ', array_keys($fieldValue)) . ") VALUES (" . implode(', ', $this->addQuotes(array_values($fieldValue))) . ")";
				switch($this->connectionType) {
					case 'MySQL':
						$result = $this->query($query);
						if($result)
							return $this->sql->insert_id;
						break;
					case 'PostgreSQL':
						$query .= ' RETURNING *';
						$result = $this->query($query);
						if($result) {
							$row = pg_fetch_row($result);
							return $row[0];
						}
						break;
					case 'MSSQL':
						$query .= '; SELECT SCOPE_IDENTITY()';
						$result = $this->query($query);
						if(result) {
							sqlsrv_next_result($result); 
							sqlsrv_fetch($result); 
							return sqlsrv_get_field($result, 0);
						}
						break;
					case 'Oracle':
						$result = $this->query($query);
						if($result) {
							$query = 'SELECT ' . str_replace('{table}', $table, $this->oracleSequencePattern) . '.CURRVAL FROM dual';
							$result = $this->query($query);
							if($result) {
								$r = $this->fetchRow($result);
								return $r[0];
							}
							return true;
						}
						break;
				}
			}
			return false;
		}
		
		public function delete($table = false, $filter = false, $type = 'AND') {			
			if($table !== false && is_array($filter)) {
				$query = "DELETE FROM $table WHERE " . urldecode(http_build_query($this->addQuotes($filter), '', " $type "));
				return $this->query($query);
			}
			return false;
		}
		
		public function update($table = false, $fieldValue = false, $filter = false, $type = 'AND') {
			if($table !== false && is_array($fieldValue) && is_array($filter)) {
				$query = "UPDATE $table SET " . urldecode(http_build_query($this->addQuotes($filter), '', ', ')) . " WHERE " . urldecode(http_build_query($this->addQuotes($filter), '', " $type "));
				return $this->query($query);
			}
			return false;
		}
		
		public function truncate($table = false) {
			if($table !== false)
				return $this->query("TRUNCATE TABLE $table");
			return false;
		}
		
		public function getConnectionType() {
			return $this->connectionType;
		}
		
		public function isConnected() {
			return $this->connected;
		}
		
		public function transactionStart() {
			switch($this->connectionType) {
				case 'MySQL':
					return $this->sql->begin_transaction();
				case 'PostgreSQL':
					return pg_query($this->sql, 'BEGIN');
				case 'MSSQL':
					return sqlsrv_begin_transaction($this->sql);
				case 'Oracle':
					$this->oracleAutoCommit = false;
					return true;
			}
		}
		
		public function transactionCommit() {
			switch($this->connectionType) {
				case 'MySQL':
					return $this->sql->commit();
				case 'PostgreSQL':
					return pg_query($this->sql, 'COMMIT');
				case 'MSSQL':
					return sqlsrv_commit($this->sql);
				case 'Oracle':
					return oci_commit($this->sql);
			}
		}
		
		public function transactionRollback() {
			switch($this->connectionType) {
				case 'MySQL':
					return $this->sql->rollback();
				case 'PostgreSQL':
					return pg_query($this->sql, 'ROLLBACK');
				case 'MSSQL':
					return sqlsrv_rollback($this->sql);
				case 'Oracle':
					return oci_rollback($this->sql);
			}
		}
	}