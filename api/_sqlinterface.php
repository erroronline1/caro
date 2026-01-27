<?php
/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

class SQLQUERY {
	/**
	 * return query for driver
	 * @param string $context
	 * 
	 * @return string sql query
	 */
	public static function PREPARE($context){
		return self::QUERIES[$context][CONFIG['sql'][CONFIG['sql']['use']]['driver']];
	}

	/**
	 * execute a query  
	 * note: only fetchAll, so if you expect only one result make sure to handle $return[0]  
	 * 
	 * @param object $_pdo preset database connection, passed from main application
	 * @param string $query either defined within queries below or prepared raw queries
	 * @param array $parameters values => pdo execution passing tokens, strtr tokens e.g. for IN queries, wildcards bool|string - see SEARCH()
	 * 
	 * @return false|int|array sql result not executed|affectedRows|selection
	 */
	public static function EXECUTE($_pdo, $query = '', $parameters = ['values' => [], 'replacements' => [], 'wildcards' => false]){
		// retrive query matching sql driver, else process raw query
		if (isset(self::QUERIES[$query])) $query = self::QUERIES[$query][CONFIG['sql'][CONFIG['sql']['use']]['driver']];
		
		// substitute NULL values, sanitize values, apply self::SEARCH() on :SEARCH-key
		if (isset($parameters['values'])){
			foreach ($parameters['values'] as $key => $value){

				if (str_starts_with($query, 'SELECT') && $key === ':SEARCH') {
					$query = SELF::SEARCH($_pdo, $query, $value, ($parameters['wildcards'] ?? false));
					if (!$query) return [];
					unset($parameters['values'][$key]);
					continue;
				}

				if (gettype($value) === 'NULL' || $value === false) {
					$query = preg_replace('/(' . $key . ')(\W|$)/m', 'NULL$2', $query); // :id in :identity would be replaced with strtr too
					unset($parameters['values'][$key]);
				}
				else $parameters['values'][$key] = trim($value);
			}
		} else $parameters['values'] = [];
		// replace tokens in query that can not be executed
		if (isset($parameters['replacements'])) {
			foreach ($parameters['replacements'] as $key => $value){
				$list = [];
				if (json_decode($value, true) === null) $list = explode(',', $value); // can't explode csv if json
				if (count($list) > 1){ // handle lists
					foreach ($list as $index => $value2){
						if (strval(intval($value2)) === $value2) $list[$index] = intval($value2); // handle int
						else if (strval(floatval($value2)) === $value2) $parameters['replacements'][$key] = floatval($value2); // handle float
						elseif (gettype($value2) === 'string' && !in_array($value2, ['NULL', 'CURRENT_TIMESTAMP'])) $list[$index] = $_pdo->quote($value2); // handle string
					}
					$parameters['replacements'][$key] = implode(',', $list);
				}
				else if (strval(intval($value)) === $value) $parameters['replacements'][$key] = intval($value); // handle int
				else if (strval(floatval($value)) === $value) $parameters['replacements'][$key] = floatval($value); // handle float
				else if (in_array($key, [':field'])) $parameters['replacements'][$key] = $value; // some replacements involve column names that must not be quoted
				else if (gettype($value) === 'string' && !in_array($value, ['NULL', 'CURRENT_TIMESTAMP'])) $parameters['replacements'][$key] = $_pdo->quote($value); // handle string
			}
			$query = strtr($query, $parameters['replacements']);
		}

		// execute query
		try {
			$statement = $_pdo->prepare($query);
			if (!$statement->execute($parameters['values'])) return false;
		}
		catch (\Exception $e) {
			UTILITY::debug($e, $statement->queryString, $statement->debugDumpParams());
			die();
		}

		// prepare result response
		if (str_starts_with($query, 'SELECT')) {
			//UTILITY::debug($statement->debugDumpParams());
			$result = $statement->fetchAll();
		}
		elseif (str_starts_with($query, 'CREATE') || str_starts_with($query, 'IF OBJECT_ID(N')) $result = $statement->errorInfo(); // _databaseupdate.php table creation
		elseif (str_starts_with($query, 'ALTER TABLE') || str_starts_with($query, 'IF COL_LENGTH(')) $result = $statement->errorInfo(); // _databaseupdate.php column altering
		else $result = $statement->rowCount(); // affected rows

		$statement = null;
		return $result;
	}

	/**
	 * alters a query according to pattern of $value  
	 * to make use of that, column comparisons have to be encapsulated within parentheses each  
	 * SELECT FROM table WHERE (column LIKE :SEARCH) OR (column2 LIKE :SEARCH)
	 * 
	 * @param object $_pdo database connection, passed from main application
	 * @param string $query either defined within queries below or prepared raw queries
	 * @param string $value search string '"lorem ipsum" +dolor -sit amet
	 * @param bool|string $wildcards true for ? and *, all for replacement of [^\w\d], contain for only %...%
	 */
	private static function SEARCH($_pdo, $query, $value, $wildcards){
		preg_match_all('/( +(AND|OR) +)*\((([\w\d\.]+?) LIKE :SEARCH)\)/i', $query, $matchedcolumns, PREG_SET_ORDER);
		$columns = [];
		foreach($matchedcolumns as $column){
			$columns[] = [
				'match' => $column[0],
				'and_or' =>$column[2] ?? null,
				'replace' => $column[3],
				'column' => $column[4],
			];
		}	
		$expressions = SEARCH::expressions($value);
		if (!$expressions) return false;

		// delete column conditions as these are supposed to be postprocessed via SEARCH::refine() (_utility.php)
		foreach($expressions as $index => $expression){
			if (isset($expression['column']) && $expression['column']) unset($expressions[$index]);
		}

		foreach ($columns as $column){
			$concatenations = [];
			foreach ($expressions as $expression){
				switch(CONFIG['sql'][CONFIG['sql']['use']]['driver']){
					case 'mysql':
						if ($expression['operator'] === '+') $concatenations[] = 'AND IFNULL(LOWER(' . $column['column'] . "), '') LIKE LOWER(" . $_pdo->quote(self::WILDCARD($expression['term'], $wildcards)) . ')';
						elseif ($expression['operator'] === '-') $concatenations[] = 'AND IFNULL(LOWER(' . $column['column'] . "), '') NOT LIKE LOWER(" . $_pdo->quote(self::WILDCARD($expression['term'], $wildcards)) . ')';
						else $concatenations[] = 'OR IFNULL(LOWER(' . $column['column'] . "), '') LIKE LOWER(" . $_pdo->quote(self::WILDCARD($expression['term'], $wildcards)) . ')';
						break;
					case 'sqlsrv':
						if ($expression['operator'] === '+') $concatenations[] = 'AND ISNULL(LOWER(' . $column['column'] . "), '') LIKE LOWER(" . $_pdo->quote(self::WILDCARD($expression['term'], $wildcards)) . ')';
						elseif ($expression['operator'] === '-') $concatenations[] = 'AND ISNULL(LOWER(' . $column['column'] . "), '') NOT LIKE LOWER(" . $_pdo->quote(self::WILDCARD($expression['term'], $wildcards)) . ')';
						else $concatenations[] = 'OR ISNULL(LOWER(' . $column['column'] . "), '') LIKE LOWER(" . $_pdo->quote(self::WILDCARD($expression['term'], $wildcards)) . ')';
						break;
				}
				if ($expression['operator'] === '-' && count($expressions) < 2 && $column['and_or'] === 'OR')
					$query = str_replace($column['match'], str_replace(' OR ', ' AND ', $column['match']), $query);
			}
			$concatenation = implode(' ', $concatenations);
			$concatenation = substr($concatenation, strpos($concatenation, ' ')); // drop initial and/or

			$query = str_replace($column['replace'], $concatenation, $query);
		}
		return $query;
	}
	/**
	 * replaces characters to SQL wildcards
	 * @param string $value
	 * @param bool|string $type
	 * 
	 * @return string
	 */
	private static function WILDCARD($value, $type){
		if ($type === true) $value = '%' . preg_replace(['/\?/', '/\*/'], ['_', '%'], $value) . '%';
		elseif ($type === 'all') $value = '%' . preg_replace(['/\?/', '/\*/', '/[^\w\d%]/u'], ['_', '%', '_'], $value) . '%';
		elseif ($type === 'contained') $value = '%' . $value . '%';
		return $value;
	}

	/**
	 * creates packages of well prepared sql queries to handle sql package size
	 * 
	 * MASKING HAS TO BE DONE BEFOREHAND
	 * 
	 * @param object $_pdo preset database connection, passed from main application
	 * @param array $chunks packages so far
	 * @param string $query next sql query
	 * @return array $chunks extended packages so far
	 */
	public static function CHUNKIFY($chunks, $query = null){
		if ($query){
			$chunkIndex = count($chunks) - 1;
			if (isset($chunks[$chunkIndex])){
				if (strlen($chunks[$chunkIndex] . $query) < CONFIG['sql'][CONFIG['sql']['use']]['packagesize']) $chunks[$chunkIndex] .= $query;
				else $chunks[] = $query;
			}
			else $chunks[] = $query;
		}
		return $chunks;
	}

	/**
	 * creates packages of sql INSERTIONS to handle sql package size  
	 * e.g. for multiple inserts
	 * 
	 * @param object $_pdo preset database connection, passed from main application
	 * @param string $query sql query
	 * @param array $items named array to replace query by strtr have to be sanitized and masked
	 * @return array $chunks extended packages so far
	 * 
	 * this does make sense to only have to define one valid (and standalone as well) reusable dummy query
	 */
	public static function CHUNKIFY_INSERT($_pdo, $query = null, $items = null){
		$chunks = [];
		if ($query && $items){
			[$query, $values] = explode('VALUES', $query);
			$chunkeditems = [];
			foreach ($items as $item){
				foreach ($item as &$replace){
					if (gettype($replace) === 'NULL' || ($replace && strtoupper($replace) === 'NULL')) $replace = 'NULL';
					elseif ($replace === '') $replace = "''";
					elseif (gettype($replace) === 'string') $replace = $_pdo->quote($replace);
				}
				$item = strtr($values, $item);
				if (count($chunkeditems)){
					$index = count($chunkeditems) - 1;
					if (strlen($query . ' VALUES ' . implode(',', [$item, ...$chunkeditems[$index]])) < CONFIG['sql'][CONFIG['sql']['use']]['packagesize']){
						$chunkeditems[$index][] = $item;
					}
					else $chunkeditems[] = [$item];
				} else $chunkeditems[] = [$item];
			}
			foreach ($chunkeditems as $items){
				$chunks[] = $query . ' VALUES ' . implode(',', $items) . ';';
			}
		}
		return $chunks;
	}

	/**
	 * closes the passed connection
	 * 
	 * @param object $_pdo preset database connection, passed from main application
	 * @return none
	 */
	public static function CLOSE($_pdo){
		$_pdo = null;
	}

	/**
	 * 'context' => [
	 *  	'mysql' => "SELECT age FROM person ORDER BY age ASC LIMIT 3",
	 *  	'sqlsrv' => "SELECT TOP 3 WITH TIES * FROM person ORDER BY age ASC"
	 * ],
	 */
	public const QUERIES = [
		'DYNAMICDBSETUP' => [
			'mysql' => "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))",  // intuitive group by
			'sqlsrv' => ""
		],


		'announcement_post' => [
			'mysql' => "INSERT INTO caro_announcements (id, author_id, date, organizational_unit, span_start, span_end, subject, text, highlight) " .
						"VALUES (:id, :author_id, CURRENT_TIMESTAMP, :organizational_unit, :span_start, :span_end, :subject, :text, :highlight) " .
						"ON DUPLICATE KEY UPDATE author_id = :author_id, date = CURRENT_TIMESTAMP, organizational_unit = :organizational_unit, span_start = :span_start, span_end = :span_end, subject = :subject, text = :text, highlight = :highlight",
			'sqlsrv' => "MERGE INTO caro_announcements WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :author_id AS author_id, :date AS date, :organizational_unit AS organizational_unit, :span_start AS span_start, :span_end AS span_end, :subject AS subject, :text AS text, :highlight as highlight) AS source " .
						"(id, author_id, date, organizational_unit, span_start, span_end, subject, text, highlight) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET author_id = :author_id, date = CURRENT_TIMESTAMP, organizational_unit = :organizational_unit, span_start = CONVERT(SMALLDATETIME, :span_start, 120), span_end = CONVERT(SMALLDATETIME, :span_end, 120), subject = :subject, text = :text, highlight = :highlight " .
						"WHEN NOT MATCHED THEN INSERT (author_id, date, organizational_unit, span_start, span_end, subject, text, highlight) VALUES (:author_id, CURRENT_TIMESTAMP, :organizational_unit, CONVERT(SMALLDATETIME, :span_start, 120), CONVERT(SMALLDATETIME, :span_end, 120), :subject, :text, :highlight);"
		],
		'announcement_get_all' => [
			'mysql' => "SELECT caro_announcements.*, caro_user.name as author_name FROM caro_announcements LEFT JOIN caro_user ON caro_announcements.author_id = caro_user.id ORDER BY caro_announcements.span_start DESC",
			'sqlsrv' => "SELECT caro_announcements.*, caro_user.name as author_name FROM caro_announcements LEFT JOIN caro_user ON caro_announcements.author_id = caro_user.id ORDER BY caro_announcements.span_start DESC"
		],
		'announcement_get_recent' => [
			'mysql' => "SELECT caro_announcements.*, caro_user.name as author_name FROM caro_announcements LEFT JOIN caro_user ON caro_announcements.author_id = caro_user.id WHERE (caro_announcements.span_start IS NULL OR CURRENT_TIMESTAMP > caro_announcements.span_start) AND (caro_announcements.span_end IS NULL OR CURRENT_TIMESTAMP < caro_announcements.span_end) ORDER BY date DESC",
			'sqlsrv' => "SELECT caro_announcements.*, caro_user.name as author_name FROM caro_announcements LEFT JOIN caro_user ON caro_announcements.author_id = caro_user.id WHERE (caro_announcements.span_start IS NULL OR CURRENT_TIMESTAMP > caro_announcements.span_start) AND (caro_announcements.span_end IS NULL OR CURRENT_TIMESTAMP < caro_announcements.span_end) ORDER BY date DESC"
		],
		'announcement_delete' => [
			'mysql' => "DELETE FROM caro_announcements WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_announcements WHERE id = :id"
		],
		

		'audit_and_management_post' => [
			'mysql' => "INSERT INTO caro_audit_and_management (id, template, unit, content, last_touch, last_user, closed, notified) " .
						"VALUES (:id, :template, :unit, :content, CURRENT_TIMESTAMP, :last_user, :closed, NULL) " .
						"ON DUPLICATE KEY UPDATE content = :content, last_touch = CURRENT_TIMESTAMP, last_user = :last_user, closed = :closed",
			'sqlsrv' => "MERGE INTO caro_audit_and_management WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :template AS template, :unit AS unit, :content AS content, :last_touch AS last_touch, :last_user AS last_user, :closed AS closed, :notified AS notified) AS source " .
						"(id, template, unit, content, last_touch, last_user, closed, notified) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET content = :content, last_touch = CURRENT_TIMESTAMP, last_user = :last_user, closed = :closed " .
						"WHEN NOT MATCHED THEN INSERT (template, unit, content, last_touch, last_user, closed, notified) VALUES (:template, :unit, :content, CURRENT_TIMESTAMP, :last_user, :closed, NULL);"
		],
		'audit_and_management_notified' => [
			'mysql' => "UPDATE caro_audit_and_management SET notified = :notified WHERE id = :id",
			'sqlsrv' => "UPDATE caro_audit_and_management SET notified = :notified WHERE id = :id"
		],
		'audit_get' => [
			'mysql' => "SELECT * FROM caro_audit_and_management WHERE template IS NOT NULL ORDER BY last_touch DESC",
			'sqlsrv' => "SELECT * FROM caro_audit_and_management WHERE template IS NOT NULL ORDER BY last_touch DESC"
		],
		'management_get' => [
			'mysql' => "SELECT * FROM caro_audit_and_management WHERE template IS NULL ORDER BY last_touch DESC",
			'sqlsrv' => "SELECT * FROM caro_audit_and_management WHERE template IS NULL ORDER BY last_touch DESC"
		],
		'audit_and_management_get_by_id' => [
			'mysql' => "SELECT * FROM caro_audit_and_management WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_audit_and_management WHERE id = :id"
		],
		'audit_and_management_delete' => [
			'mysql' => "DELETE FROM caro_audit_and_management WHERE id = :id AND closed IS NULL",
			'sqlsrv' => "DELETE FROM caro_audit_and_management WHERE id = :id AND closed IS NULL"
		],
	
		
		'audit_post_template' => [
			'mysql' => "INSERT INTO caro_audit_templates (id, content, objectives, unit, date, author, hint, method) " .
						"VALUES (:id, :content, :objectives, :unit, CURRENT_TIMESTAMP, :author, :hint, :method) " .
						"ON DUPLICATE KEY UPDATE content = :content, objectives = :objectives, unit = :unit, hint = :hint, method = :method, date = CURRENT_TIMESTAMP, author = :author",
			'sqlsrv' => "MERGE INTO caro_audit_templates WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :content AS content, :objectives AS objectives, :unit AS unit, :date AS date, :author AS author, :hint AS hint, :method AS method) AS source " .
						"(id, content, objectives, unit, date, author, hint, method) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET content = :content, objectives = :objectives, unit = :unit, hint = :hint, method = :method, date = CURRENT_TIMESTAMP, author = :author " .
						"WHEN NOT MATCHED THEN INSERT (content, objectives, unit, date, author, hint, method) VALUES (:content, :objectives, :unit, CURRENT_TIMESTAMP, :author, :hint, :method);",
		],
		'audit_get_templates' => [
			'mysql' => "SELECT * FROM caro_audit_templates",
			'sqlsrv' => "SELECT * FROM caro_audit_templates"
		],
		'audit_get_template' => [
			'mysql' => "SELECT * FROM caro_audit_templates WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_audit_templates WHERE id = :id"
		],
		'audit_delete_template' => [
			'mysql' => "DELETE FROM caro_audit_templates WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_audit_templates WHERE id = :id"
		],



		'application_login' => [
			'mysql' => "SELECT * FROM caro_user WHERE token = :token LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_user WHERE token = :token"
		],
		'application_get_permission_group' => [
			'mysql' => "SELECT id FROM caro_user WHERE (permissions LIKE :group OR permissions LIKE CONCAT(:group, ',%') OR permissions LIKE CONCAT('%,', :group, ',%') OR permissions LIKE CONCAT('%,', :group))",
			'sqlsrv' => "SELECT id FROM caro_user WHERE (permissions LIKE :group OR permissions LIKE CONCAT(:group, ',%') OR permissions LIKE CONCAT('%,', :group, ',%') OR permissions LIKE CONCAT('%,', :group))"
		],
		'application_get_unit_group' => [
			'mysql' => "SELECT id FROM caro_user WHERE (units LIKE :group OR units LIKE CONCAT(:group, ',%') OR units LIKE CONCAT('%,', :group, ',%') OR units LIKE CONCAT('%,', :group))",
			'sqlsrv' => "SELECT id FROM caro_user WHERE (units LIKE :group OR units LIKE CONCAT(:group, ',%') OR units LIKE CONCAT('%,', :group, ',%') OR units LIKE CONCAT('%,', :group))"
		],


		'application_post_manual' => [
			'mysql' => "INSERT INTO caro_manual (id, title, content, permissions) " .
						"VALUES (:id, :title, :content, :permissions) " .
						"ON DUPLICATE KEY UPDATE title = :title, content = :content, permissions = :permissions",
			'sqlsrv' => "MERGE INTO caro_manual WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :title AS title, :content AS content, :permissions AS permissions) AS source " .
						"(id, title, content, permissions) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET title = :title, content = :content, permissions = :permissions " .
						"WHEN NOT MATCHED THEN INSERT (title, content, permissions) VALUES (:title, :content, :permissions);"
		],
		'application_get_manual' => [
			'mysql' => "SELECT * FROM caro_manual ORDER BY title",
			'sqlsrv' => "SELECT * FROM caro_manual ORDER BY title"
		],
		'application_get_manual_by_id' => [
			'mysql' => "SELECT * FROM caro_manual WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_manual WHERE id = :id"
		],
		'application_delete_manual' => [
			'mysql' => "DELETE FROM caro_manual WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_manual WHERE id = :id"
		],


		'application_post_session' => [
			'mysql' => "INSERT IGNORE INTO caro_sessions (id, user_id, date) VALUES (:id, :user_id, CURRENT_TIMESTAMP)",
			'sqlsrv' => "MERGE INTO caro_sessions WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :user_id AS user_id, CURRENT_TIMESTAMP AS date) AS source (id, user_id, date) ON (target.id = source.id ) " .
						"WHEN MATCHED THEN UPDATE SET date = CURRENT_TIMESTAMP " .
						"WHEN NOT MATCHED THEN INSERT (id, user_id, date) VALUES (:id, :user_id, CURRENT_TIMESTAMP);"
		],
		'application_get_session_fingerprint' => [
			'mysql' => "SELECT user_id, SHA2(CONCAT(id, user_id), 256) AS fingerprint FROM caro_sessions WHERE id = :id AND user_id = :user_id",
			'sqlsrv' => "SELECT user_id, LOWER(CONVERT(VARCHAR(100), HASHBYTES('SHA2_256', CONCAT( LOWER(id), LOWER(CONVERT(VARCHAR(100), user_id, 2)))), 2)) AS fingerprint FROM caro_sessions WHERE id = :id AND user_id = :user_id"
		],
		'application_get_user_from_fingerprint_checksum' => [
			'mysql' => "SELECT caro_user.* FROM caro_sessions LEFT JOIN caro_user ON caro_sessions.user_id = caro_user.id WHERE SHA2(CONCAT(SHA2(CONCAT(caro_sessions.id, caro_sessions.user_id), 256), :checksum), 256) = :hash",
			'sqlsrv' => "SELECT caro_user.* FROM caro_sessions LEFT JOIN caro_user ON caro_sessions.user_id = caro_user.id WHERE LOWER(CONVERT(VARCHAR(100), HASHBYTES('SHA2_256', CONCAT(LOWER(CONVERT(VARCHAR(100), HASHBYTES('SHA2_256', CONCAT( LOWER(caro_sessions.id), LOWER(CONVERT(VARCHAR(100), caro_sessions.user_id, 2)))), 2)), :checksum)), 2)) = :hash"
		],
		'application_delete_sessions' => [
			'mysql' => "DELETE FROM caro_sessions WHERE date < :date",
			'sqlsrv' => "DELETE FROM caro_sessions WHERE date < CONVERT(SMALLDATETIME, :date, 120)"
		],
		'application_get_user_sessions' => [
			'mysql' => "SELECT * FROM caro_sessions WHERE user_id = :user_id ORDER BY date DESC",
			'sqlsrv' => "SELECT * FROM caro_sessions WHERE user_id = :user_id ORDER BY date DESC"
		],



		'calendar_post' => [
			'mysql' => "INSERT INTO caro_calendar (id, type, span_start, span_end, author_id, affected_user_id, organizational_unit, subject, misc, closed, alert, autodelete) " .
						"VALUES (:id, :type, :span_start, :span_end, :author_id, :affected_user_id, :organizational_unit, :subject, :misc, :closed, :alert, :autodelete) " .
						"ON DUPLICATE KEY UPDATE span_start = :span_start, span_end = :span_end, author_id = :author_id, affected_user_id = :affected_user_id, organizational_unit = :organizational_unit, subject = :subject, misc = :misc, closed = :closed, alert = :alert, autodelete = :autodelete",
			'sqlsrv' => "MERGE INTO caro_calendar WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :type AS type, :span_start AS span_start, :span_end AS span_end, :author_id AS author_id, :affected_user_id AS affected_user_id, :organizational_unit AS organizational_unit, :subject AS subject, :misc AS misc, :closed AS closed, :alert AS alert, :autodelete AS autodelete) AS source " .
						"(id, type, span_start, span_end, author_id, affected_user_id, organizational_unit, subject, misc, closed, alert, autodelete) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET span_start = CONVERT(SMALLDATETIME, :span_start, 120), span_end = CONVERT(SMALLDATETIME, :span_end, 120), author_id = :author_id, affected_user_id = :affected_user_id, organizational_unit = :organizational_unit, subject = :subject, misc = :misc, closed = :closed, alert = :alert, autodelete = :autodelete " .
						"WHEN NOT MATCHED THEN INSERT (type, span_start, span_end, author_id, affected_user_id, organizational_unit, subject, misc, closed, alert, autodelete) VALUES (:type, CONVERT(SMALLDATETIME, :span_start, 120), CONVERT(SMALLDATETIME, :span_end, 120), :author_id, :affected_user_id, :organizational_unit, :subject, :misc, :closed, :alert, :autodelete);"
		],
		'calendar_complete' => [
			'mysql' => "UPDATE caro_calendar SET closed = :closed WHERE id = :id",
			'sqlsrv' => "UPDATE caro_calendar SET closed = :closed WHERE id = :id",
		],
		'calendar_get_by_id' => [
			'mysql' => "SELECT * FROM caro_calendar WHERE id IN (:id)",
			'sqlsrv' => "SELECT * FROM caro_calendar WHERE id IN (:id)",
		],
		'calendar_get_day' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE :date BETWEEN DATE_FORMAT(caro_calendar.span_start, '%Y-%m-%d') AND DATE_FORMAT(caro_calendar.span_end, '%Y-%m-%d') ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE :date BETWEEN FORMAT(caro_calendar.span_start, 'yyyy-MM-dd') AND FORMAT(caro_calendar.span_end, 'yyyy-MM-dd') ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_get_type' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.type = :type ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.type = :type ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_get_within_date_range' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.span_start BETWEEN :earlier AND :later OR caro_calendar.span_end BETWEEN :earlier AND :later ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.span_start BETWEEN CONVERT(SMALLDATETIME, :earlier, 120) AND CONVERT(SMALLDATETIME, :later, 120) OR caro_calendar.span_end BETWEEN CONVERT(SMALLDATETIME, :earlier, 120) AND CONVERT(SMALLDATETIME, :later, 120) ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_search' => [ // :SEARCH is a reserved keyword for application of self::SEARCH()
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE (caro_calendar.subject LIKE :SEARCH) OR (c_u2.name LIKE :SEARCH) ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE (caro_calendar.subject LIKE :SEARCH) OR (c_u2.name LIKE :SEARCH) ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_alert' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.alert = 1 AND caro_calendar.subject != '' AND caro_calendar.span_start <= CURRENT_TIMESTAMP; UPDATE caro_calendar SET alert = 0 WHERE alert = 1 AND span_start <= CURRENT_TIMESTAMP",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.alert = 1 AND caro_calendar.subject != '' AND caro_calendar.span_start <= CURRENT_TIMESTAMP; UPDATE caro_calendar SET alert = 0 WHERE alert = 1 AND span_start <= CURRENT_TIMESTAMP",
		],
		'calendar_delete' => [
			'mysql' => "DELETE FROM caro_calendar WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_calendar WHERE id = :id",
		],



		'consumables_post_vendor' => [
			'mysql' => "INSERT INTO caro_consumables_vendors (id, hidden, name, info, products, evaluation) " .
						"VALUES (:id, :hidden, :name, :info, :products, :evaluation) " .
						"ON DUPLICATE KEY UPDATE hidden = :hidden, name = :name, info = :info, products = :products, evaluation = :evaluation",
			'sqlsrv' => "MERGE INTO caro_consumables_vendors WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :hidden AS hidden, :name AS name, :info AS info, :products AS products, :evaluation AS evaluation) AS source " .
						"(id, hidden, name, info, products, evaluation) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET hidden = :hidden, name = :name, info = :info, products = :products, evaluation = :evaluation " .
						"WHEN NOT MATCHED THEN INSERT (hidden, name, info, products, evaluation) VALUES (:hidden, :name, :info, :products, :evaluation);"
		],
		'consumables_get_vendor_datalist' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors ORDER BY name ASC",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors ORDER BY name ASC"
		],
		'consumables_get_vendor' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors WHERE id = :id OR name = :id",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors WHERE CONVERT(VARCHAR, id) = :id OR name = :id"
		],
		'consumables_get_vendor_search' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors WHERE (name LIKE :SEARCH)",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors WHERE (name LIKE :SEARCH)"
		],


		// on duplicate key update / merging does not make sense for products, for queries are dis- and reassembled for improved performance
		// also micro-updates
		'consumables_post_product' => [
			'mysql' => "INSERT INTO caro_consumables_products (id, vendor_id, article_no, article_name, article_alias, article_unit, article_ean, article_info, hidden, has_files, trading_good, checked, sample_checks, incorporated, has_expiry_date, special_attention, last_order, stock_item, erp_id, document_reminder, thirdparty_order) VALUES (NULL, :vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :article_info, :hidden, :has_files, :trading_good, NULL, NULL, :incorporated, :has_expiry_date, :special_attention, :last_order, :stock_item, :erp_id, NULL, :thirdparty_order)",
			'sqlsrv' => "INSERT INTO caro_consumables_products (vendor_id, article_no, article_name, article_alias, article_unit, article_ean, article_info, hidden, has_files, trading_good, checked, sample_checks, incorporated, has_expiry_date, special_attention, last_order, stock_item, erp_id, document_reminder, thirdparty_order) VALUES (:vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :article_info, :hidden, :has_files, :trading_good, NULL, NULL, :incorporated, :has_expiry_date, :special_attention, CONVERT(SMALLDATETIME, :last_order, 120), :stock_item, :erp_id, NULL, :thirdparty_order)"
		],
		'consumables_put_product' => [
			'mysql' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, article_info = :article_info, hidden = :hidden, has_files = :has_files, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention, stock_item = :stock_item, erp_id = :erp_id, last_order = :last_order, thirdparty_order = :thirdparty_order WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, article_info = :article_info, hidden = :hidden, has_files = :has_files, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention, stock_item = :stock_item, erp_id = :erp_id, last_order = CONVERT(SMALLDATETIME, :last_order, 120), thirdparty_order = :thirdparty_order WHERE id = :id"
		],
		'consumables_put_product_productlist_import' => [
			'mysql' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention, stock_item = :stock_item, erp_id = :erp_id, last_order = :last_order WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention, stock_item = :stock_item, erp_id = :erp_id, last_order = CONVERT(SMALLDATETIME, :last_order, 120) WHERE id = :id"
		],
		'consumables_put_product_productlist_erp_amendment' => [
			'mysql' => "UPDATE caro_consumables_products SET article_unit = IF(article_unit IS NULL, :article_unit, article_unit), article_ean = IF(article_ean IS NULL, :article_ean, article_ean), trading_good = IF(trading_good IS NULL, :trading_good, trading_good), incorporated = IF(incorporated IS NULL, :incorporated, incorporated), has_expiry_date = IF(has_expiry_date IS  NULL, :has_expiry_date, has_expiry_date), special_attention = IF(special_attention IS NULL, :special_attention, special_attention), stock_item = :stock_item, erp_id = :erp_id, last_order = IF(last_order IS NULL, :last_order, last_order), thirdparty_order = :thirdparty_order WHERE vendor_id = :vendor_id AND article_no = :article_no",
			'sqlsrv' => "UPDATE caro_consumables_products SET article_unit = (CASE WHEN article_unit IS NULL THEN :article_unit ELSE article_unit END), article_ean = (CASE WHEN article_ean IS NULL THEN :article_ean ELSE article_ean END), trading_good = (CASE WHEN trading_good IS NULL THEN :trading_good ELSE trading_good END), incorporated = (CASE WHEN incorporated IS NULL THEN :incorporated ELSE incorporated END), has_expiry_date = (CASE WHEN has_expiry_date IS NULL THEN :has_expiry_date ELSE has_expiry_date END), special_attention = (CASE WHEN special_attention IS NULL THEN :special_attention ELSE special_attention END), stock_item = :stock_item, erp_id = :erp_id, last_order = (CASE WHEN last_order IS NULL THEN CONVERT(SMALLDATETIME, :last_order, 120) ELSE last_order END), thirdparty_order = :thirdparty_order WHERE vendor_id = :vendor_id AND article_no = :article_no"
		],
		'consumables_put_batch' => [ // preprocess via strtr
			'mysql' => "UPDATE caro_consumables_products SET :field = :value WHERE id IN (:ids)",
			'sqlsrv' => "UPDATE caro_consumables_products SET :field = :value WHERE id IN (:ids)"
		],
		'consumables_put_sample_check' => [
			'mysql' => "UPDATE caro_consumables_products SET checked = :checked, sample_checks = :sample_checks WHERE id IN (:ids) AND trading_good IS NOT NULL AND trading_good != 0",
			'sqlsrv' => "UPDATE caro_consumables_products SET checked = :checked, sample_checks = :sample_checks WHERE id IN (:ids) AND trading_good IS NOT NULL AND trading_good != 0"
		],
		'consumables_put_incorporation' => [
			'mysql' => "UPDATE caro_consumables_products SET incorporated = :incorporated WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET incorporated = :incorporated WHERE id = :id"
		],
		'consumables_put_last_order' => [
			'mysql' => "UPDATE caro_consumables_products SET last_order = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET last_order = CURRENT_TIMESTAMP WHERE id  = :id"
		],
		'consumables_put_last_document_evaluation' => [
			'mysql' => "UPDATE caro_consumables_products SET document_reminder = :notified WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET document_reminder = :notified WHERE id  = :id"
		],
		'consumables_get_product' => [
			'mysql' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE caro_consumables_products.id IN (:ids)",
			'sqlsrv' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE CONVERT(VARCHAR, caro_consumables_products.id) IN (:ids)"
		],
		'consumables_get_products' => [
			'mysql' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id",
			'sqlsrv' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id"
		],
		'consumables_get_product_search' => [ // :SEARCH is a reserved keyword for application of self::SEARCH()
			'mysql' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE ((caro_consumables_products.article_no LIKE :SEARCH) OR (caro_consumables_products.article_name LIKE :SEARCH) OR (caro_consumables_products.article_alias LIKE :SEARCH) OR caro_consumables_products.article_ean = :search OR caro_consumables_products.erp_id = :search) AND caro_consumables_vendors.name LIKE :vendor",
			'sqlsrv' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE ((caro_consumables_products.article_no LIKE :SEARCH) OR (caro_consumables_products.article_name LIKE :SEARCH) OR (caro_consumables_products.article_alias LIKE :SEARCH) OR caro_consumables_products.article_ean = :search OR caro_consumables_products.erp_id = :search) AND caro_consumables_vendors.name LIKE :vendor"
		],
		'consumables_get_product_by_article_no_vendor' => [
			'mysql' => "SELECT caro_consumables_products.id, caro_consumables_products.last_order FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE caro_consumables_products.article_no LIKE :article_no AND caro_consumables_vendors.name LIKE :vendor",
			'sqlsrv' => "SELECT caro_consumables_products.id, caro_consumables_products.last_order FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE caro_consumables_products.article_no LIKE :article_no AND caro_consumables_vendors.name LIKE :vendor"
		],
		'consumables_get_last_checked' => [
			'mysql' => "SELECT caro_consumables_products.checked as checked, caro_consumables_vendors.id as vendor_id FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE caro_consumables_products.trading_good = 1 "
				. "AND caro_consumables_products.checked IS NOT NULL ORDER BY caro_consumables_products.checked DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP(1) caro_consumables_products.checked as checked, caro_consumables_vendors.id as vendor_id FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE caro_consumables_products.trading_good = 1 "
				. "AND caro_consumables_products.checked IS NOT NULL ORDER BY caro_consumables_products.checked"
		],
		'consumables_get_product_units' => [
			'mysql' => "SELECT article_unit FROM caro_consumables_products WHERE article_unit IS NOT NULL GROUP BY article_unit ORDER BY article_unit ASC",
			'sqlsrv' => "SELECT article_unit FROM caro_consumables_products WHERE article_unit IS NOT NULL GROUP BY article_unit ORDER BY article_unit ASC"
		],
		'consumables_get_products_by_vendor_id' => [
			'mysql' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE caro_consumables_vendors.id IN (:ids)",
			'sqlsrv' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE CONVERT(VARCHAR, caro_consumables_vendors.id) IN (:ids)"
		],
		'consumables_delete_all_unprotected_products' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE vendor_id = :id AND (article_alias IS NULL OR article_alias = '') AND checked IS NULL AND (incorporated IS NULL OR incorporated = '') AND (has_files IS NULL OR has_files = 0) AND last_order IS NULL",
			'sqlsrv' => "DELETE FROM caro_consumables_products WHERE vendor_id = :id AND (article_alias IS NULL OR article_alias = '') AND checked IS NULL AND (incorporated IS NULL OR incorporated = '') AND (has_files IS NULL OR has_files = 0) AND last_order IS NULL"
		],
		'consumables_delete_unprotected_product' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE id = :id AND (article_alias IS NULL OR article_alias = '') AND checked IS NULL AND (incorporated IS NULL OR incorporated = '') AND has_files IS NULL",
			'sqlsrv' => "DELETE FROM caro_consumables_products WHERE id = :id AND (article_alias IS NULL OR article_alias = '') AND checked IS NULL AND (incorporated IS NULL OR incorporated = '') AND has_files IS NULL"
		],



		'csvfilter_post' => [
			'mysql' => "INSERT INTO caro_csvfilter (id, name, date, author, content, hidden, approval) " .
						"VALUES (:id, :name, CURRENT_TIMESTAMP, :author, :content, :hidden, :approval) " .
						"ON DUPLICATE KEY UPDATE name = :name, author = :author, content = :content, hidden = :hidden, approval = :approval",
			'sqlsrv' => "MERGE INTO caro_csvfilter WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :name AS name, :content AS content) AS source " .
						"(id, name, content) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET name = :name, author = :author, content = :content, hidden = :hidden, approval = :approval " .
						"WHEN NOT MATCHED THEN INSERT (name, date, author, content, hidden, approval) VALUES (:name, CURRENT_TIMESTAMP, :author, :content, :hidden, :approval);"
		],
		'csvfilter_put_approve' => [
			'mysql' => "UPDATE caro_csvfilter SET approval = :approval WHERE id = :id",
			'sqlsrv' => "UPDATE caro_csvfilter SET approval = :approval WHERE id = :id"
		],
		'csvfilter_datalist' => [
			'mysql' => "SELECT * FROM caro_csvfilter ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_csvfilter name ORDER BY name ASC, date DESC"
		],
		'csvfilter_get_filter' => [
			'mysql' => "SELECT * FROM caro_csvfilter WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_csvfilter WHERE id = :id"
		],
		'csvfilter_get_latest_by_name' => [
			'mysql' => "SELECT * FROM caro_csvfilter WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_csvfilter WHERE name = :name ORDER BY id DESC"
		],
		'csvfilter_delete' => [
			'mysql' => "DELETE FROM caro_csvfilter WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_csvfilter WHERE id = :id"
		],



		'file_external_documents_get' => [
			'mysql' => "SELECT * FROM caro_file_external_documents ORDER BY path ASC",
			'sqlsrv' => "SELECT * FROM caro_file_external_documents ORDER BY path ASC"
		],
		'file_external_documents_get_active' => [
			'mysql' => "SELECT * FROM caro_file_external_documents WHERE activated IS NOT NULL AND retired IS NULL ORDER BY path ASC",
			'sqlsrv' => "SELECT * FROM caro_file_external_documents WHERE activated IS NOT NULL AND retired IS NULL ORDER BY path ASC"
		],
		'file_external_documents_retire' => [
			'mysql' => "UPDATE caro_file_external_documents SET author = :author, retired = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_file_external_documents SET author = :author, retired = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'file_external_documents_unretire' => [
			'mysql' => "UPDATE caro_file_external_documents SET author = :author, activated = CURRENT_TIMESTAMP, retired = NULL WHERE id = :id",
			'sqlsrv' => "UPDATE caro_file_external_documents SET author = :author, activated = CURRENT_TIMESTAMP, retired = NULL WHERE id = :id"
		],
		'file_external_documents_context' => [
			'mysql' => "UPDATE caro_file_external_documents SET regulatory_context = :regulatory_context WHERE id = :id",
			'sqlsrv' => "UPDATE caro_file_external_documents SET regulatory_context = :regulatory_context WHERE id = :id"
		],
		'file_external_documents_post' => [
			'mysql' => "INSERT INTO caro_file_external_documents (id, path, author, regulatory_context, activated, retired) VALUES (NULL, :path, :author, '', NULL, NULL)",
			'sqlsrv' => "INSERT INTO caro_file_external_documents (path, author, regulatory_context, activated, retired) VALUES (:path, :author, '', NULL, NULL)"
		],



		'document_post' => [
			'mysql' => "INSERT INTO caro_documents (id, name, alias, context, unit, date, author, content, hidden, approval, regulatory_context, permitted_export, restricted_access, patient_access) " .
						"VALUES (:id, :name, :alias, :context, :unit, CURRENT_TIMESTAMP, :author, :content, NULL, '', :regulatory_context, :permitted_export, :restricted_access, :patient_access) " .
						"ON DUPLICATE KEY UPDATE alias = :alias, context = :context, unit = :unit, author = :author, content = :content, hidden = :hidden, approval = :approval, regulatory_context = :regulatory_context, permitted_export = :permitted_export, restricted_access = :restricted_access, patient_access = :patient_access",
			'sqlsrv' => "MERGE INTO caro_documents WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :name AS name, :alias AS alias, :context AS context, :unit AS unit, :author AS author, :content AS content, :hidden AS hidden, :approval AS approval, :regulatory_context AS regulatory_context, :permitted_export AS permitted_export, :restricted_access AS restricted_access, :patient_access AS patient_access) AS source " .
						"(id, name, alias, context, unit, author, content, hidden, approval, regulatory_context, permitted_export, restricted_access, patient_access) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET alias = :alias, context = :context, unit = :unit, author = :author, content = :content, hidden = :hidden, approval = :approval, regulatory_context = :regulatory_context, permitted_export = :permitted_export, restricted_access = :restricted_access, patient_access = :patient_access " .
						"WHEN NOT MATCHED THEN INSERT (name, alias, context, unit, date, author, content, hidden, approval, regulatory_context, permitted_export, restricted_access, patient_access) VALUES (:name, :alias, :context, :unit, CURRENT_TIMESTAMP, :author, :content, NULL, '', :regulatory_context, :permitted_export, :restricted_access, :patient_access);"
		],
		'document_put_approve' => [
			'mysql' => "UPDATE caro_documents SET approval = :approval WHERE id = :id",
			'sqlsrv' => "UPDATE caro_documents SET approval = :approval WHERE id = :id"
		],
		'document_document_datalist' => [
			'mysql' => "SELECT * FROM caro_documents WHERE context NOT IN ('component', 'bundle') ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE context NOT IN ('component', 'bundle') ORDER BY name ASC, date DESC"
		],
		'document_component_datalist' => [
			'mysql' => "SELECT * FROM caro_documents WHERE context = 'component' ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE context = 'component' ORDER BY name ASC, date DESC"
		],
		'document_bundle_datalist' => [
			'mysql' => "SELECT * FROM caro_documents WHERE context = 'bundle' ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE context = 'bundle' ORDER BY name ASC, date DESC"
		],
		'document_document_get_by_name' => [
			'mysql' => "SELECT * FROM caro_documents WHERE name = :name AND context NOT IN ('component', 'bundle') ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE name = :name AND context NOT IN ('component', 'bundle') ORDER BY id DESC"
		],
		'document_document_get_by_context' => [
			'mysql' => "SELECT * FROM caro_documents WHERE context = :context ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE context = :context ORDER BY id DESC"
		],
		'document_component_get_by_name' => [
			'mysql' => "SELECT * FROM caro_documents WHERE name = :name AND context = 'component' ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE name = :name AND context = 'component' ORDER BY id DESC"
		],
		'document_bundle_get_by_name' => [
			'mysql' => "SELECT * FROM caro_documents WHERE name = :name AND context = 'bundle' ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE name = :name AND context = 'bundle' ORDER BY id DESC"
		],
		'document_get' => [
			'mysql' => "SELECT * FROM caro_documents WHERE id = :id ",
			'sqlsrv' => "SELECT * FROM caro_documents WHERE id = :id"
		],
		'document_delete' => [
			'mysql' => "DELETE FROM caro_documents WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_documents WHERE id = :id"
		],



		// on duplicate key update / merging does not make sense for measures, for put and post are processed different
		'measure_post' => [
			'mysql' => "INSERT INTO caro_measures (id, timestamp, content, user_id, votes, measures, last_user, last_touch, closed) VALUES (NULL, CURRENT_TIMESTAMP, :content, :user_id, NULL, NULL, NULL, NULL, NULL)",
			'sqlsrv' => "INSERT INTO caro_measures (timestamp, content, user_id, votes, measures, last_user, last_touch, closed) VALUES (CURRENT_TIMESTAMP, :content, :user_id, NULL, NULL, NULL, NULL, NULL)"
		],
		'measure_put' => [
			'mysql' => "UPDATE caro_measures SET measures = :measures, closed = :closed, last_user = :last_user, last_touch = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_measures SET measures = :measures, closed = :closed, last_user = :last_user, last_touch = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'measure_vote' => [
			'mysql' => "UPDATE caro_measures SET votes = :votes WHERE id = :id",
			'sqlsrv' => "UPDATE caro_measures SET votes = :votes WHERE id = :id"
		],
		'measure_get' => [
			'mysql' => "SELECT caro_measures.*, caro_user.name AS user_name FROM caro_measures LEFT JOIN caro_user ON caro_measures.user_id = caro_user.id ORDER BY closed, timestamp DESC",
			'sqlsrv' => "SELECT caro_measures.*, caro_user.name AS user_name FROM caro_measures LEFT JOIN caro_user ON caro_measures.user_id = caro_user.id ORDER BY closed, timestamp DESC"
		],
		'measure_get_by_id' => [
			'mysql' => "SELECT caro_measures.*, caro_user.name AS user_name FROM caro_measures LEFT JOIN caro_user ON caro_measures.user_id = caro_user.id WHERE caro_measures.id = :id",
			'sqlsrv' => "SELECT caro_measures.*, caro_user.name AS user_name FROM caro_measures LEFT JOIN caro_user ON caro_measures.user_id = caro_user.id WHERE caro_measures.id = :id"
		],
		'measure_delete' => [
			'mysql' => "DELETE FROM caro_measures WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_measures WHERE id = :id"
		],



		'message_post_message' => [
			'mysql' => "INSERT INTO caro_messages (id, user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (NULL, :from_user, :to_user, :from_user, :message, CURRENT_TIMESTAMP, 1, 1), (NULL, :to_user, :from_user, :from_user, :message, CURRENT_TIMESTAMP, 0, 0)",
			'sqlsrv' => "INSERT INTO caro_messages (user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (:from_user, :to_user, :from_user, :message, CURRENT_TIMESTAMP, 1, 1), (:to_user, :from_user, :from_user, :message, CURRENT_TIMESTAMP, 0, 0)"
		],
		'message_post_system_message' => [
			'mysql' => "INSERT INTO caro_messages (id, user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (NULL, :to_user, 1, 1, :message, CURRENT_TIMESTAMP, 0, 0)",
			'sqlsrv' => "INSERT INTO caro_messages (user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (:to_user, 1, 1, :message, CURRENT_TIMESTAMP, 0, 0)"
		],
		'message_put_notified' => [
			'mysql' => "UPDATE caro_messages SET notified = 1 WHERE user_id = :user",
			'sqlsrv' => "UPDATE caro_messages SET notified = 1 WHERE user_id = :user"
		],		
		'message_get_unnotified' => [
			'mysql' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND notified = 0",
			'sqlsrv' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND notified = 0"
		],
		'message_get_unseen' => [
			'mysql' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND seen = 0",
			'sqlsrv' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND seen = 0"
		],
		'message_get_conversations' => [
			'mysql' => "SELECT caro_messages.*, caro_user.name as conversation_user_name, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.conversation_user = caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.id IN (SELECT MAX(caro_messages.id) FROM caro_messages WHERE caro_messages.user_id = :user GROUP BY caro_messages.conversation_user) ORDER BY caro_messages.timestamp DESC",
			'sqlsrv' => "SELECT caro_messages.*, caro_user.name as conversation_user_name, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.conversation_user = caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.id IN (SELECT MAX(caro_messages.id) FROM caro_messages WHERE caro_messages.user_id = :user GROUP BY caro_messages.conversation_user) ORDER BY caro_messages.timestamp DESC"
		],
		'message_get_unseen_conversations' => [
			'mysql' => "SELECT COUNT(id) as unseen FROM caro_messages WHERE user_id = :user AND seen = 0 AND conversation_user = :conversation",
			'sqlsrv' => "SELECT COUNT(id) as unseen FROM caro_messages WHERE user_id = :user AND seen = 0 AND conversation_user = :conversation"
		],
		'message_get_conversation' => [
			'mysql' => "SELECT caro_messages.*, caro_user.name as conversation_user_name, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.sender = caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.conversation_user = :conversation ORDER BY caro_messages.timestamp ASC; UPDATE caro_messages SET notified = 1, seen = 1 WHERE user_id = :user AND conversation_user = :conversation",
			'sqlsrv' => "SELECT caro_messages.*, caro_user.name as conversation_user_name, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.sender = caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.conversation_user = :conversation ORDER BY caro_messages.timestamp ASC; UPDATE caro_messages SET notified = 1, seen = 1 WHERE user_id = :user AND conversation_user = :conversation"
		],
		'message_get_messages_prior_date' => [
			'mysql' => "SELECT id FROM caro_messages WHERE user_id = :user AND timestamp < :timestamp",
			'sqlsrv' => "SELECT id FROM caro_messages WHERE user_id = :user AND timestamp < CONVERT(SMALLDATETIME, :timestamp, 120)"
		],
		'message_delete_messages' => [
			'mysql' => "DELETE FROM caro_messages WHERE user_id = :user AND id in (:ids)",
			'sqlsrv' => "DELETE FROM caro_messages WHERE user_id = :user AND id in (:ids)"
		],



		'order_get_product_search' => [ // :SEARCH is a reserved keyword for application of self::SEARCH()
			'mysql' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE ((caro_consumables_products.article_no LIKE :SEARCH) OR (caro_consumables_products.article_name LIKE :SEARCH) OR (caro_consumables_products.article_alias LIKE :SEARCH) OR caro_consumables_products.article_ean = :search OR caro_consumables_products.erp_id = :search) AND caro_consumables_vendors.name LIKE :vendor AND caro_consumables_vendors.hidden IS NULL AND caro_consumables_products.hidden IS NULL",
			'sqlsrv' => "SELECT caro_consumables_products.*, caro_consumables_vendors.name as vendor_name FROM caro_consumables_products LEFT JOIN caro_consumables_vendors ON caro_consumables_products.vendor_id = caro_consumables_vendors.id WHERE ((caro_consumables_products.article_no LIKE :SEARCH) OR (caro_consumables_products.article_name LIKE :SEARCH) OR (caro_consumables_products.article_alias LIKE :SEARCH) OR caro_consumables_products.article_ean = :search OR caro_consumables_products.erp_id = :search) AND caro_consumables_vendors.name LIKE :vendor AND caro_consumables_vendors.hidden IS NULL AND caro_consumables_products.hidden IS NULL"
		],

		'order_post_prepared_order' => [
			'mysql' => "INSERT INTO caro_consumables_prepared_orders (id, order_data) " .
						"VALUES (:id, :order_data) " .
						"ON DUPLICATE KEY UPDATE order_data = :order_data",
			'sqlsrv' => "MERGE INTO caro_consumables_prepared_orders WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :order_data AS order_data) AS source " .
						"(id, order_data) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET order_data = :order_data " .
						"WHEN NOT MATCHED THEN INSERT (order_data) VALUES (:order_data);"
		],
		'order_get_prepared_order' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_consumables_prepared_orders WHERE CONVERT(VARCHAR, id) = :id"
		],
		'order_get_prepared_orders' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders",
			'sqlsrv' => "SELECT * FROM caro_consumables_prepared_orders"
		],
		'order_delete_prepared_orders' => [
			'mysql' => "DELETE FROM caro_consumables_prepared_orders WHERE id IN (:id)",
			'sqlsrv' => "DELETE FROM caro_consumables_prepared_orders WHERE id IN (:id)"
		],

		// on duplicate key update / merging does not make sense for approved orders, for entries are micro-updated anyway
		'order_post_approved_order' => [
			'mysql' => "INSERT INTO caro_consumables_approved_orders (id, order_data, organizational_unit, approval, approved, ordered, delivered_partially, delivered_full, issued_partially, issued_full, archived, ordertype, delivered_notified, issued_notified) VALUES (NULL, :order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL, NULL, NULL, NULL, :ordertype, NULL, NULL)",
			'sqlsrv' => "INSERT INTO caro_consumables_approved_orders (order_data, organizational_unit, approval, approved, ordered, delivered_partially, delivered_full, issued_partially, issued_full, archived, ordertype, delivered_notified, issued_notified) VALUES (:order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL, NULL, NULL, NULL, :ordertype, NULL, NULL)"
		],
		'order_put_approved_order_state' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET :field = :date WHERE id IN (:id)",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET :field = CONVERT(SMALLDATETIME, :date, 120) WHERE id IN (:id)"
		],
		'order_put_approved_order_addinformation' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data WHERE id = :id"
		],
		'order_put_approved_order_cancellation' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data, ordered = NULL, delivered_partially = NULL, delivered_full = NULL, issued_partially = NULL, issued_full = NULL, archived = NULL, ordertype = 'cancellation' WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data, ordered = NULL, delivered_partially = NULL, delivered_full = NULL, issued_partially = NULL, issued_full = NULL, archived = NULL, ordertype = 'cancellation' WHERE id = :id"
		],
		'order_notified' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET delivered_notified = :delivered_notified, issued_notified = :issued_notified WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET delivered_notified = :delivered_notified, issued_notified = :issued_notified WHERE id = :id"
		],
		'order_get_approved_order_by_ids' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE id IN (:ids)",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE id IN (:ids)"
		],
		'order_get_approved_order_by_issued' => [ // preselection for safe deletion
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE issued_full < :date_time AND archived IS NULL",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE issued_full < CONVERT(SMALLDATETIME, :date_time, 120) AND archived IS NULL"
		],		
		'order_get_approved_order_by_substr' => [ // CASE SENSITIVE JUST TO BE SURE, compares order data to detect reused attachments in case of deletion
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE order_data LIKE CONCAT('%', :substr, '%')",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE order_data LIKE CONCAT('%', :substr, '%')"
		],
		'order_get_appoved_oldest_approval' =>[
			'mysql' => "SELECT approved FROM caro_consumables_approved_orders WHERE ordered IS NULL OR delivered_full IS NULL ORDER BY approved ASC LIMIT 1",
			'sqlsrv' => "SELECT TOP(1) approved FROM caro_consumables_approved_orders WHERE ordered IS NULL OR delivered_full IS NULL ORDER BY approved ASC"
		],
		'order_get_approved_search' => [ // :SEARCH is a reserved keyword for application of self::SEARCH()
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE (organizational_unit IN (:organizational_unit) OR order_data LIKE '%orderer\":\":user\"%') AND (order_data LIKE :SEARCH)",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE (organizational_unit IN (:organizational_unit) OR order_data LIKE '%orderer\":\":user\"%') AND (order_data LIKE :SEARCH)"
		],
		'order_get_approved_unprocessed_alert' => [ // for notifications
			'mysql' => "SELECT count(id) AS num FROM caro_consumables_approved_orders WHERE ordered > :timestamp",
			'sqlsrv' => "SELECT count(id) AS num FROM caro_consumables_approved_orders WHERE ordered > CONVERT(SMALLDATETIME, :timestamp, 120)"
		],
		'order_get_approved_unprocessed' => [ // for notifications
			'mysql' => "SELECT count(id) AS num FROM caro_consumables_approved_orders WHERE ordered IS NULL",
			'sqlsrv' => "SELECT count(id) AS num FROM caro_consumables_approved_orders WHERE ordered IS NULL",
		],
		'order_get_approved_undelivered_unissued' => [ // for system message reminders
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE ordered IS NOT NULL AND (delivered_full IS NULL OR issued_full IS NULL)",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE ordered IS NOT NULL AND (delivered_full IS NULL OR issued_full IS NULL)",
		],
		'order_get_approved_archived' => [ // for system message reminders
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE archived IS NOT NULL",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE archived IS NOT NULL",
		],
		'order_delete_approved_order' => [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id"
		],



		// kudos https://stackoverflow.com/a/30660857/6087758
		'order_post_order_statistics' => [
			'mysql' => "INSERT INTO caro_consumables_order_statistics (order_id, order_data, organizational_unit, orderer_name, approved, ordered, delivered_partially, delivered_full, ordertype) " .
						"VALUES (:order_id, :order_data, :organizational_unit, :orderer_name, :approved, :ordered, :delivered_partially, :delivered_full, :ordertype) " .
						"ON DUPLICATE KEY UPDATE order_data = :order_data, delivered_partially = :delivered_partially, delivered_full = :delivered_full, ordertype = :ordertype",
			'sqlsrv' => "MERGE INTO caro_consumables_order_statistics WITH (HOLDLOCK) AS target USING " .
						"(SELECT :order_id AS order_id, :order_data AS order_data, :organizational_unit as organizational_unit, :orderer_name as orderer_name, CONVERT(SMALLDATETIME, :approved, 120) AS approved, CONVERT(SMALLDATETIME, :ordered, 120) AS ordered, CONVERT(SMALLDATETIME, :delivered_partially, 120) AS delivered_partially, CONVERT(SMALLDATETIME, :delivered_full, 120) AS delivered_full, :ordertype AS ordertype) AS source " .
						"(order_id, order_data, organizational_unit, orderer_name, approved, ordered, delivered_partially, delivered_full, ordertype) ON (target.order_id = source.order_id) " .
						"WHEN MATCHED THEN UPDATE SET order_data = :order_data, delivered_partially = CONVERT(SMALLDATETIME, :delivered_partially, 120), delivered_full = CONVERT(SMALLDATETIME, :delivered_full, 120), ordertype = :ordertype " .
						"WHEN NOT MATCHED THEN INSERT (order_id, order_data, organizational_unit, orderer_name, approved, ordered, delivered_partially, delivered_full, ordertype) VALUES (:order_id, :order_data, :organizational_unit, :orderer_name, CONVERT(SMALLDATETIME, :approved, 120), CONVERT(SMALLDATETIME, :ordered, 120), CONVERT(SMALLDATETIME, :delivered_partially, 120), CONVERT(SMALLDATETIME, :delivered_full, 120), :ordertype);"
													// ^^^^^^^^ insert id here as opposed to other merged queries because most other tables have id as auto increment, but this is primary only	
		],
		'order_get_order_statistics' => [
			'mysql' => "SELECT * FROM caro_consumables_order_statistics WHERE ordered BETWEEN :start AND :end ORDER BY order_id",
			'sqlsrv' => "SELECT * FROM caro_consumables_order_statistics WHERE ordered BETWEEN CONVERT(SMALLDATETIME, :start, 120) AND CONVERT(SMALLDATETIME, :end, 120) ORDER BY order_id"
		],
		'order_delete_order_statistics' => [
			'mysql' => "DELETE FROM caro_consumables_order_statistics WHERE order_id = :order_id",
			'sqlsrv' => "DELETE FROM caro_consumables_order_statistics WHERE order_id = :order_id"
		],
		'order_truncate_order_statistics' => [
			'mysql' => "DELETE FROM caro_consumables_order_statistics WHERE ordered < :datetime",
			'sqlsrv' => "DELETE FROM caro_consumables_order_statistics WHERE ordered < CONVERT(SMALLDATETIME, :datetime, 120)"
		],



		'records_post' => [
			'mysql' => "INSERT INTO caro_records (id, context, case_state, record_type, identifier, last_user, last_touch, last_document, content, closed, notified, lifespan, erp_case_number, note, restricted_access) " .
						"VALUES (:id, :context, NULL, :record_type, :identifier, :last_user, CURRENT_TIMESTAMP, :last_document, :content, NULL, NULL, NULL, NULL, NULL, :restricted_access) " .
						"ON DUPLICATE KEY UPDATE case_state = :case_state, record_type = :record_type, identifier = :identifier, last_user = :last_user, last_touch = CURRENT_TIMESTAMP, last_document = :last_document, content = :content, closed = NULL, lifespan = :lifespan, erp_case_number = :erp_case_number, note = :note, restricted_access = :restricted_access",
			'sqlsrv' => "MERGE INTO caro_records WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :context AS context, :case_state AS case_state, :record_type AS record_type, :identifier AS identifier, :last_user AS last_user, :last_document AS last_document, :content AS content, :lifespan AS lifespan, :erp_case_number AS erp_case_number, :note AS note, :restricted_access AS restricted_access) AS source " .
						"(id, context, case_state, record_type, identifier, last_user, last_document, content, lifespan, erp_case_number, note, restricted_access) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET case_state = :case_state, record_type = :record_type, identifier = :identifier, last_user = :last_user, last_touch = CURRENT_TIMESTAMP, last_document = :last_document, content = :content, closed = NULL, lifespan = :lifespan, erp_case_number = :erp_case_number, note = :note, restricted_access = :restricted_access " .
						"WHEN NOT MATCHED THEN INSERT (context, case_state, record_type, identifier, last_user, last_touch, last_document, content, closed, notified, lifespan, erp_case_number, note, restricted_access) VALUES (:context, NULL, :record_type, :identifier, :last_user, CURRENT_TIMESTAMP, :last_document, :content, NULL, NULL, NULL, NULL, NULL, :restricted_access);"
		],
		'records_close' => [
			'mysql' => "UPDATE caro_records SET closed = :closed WHERE identifier = :identifier",
			'sqlsrv' => "UPDATE caro_records SET closed = :closed WHERE identifier = :identifier"
		],
		'records_notified' => [
			'mysql' => "UPDATE caro_records SET notified = :notified WHERE identifier = :identifier",
			'sqlsrv' => "UPDATE caro_records SET notified = :notified WHERE identifier = :identifier"
		],
		'records_get_all' => [
			'mysql' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id",
			'sqlsrv' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id"
		],
		'records_get_unclosed' => [
			'mysql' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id WHERE closed IS NULL",
			'sqlsrv' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id WHERE closed IS NULL"
		],
		'records_get_identifier' => [
			'mysql' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id WHERE caro_records.identifier = :identifier",
			'sqlsrv' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id WHERE caro_records.identifier = :identifier"
		],
		'records_search' => [ // :SEARCH is a reserved keyword for application of self::SEARCH()
			'mysql' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id WHERE (caro_records.identifier LIKE :SEARCH) OR (caro_records.note LIKE :SEARCH) OR caro_records.content LIKE :search OR caro_records.erp_case_number LIKE :search",
			'sqlsrv' => "SELECT caro_records.*, caro_user.units FROM caro_records LEFT JOIN caro_user ON caro_records.last_user = caro_user.id WHERE (caro_records.identifier LIKE :SEARCH) OR (caro_records.note LIKE :SEARCH) OR caro_records.content LIKE :search OR caro_records.erp_case_number LIKE :search"
		],
		'records_delete' => [
			'mysql' => "DELETE FROM caro_records WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_records WHERE id = :id"
		],



		// on duplicate key update / merging does not make sense for datalists, for comparison does not occur based on key but rather other column values
		'records_datalist_post' => [
			'mysql' => "INSERT INTO caro_records_datalist (id, issue, unit, datalist) VALUES (NULL, :issue, :unit, :datalist)",
			'sqlsrv' => "INSERT INTO caro_records_datalist (issue, unit, datalist) VALUES (:issue, :unit, :datalist)"
		],
		'records_datalist_put' => [
			'mysql' => "UPDATE caro_records_datalist SET datalist = :datalist WHERE issue = :issue AND unit = :unit",
			'sqlsrv' => "UPDATE caro_records_datalist SET datalist = :datalist WHERE issue = :issue AND unit = :unit"
		],
		'records_datalist_get' => [
			'mysql' => "SELECT * FROM caro_records_datalist WHERE unit = :unit",
			'sqlsrv' => "SELECT * FROM caro_records_datalist WHERE unit = :unit"
		],
		'records_datalist_delete' => [ // maintenance update
			'mysql' => "DELETE FROM caro_records_datalist WHERE unit = :unit",
			'sqlsrv' => "DELETE FROM caro_records_datalist WHERE unit = :unit"
		],
		


		'risk_post' => [
			'mysql' => "INSERT INTO caro_risks (id, type, process, risk, relevance, cause, effect, probability, damage, measure, measure_probability, measure_damage, risk_benefit, measure_remainder, proof, date, author) " .
						"VALUES (:id, :type, :process, :risk, :relevance, :cause, :effect, :probability, :damage, :measure, :measure_probability, :measure_damage, :risk_benefit, :measure_remainder, :proof, CURRENT_TIMESTAMP, :author) " .
						"ON DUPLICATE KEY UPDATE risk = :risk, probability = :probability, damage = :damage, measure_probability = :measure_probability, measure_damage = :measure_damage, proof = :proof, hidden = :hidden",
			'sqlsrv' => "MERGE INTO caro_risks WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :type AS type, :process AS process, :risk AS risk, :relevance AS relevance, :cause AS cause, :effect AS effect, :probability AS probability, :damage AS damage, :measure AS measure, :measure_probability AS measure_probability, :measure_damage AS measure_damage, :risk_benefit AS risk_benefit, :measure_remainder AS measure_remainder, :proof AS proof, :date AS date, :author AS author) AS source " .
						"(id, type, process, risk, relevance, cause, effect, probability, damage, measure, measure_probability, measure_damage, risk_benefit, measure_remainder, proof, date, author) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET risk = :risk, probability = :probability, damage = :damage, measure_probability = :measure_probability, measure_damage = :measure_damage, proof = :proof, hidden = :hidden " .
						"WHEN NOT MATCHED THEN INSERT (type, process, risk, relevance, cause, effect, probability, damage, measure, measure_probability, measure_damage, risk_benefit, measure_remainder, proof, date, author) VALUES (:type, :process, :risk, :relevance, :cause, :effect, :probability, :damage, :measure, :measure_probability, :measure_damage, :risk_benefit, :measure_remainder, :proof, CURRENT_TIMESTAMP, :author);"
		],
		'risk_datalist' => [
			'mysql' => "SELECT * FROM caro_risks ORDER BY process, risk, cause, effect",
			'sqlsrv' => "SELECT * FROM caro_risks ORDER BY process, risk, cause, effect"
		],
		'risk_search' => [ // :SEARCH is a reserved keyword for application of self::SEARCH()
			'mysql' => "SELECT * FROM caro_risks WHERE (cause LIKE :SEARCH) OR (effect LIKE :SEARCH) OR (measure LIKE :SEARCH) OR (risk_benefit LIKE :SEARCH) OR (measure_remainder LIKE :SEARCH) ORDER BY process, risk, cause, effect",
			'sqlsrv' => "SELECT * FROM caro_risks WHERE (cause LIKE :SEARCH) OR (effect LIKE :SEARCH) OR (measure LIKE :SEARCH) OR (risk_benefit LIKE :SEARCH) OR (measure_remainder LIKE :SEARCH) ORDER BY process, risk, cause, effect"
		],
		'risk_get' => [
			'mysql' => "SELECT * FROM caro_risks WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_risks WHERE id = :id"
		],



		'texttemplate_post' => [
			'mysql' => "INSERT INTO caro_texttemplates (id, name, unit, date, author, content, type, hidden, approval, linked_files) " .
						"VALUES (:id, :name, :unit, CURRENT_TIMESTAMP, :author, :content, :type, :hidden, :approval, :linked_files) " .
						"ON DUPLICATE KEY UPDATE name = :name, unit = :unit, author = :author, content = :content, hidden = :hidden, approval = :approval, linked_files = :linked_files",
			'sqlsrv' => "MERGE INTO caro_texttemplates WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :name AS name, :content AS content, :type as type) AS source " .
						"(id, name, content, type) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET name = :name, unit = :unit, author = :author, content = :content, hidden = :hidden, approval = :approval, linked_files = :linked_files " .
						"WHEN NOT MATCHED THEN INSERT (name, unit, date, author, content, type, hidden, approval, linked_files) VALUES (name, :unit, CURRENT_TIMESTAMP, :author, :content, :type, :hidden, :approval, :linked_files);"
		],
		'texttemplate_put_approve' => [
			'mysql' => "UPDATE caro_texttemplates SET approval = :approval WHERE id = :id",
			'sqlsrv' => "UPDATE caro_texttemplates SET approval = :approval WHERE id = :id"
		],
		'texttemplate_datalist' => [
			'mysql' => "SELECT * FROM caro_texttemplates ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_texttemplates name ORDER BY name ASC, date DESC"
		],
		'texttemplate_get_chunk' => [
			'mysql' => "SELECT * FROM caro_texttemplates WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_texttemplates WHERE id = :id"
		],
		'texttemplate_get_latest_by_name' => [
			'mysql' => "SELECT * FROM caro_texttemplates WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_texttemplates WHERE name= :name ORDER BY id DESC"
		],
		'texttemplate_delete' => [
			'mysql' => "DELETE FROM caro_texttemplates WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_texttemplates WHERE id = :id"
		],



		'user_post' => [
			'mysql' => "INSERT INTO caro_user (id, name, permissions, units, token, orderauth, image, app_settings, skills) " .
						"VALUES (:id, :name, :permissions, :units, :token, :orderauth, :image, :app_settings, :skills) " .
						"ON DUPLICATE KEY UPDATE name = :name, permissions = :permissions, units = :units, token = :token, orderauth = :orderauth, image = :image, app_settings = :app_settings, skills = :skills",
			'sqlsrv' => "MERGE INTO caro_user WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :name AS name, :permissions AS permissions, :units AS units, :token AS token, :orderauth AS orderauth, :image AS image, :app_settings AS app_settings, :skills AS skills) AS source " .
						"(id, name, permissions, units, token, orderauth, image, app_settings, skills) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET name = :name, permissions = :permissions, units = :units, token = :token, orderauth = :orderauth, image = :image, app_settings = :app_settings, skills = :skills " .
						"WHEN NOT MATCHED THEN INSERT (name, permissions, units, token, orderauth, image, app_settings, skills) VALUES (:name, :permissions, :units, :token, :orderauth, :image, :app_settings, :skills);",
		],
		'user_get_datalist' => [
			'mysql' => "SELECT id, name, orderauth, permissions, units, image, app_settings, skills FROM caro_user ORDER BY name ASC",
			'sqlsrv' => "SELECT id, name, orderauth, permissions, units, image, app_settings, skills FROM caro_user ORDER BY name ASC"
		],
		'user_get' => [
			'mysql' => "SELECT * FROM caro_user WHERE id IN (:id) OR name IN (:name)",
			'sqlsrv' => "SELECT * FROM caro_user WHERE CONVERT(VARCHAR, id) IN (:id) OR name IN (:name)"
		],
		'user_get_orderauth' => [
			'mysql' => "SELECT * FROM caro_user WHERE orderauth = :orderauth",
			'sqlsrv' => "SELECT * FROM caro_user WHERE orderauth = :orderauth"
		],
		'user_delete' => [
			'mysql' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id; DELETE FROM caro_calendar WHERE affected_user_id = :id; DELETE FROM caro_user_training WHERE user_id = :id",
			'sqlsrv' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id; DELETE FROM caro_calendar WHERE affected_user_id = :id; DELETE FROM caro_user_training WHERE user_id = :id"
		],



		'user_responsibility_post' => [
			'mysql' => "INSERT INTO caro_user_responsibility (id, user_id, units, assigned_users, proxy_users, span_start, span_end, responsibility, description) " .
						"VALUES (:id, :user_id, :units, :assigned_users, :proxy_users, :span_start, :span_end, :responsibility, :description) " .
						"ON DUPLICATE KEY UPDATE user_id = :user_id, units = :units, assigned_users = :assigned_users, proxy_users = :proxy_users, span_start = :span_start, span_end = :span_end, responsibility = :responsibility, description = :description",
			'sqlsrv' => "MERGE INTO caro_user_responsibility WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :user_id AS user_id, :units AS units, :assigned_users AS assigned_users, :proxy_users AS proxy_users, :span_start AS span_start, :span_end AS span_end, :responsibility AS responsibility, :description AS description) AS source " .
						"(id, user_id, units, assigned_users, proxy_users, span_start, span_end, responsibility, description) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET user_id = :user_id, units = :units, assigned_users = :assigned_users, proxy_users = :proxy_users, span_start =  CONVERT(DATE, :span_start, 23),  span_end = CONVERT(DATE, :span_end, 23), responsibility = :responsibility, description = :description " .
						"WHEN NOT MATCHED THEN INSERT (user_id, units, assigned_users, proxy_users, span_start, span_end, responsibility, description) VALUES (:user_id, :units, :assigned_users, :proxy_users, CONVERT(DATE, :span_start, 23), CONVERT(DATE, :span_end, 23), :responsibility, :description);",
		],
		'user_responsibility_accept' => [
			'mysql' => "UPDATE caro_user_responsibility SET assigned_users = :assigned_users, proxy_users = :proxy_users WHERE id = :id",
			'sqlsrv' => "UPDATE caro_user_responsibility SET assigned_users = :assigned_users, proxy_users = :proxy_users WHERE id = :id"
		],
		'user_responsibility_get' => [
			'mysql' => "SELECT * FROM caro_user_responsibility WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_user_responsibility WHERE id = :id"
		],
		'user_responsibility_get_all' => [
			'mysql' => "SELECT * FROM caro_user_responsibility ORDER BY span_start DESC",
			'sqlsrv' => "SELECT * FROM caro_user_responsibility ORDER BY span_start DESC"
		],
		'user_responsibility_delete' => [
			'mysql' => "DELETE FROM caro_user_responsibility WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_user_responsibility WHERE id = :id"
		],



		'user_training_post' => [
			'mysql' => "INSERT INTO caro_user_training (id, user_id, name, date, expires, experience_points, file_path, evaluation, planned) " .
						"VALUES (:id, :user_id, :name, :date, :expires, :experience_points, :file_path, :evaluation, :planned) " .
						"ON DUPLICATE KEY UPDATE name = :name, date = :date, expires = :expires, experience_points = :experience_points, file_path = :file_path, evaluation = :evaluation, planned = :planned",
			'sqlsrv' => "MERGE INTO caro_user_training WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :user_id AS user_id, :name AS name, :date AS date, :expires AS expires, :experience_points AS experience_points, :file_path AS file_path, :evaluation AS evaluation, :planned AS planned) AS source " .
						"(id, user_id, name, date, expires, experience_points, file_path, evaluation, planned) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET name = :name, date = CONVERT(DATE, :date, 23), expires = CONVERT(DATE, :expires, 23), experience_points = :experience_points, file_path = :file_path, evaluation = :evaluation, planned = :planned " .
						"WHEN NOT MATCHED THEN INSERT (user_id, name, date, expires, experience_points, file_path, evaluation, planned) VALUES (:user_id, :name, CONVERT(DATE, :date, 23), CONVERT(DATE, :expires, 23), :experience_points, :file_path, :evaluation, :planned);",
		],
		'user_training_get' => [
			'mysql' => "SELECT caro_user_training.*, caro_user.name AS user_name FROM caro_user_training LEFT JOIN caro_user ON caro_user_training.user_id = caro_user.id WHERE caro_user_training.id = :id",
			'sqlsrv' => "SELECT caro_user_training.*, caro_user.name AS user_name FROM caro_user_training LEFT JOIN caro_user ON caro_user_training.user_id = caro_user.id WHERE caro_user_training.id = :id"
		],
		'user_training_get_user' => [
			'mysql' => "SELECT * FROM caro_user_training WHERE user_id IN (:ids) ORDER BY date, id, user_id ASC",
			'sqlsrv' => "SELECT * FROM caro_user_training WHERE user_id IN (:ids) ORDER BY date, id, user_id ASC"
		],
		'user_training_delete' => [
			'mysql' => "DELETE FROM caro_user_training WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_user_training WHERE id = :id"
		],



		'whiteboard_post' => [
			'mysql' => "INSERT INTO caro_whiteboard (id, user_id, name, last_touch, organizational_unit, content) " .
						"VALUES (:id, :user_id, :name, CURRENT_TIMESTAMP, :organizational_unit, :content) " .
						"ON DUPLICATE KEY UPDATE name = :name, last_touch = CURRENT_TIMESTAMP, organizational_unit = :organizational_unit, content = :content ",
			'sqlsrv' => "MERGE INTO caro_whiteboard WITH (HOLDLOCK) AS target USING " .
						"(SELECT :id AS id, :user_id AS user_id, :name AS name, CURRENT_TIMESTAMP AS last_touch, :organizational_unit AS organizational_unit, :content AS content) AS source " .
						"(id, user_id, name, last_touch, organizational_unit, content) ON (target.id = source.id) " .
						"WHEN MATCHED THEN UPDATE SET name = :name, last_touch = CURRENT_TIMESTAMP, organizational_unit = :organizational_unit, content = :content " .
						"WHEN NOT MATCHED THEN INSERT (user_id, name, last_touch, organizational_unit, content) VALUES (:user_id, :name, CURRENT_TIMESTAMP, :organizational_unit, :content);",
		],
		'whiteboard_get_all' => [
			'mysql' => "SELECT caro_whiteboard.*, caro_user.name as user_name FROM caro_whiteboard LEFT JOIN caro_user ON caro_whiteboard.user_id = caro_user.id",
			'sqlsrv' => "SELECT caro_whiteboard.*, caro_user.name as user_name FROM caro_whiteboard LEFT JOIN caro_user ON caro_whiteboard.user_id = caro_user.id"
		],
		'whiteboard_get' => [
			'mysql' => "SELECT * FROM caro_whiteboard WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_whiteboard WHERE id = :id"
		],
		'whiteboard_delete' => [
			'mysql' => "DELETE FROM caro_whiteboard WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_whiteboard WHERE id = :id"
		],
	];
}
?>