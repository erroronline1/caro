<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

class SQLQUERY {
	/**
	 * return query for driver
	 * @param string $context
	 * 
	 * @return string sql query
	 */
	public static function PREPARE($context){
		return self::QUERIES[$context][INI['sql'][INI['sql']['use']]['driver']];
	}

	/**
	 * execute a query
	 * note: only fetchAll, so if you expect only one result make sure to handle $return[0]
	 * 
	 * REPLACEMENTS ARE PROCESED RAW
	 * MASKING HAS TO BE DONE BEFOREHAND
	 * 
	 * @param object $_pdo preset database connection, passed from main application
	 * @param string $query either defined within queries below or prepared chunkified
	 * @param array $parameters values => pdo execution passing tokens, strtr tokens e.g. for IN queries
	 * 
	 * @return false|int|array sql result not executed|affectedRows|selection
	 */
	public static function EXECUTE($_pdo, $query = '', $parameters = ['values' => [], 'replacements' => []]){
		// retrive query matching sql driver, else process raw query
		if (array_key_exists($query, self::QUERIES)) $query = self::QUERIES[$query][INI['sql'][INI['sql']['use']]['driver']];
		
		// substitute NULL values, int values and mask/sanitize values
		if (array_key_exists('values', $parameters) && $parameters['values']){
			foreach ($parameters['values'] as $key => $value){
				if ($value === null || $value === false) {
					$query = strtr($query, [$key => 'NULL']);
					unset($parameters['values'][$key]);
				}
				/*else if (strval(intval($value)) === $value) {
					$query = strtr($query, [$key => intval($value)]);
					unset($parameters['values'][$key]);
				}
				else if (strval(floatval($value)) === $value) {
					$query = strtr($query, [$key => floatval($value)]);
					unset($parameters['values'][$key]);
				}*/
				else $parameters['values'][$key] = trim($value);
			}
		} else $parameters['values'] = [];

		// replace tokens in query that can not be executed
		if (array_key_exists('replacements', $parameters) && $parameters['replacements']) {
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
		$statement = $_pdo->prepare($query);

		//var_dump($query, $parameters);
		//$statement->execute($parameters['values']);
		//var_dump($statement->debugDumpParams());
		if (!$statement->execute($parameters['values'])) return false;
		if (str_starts_with($query, 'SELECT')) {
			//var_dump($statement->debugDumpParams());
			return $statement->fetchAll();
		}
		return $statement->rowCount();
	}

	/**
	 * creates packages of well prepared sql queries to handle sql package size
	 * @param array $chunks packages so far
	 * @param string $query next sql query
	 * @return array $chunks extended packages so far
	 */
	public static function CHUNKIFY($chunks, $query = null){
		if ($query){
			$chunkIndex = count($chunks) - 1;
			if (array_key_exists($chunkIndex, $chunks)){
				if (strlen($chunks[$chunkIndex] . $query) < INI['sql'][INI['sql']['use']]['packagesize']) $chunks[$chunkIndex] .= $query;
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
	 * MASKING HAS TO BE DONE BEFOREHAND
	 * 
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
			foreach($items as $item){
				foreach ($item as &$replace){
					if ($replace === '') $replace = "''";
					elseif (gettype($replace) === 'string') $replace = $_pdo->quote($replace);
				}
				$item = strtr($values, $item);
				if (count($chunkeditems)){
					$index = count($chunkeditems) - 1;
					if (strlen($query . ' VALUES ' . implode(',', [$item, ...$chunkeditems[$index]])) < INI['sql'][INI['sql']['use']]['packagesize']){
						$chunkeditems[$index][] = $item;
					}
					else $chunkeditems[] = [$item];
				} else $chunkeditems[] = [$item];
			}
			foreach($chunkeditems as $items){
				$chunks[] = $query . ' VALUES ' . implode(',', $items) . ';';
			}
		}
		return $chunks;
	}

	/**
	 * 'context' => [
	 *  	'mysql' => "SELECT age FROM person ORDER BY age ASC LIMIT 3",
	 *  	'sqlsrv' => "SELECT TOP 3 WITH TIES * FROM person ORDER BY age ASC"
	 * ],
	 */
	public const QUERIES = [
		'DYNAMICDBSETUP' => [
			'mysql' => "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));",  // intuitive group by
			'sqlsrv' => ""
		],

		'application_login' => [
			'mysql' => "SELECT * FROM caro_user WHERE token = :token LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_user WHERE token = :token"
		],
		'application_get_permission_group' => [
			'mysql' => "SELECT id FROM caro_user WHERE permissions LIKE CONCAT('%', :group, '%')",
			'sqlsrv' => "SELECT id FROM caro_user WHERE permissions LIKE CONCAT('%', :group, '%')"
		],
		'application_get_unit_group' => [
			'mysql' => "SELECT id FROM caro_user WHERE units LIKE CONCAT('%', :group, '%')",
			'sqlsrv' => "SELECT id FROM caro_user WHERE units LIKE CONCAT('%', :group, '%')"
		],
		'application_post_manual' => [
			'mysql' => "INSERT INTO caro_manual (id, title, content, permissions) VALUES (NULL, :title, :content, :permissions)",
			'sqlsrv' => "INSERT INTO caro_manual (title, content, permissions) VALUES (:title, :content, :permissions)"
		],
		'application_put_manual' => [
			'mysql' => "UPDATE caro_manual SET title = :title, content = :content, permissions = :permissions WHERE id = :id",
			'sqlsrv' => "UPDATE caro_manual SET title = :title, content = :content, permissions = :permissions WHERE id = :id"
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



		'calendar_post' => [
			'mysql' => "INSERT INTO caro_calendar (id, type, span_start, span_end, author_id, affected_user_id, organizational_unit, subject, misc, closed, alert) VALUES (NULL, :type, :span_start, :span_end, :author_id, :affected_user_id, :organizational_unit, :subject, :misc, :closed, :alert)",
			'sqlsrv' => "INSERT INTO caro_calendar (type, span_start, span_end, author_id, affected_user_id, organizational_unit, subject, misc, closed, alert) VALUES (:type, CONVERT(SMALLDATETIME, :span_start, 120), CONVERT(SMALLDATETIME, :span_end, 120), :author_id, :affected_user_id, :organizational_unit, :subject, :misc, :closed, :alert)",
		],
		'calendar_put' => [
			'mysql' => "UPDATE caro_calendar SET span_start = :span_start, span_end = :span_end, author_id = :author_id, affected_user_id = :affected_user_id, organizational_unit = :organizational_unit, subject = :subject, misc = :misc, closed = :closed, alert = :alert WHERE id = :id",
			'sqlsrv' => "UPDATE caro_calendar SET span_start = CONVERT(SMALLDATETIME, :span_start, 120), span_end = CONVERT(SMALLDATETIME, :span_end, 120), author_id = :author_id, affected_user_id = :affected_user_id, organizational_unit = :organizational_unit, subject = :subject, misc = :misc, closed = :closed alert = :alert WHERE id = :id",
		],
		'calendar_get_by_id' => [
			'mysql' => "SELECT * FROM caro_calendar WHERE id IN (:id)",
			'sqlsrv' => "SELECT * FROM caro_calendar WHERE id IN (:id)",
		],
		'calendar_complete' => [
			'mysql' => "UPDATE caro_calendar SET closed = :closed WHERE id = :id",
			'sqlsrv' => "UPDATE caro_calendar SET closed = :closed WHERE id = :id",
		],
		'calendar_get_day' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE :date BETWEEN DATE_FORMAT(caro_calendar.span_start, '%Y-%m-%d') AND DATE_FORMAT(caro_calendar.span_end, '%Y-%m-%d') ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE :date BETWEEN FORMAT(caro_calendar.span_start, 'yyyy-MM-dd') AND FORMAT(caro_calendar.span_end, 'yyyy-MM-dd') ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_get_within_date_range' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.span_start BETWEEN :earlier AND :later OR caro_calendar.span_end BETWEEN :earlier AND :later ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.span_start BETWEEN CONVERT(SMALLDATETIME, :earlier, 120) AND CONVERT(SMALLDATETIME, :later, 120) OR caro_calendar.span_end BETWEEN CONVERT(SMALLDATETIME, :earlier, 120) AND CONVERT(SMALLDATETIME, :later, 120) ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_search' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE LOWER(caro_calendar.subject) LIKE LOWER(CONCAT('%', :subject, '%')) ORDER BY caro_calendar.span_end ASC",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE LOWER(caro_calendar.subject) LIKE LOWER(CONCAT('%', :subject, '%')) ORDER BY caro_calendar.span_end ASC",
		],
		'calendar_delete' => [
			'mysql' => "DELETE FROM caro_calendar WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_calendar WHERE id = :id",
		],
		'calendar_alert' => [
			'mysql' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.alert = 1 AND caro_calendar.subject != '' AND caro_calendar.span_start <= CURRENT_TIMESTAMP; UPDATE caro_calendar SET alert = 0 WHERE alert = 1 AND span_start <= CURRENT_TIMESTAMP;",
			'sqlsrv' => "SELECT caro_calendar.*, c_u1.name AS author, c_u2.name AS affected_user, c_u2.units AS affected_user_units FROM caro_calendar LEFT JOIN caro_user AS c_u1 ON caro_calendar.author_id = c_u1.id LEFT JOIN caro_user AS c_u2 ON caro_calendar.affected_user_id = c_u2.id WHERE caro_calendar.alert = 1 AND caro_calendar.subject != '' AND caro_calendar.span_start <= CURRENT_TIMESTAMP; UPDATE caro_calendar SET alert = 0 WHERE alert = 1 AND span_start <= CURRENT_TIMESTAMP;",
		],



		'checks_post' => [
			'mysql' => "INSERT INTO caro_checks (id, type, date, author, content) VALUES (NULL, :type, CURRENT_TIMESTAMP, :author, :content)",
			'sqlsrv' => "INSERT INTO caro_checks (type, date, author, content) VALUES (:type, CURRENT_TIMESTAMP, :author, :content)"
		],
		'checks_get_types' => [
			'mysql' => "SELECT type FROM caro_checks GROUP BY type",
			'sqlsrv' => "SELECT type FROM caro_checks GROUP BY type"
		],
		'checks_get' => [
			'mysql' => "SELECT * FROM caro_checks WHERE type = :type ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_checks WHERE type = :type ORDER BY id DESC"
		],
		'checks_get_by_id' => [
			'mysql' => "SELECT * FROM caro_checks WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_checks WHERE id = :id"
		],
		'checks_delete' => [
			'mysql' => "DELETE FROM caro_checks WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_checks WHERE id = :id"
		],



		'consumables_post_vendor' => [
			'mysql' => "INSERT INTO caro_consumables_vendors (id, active, name, info, certificate, pricelist, immutable_fileserver) VALUES ( NULL, :active, :name, :info, :certificate, :pricelist, :immutable_fileserver)",
			'sqlsrv' => "INSERT INTO caro_consumables_vendors (active, name, info, certificate, pricelist, immutable_fileserver) VALUES ( :active, :name, :info, :certificate, :pricelist, :immutable_fileserver)"
		],
		'consumables_put_vendor' => [
			'mysql' => "UPDATE caro_consumables_vendors SET active = :active, name = :name, info = :info, certificate = :certificate, pricelist = :pricelist WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_vendors SET active = :active, name = :name, info = :info, certificate = :certificate, pricelist = :pricelist WHERE id = :id"
		],
		'consumables_get_vendor_datalist' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors ORDER BY name ASC",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors ORDER BY name ASC"
		],
		'consumables_get_vendor' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors WHERE id = :id OR name = :id",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors WHERE CONVERT(VARCHAR, id) = :id OR name = :id"
		],

		'consumables_post_product' => [
			'mysql' => "INSERT INTO caro_consumables_products (id, vendor_id, article_no, article_name, article_alias, article_unit, article_ean, active, protected, trading_good, checked, incorporated, has_expiry_date, special_attention) VALUES (NULL, :vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :active, :protected, :trading_good, NULL, '', :has_expiry_date, :special_attention)",
			'sqlsrv' => "INSERT INTO caro_consumables_products (vendor_id, article_no, article_name, article_alias, article_unit, article_ean, active, protected, trading_good, checked, incorporated, has_expiry_date, special_attention) VALUES (:vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :active, :protected, :trading_good, NULL, '', :has_expiry_date, :special_attention)"
		],
		'consumables_put_product' => [
			'mysql' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, active = :active, protected = :protected, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, active = :active, protected = :protected, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention WHERE id = :id"
		],
		'consumables_put_product_pricelist_import' => [
			'mysql' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean, trading_good = :trading_good, incorporated = :incorporated, has_expiry_date = :has_expiry_date, special_attention = :special_attention WHERE id = :id"
		],
		'consumables_put_batch' => [ // preprocess via strtr
			'mysql' => "UPDATE caro_consumables_products SET :field = :value WHERE id IN (:ids)",
			'sqlsrv' => "UPDATE caro_consumables_products SET :field = :value WHERE id IN (:ids)"
		],
		'consumables_put_check' => [ // preprocess via strtr
			'mysql' => "UPDATE caro_consumables_products SET checked = :checked WHERE id IN (:ids)",
			'sqlsrv' => "UPDATE caro_consumables_products SET checked = :checked WHERE id IN (:ids)"
		],
		'consumables_put_incorporation' => [ // preprocess via strtr
			'mysql' => "UPDATE caro_consumables_products SET incorporated = :incorporated WHERE id IN (:ids)",
			'sqlsrv' => "UPDATE caro_consumables_products SET incorporated = :incorporated WHERE id IN (:ids)"
		],
		'consumables_get_product' => [ // preprocess via strtr
			'mysql' => "SELECT prod.*, dist.name as vendor_name, dist.immutable_fileserver as vendor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.id IN (:ids) AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name, dist.immutable_fileserver as vendor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE CONVERT(VARCHAR, prod.id) IN (:ids) AND prod.vendor_id = dist.id"
		],
		'consumables_get_products_incorporation_attention' => [
			'mysql' => "SELECT prod.id AS id, prod.incorporated AS incorporated, prod.special_attention as special_attention, prod.article_name AS article_name, prod.article_no AS article_no, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.id AS id, prod.incorporated AS incorporated, prod.special_attention as special_attention, prod.article_name AS article_name, prod.article_no AS article_no, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.vendor_id = dist.id"
		],
		'consumables_get_product_search' => [
			'mysql' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id"
		],
		'consumables_get_product_by_article_no_vendor' => [
			'mysql' => "SELECT prod.id FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.article_no LIKE :article_no AND dist.name LIKE :vendor AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.id FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.article_no LIKE :article_no AND dist.name LIKE :vendor AND prod.vendor_id = dist.id"
		],
		'consumables_get_eligible_sample_check' => [ // must be splitted with the following two queries for sql performance reasons
			'mysql' => "SELECT prod.id AS id, prod.article_no AS article_no, dist.name AS vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.vendor_id = dist.id AND prod.trading_good = 1 "
				."AND prod.vendor_id NOT IN (:valid_checked) "
				."AND prod.id NOT IN (:not_reusable)",
			'sqlsrv' => "SELECT prod.id AS id, prod.article_no AS article_no, dist.name AS vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.vendor_id = dist.id AND prod.trading_good = 1 "
				."AND prod.vendor_id NOT IN (:valid_checked) "
				."AND prod.id NOT IN (:not_reusable)"
		],
		'consumables_get_valid_checked' => [
			'mysql' => "SELECT prod.vendor_id AS vendor_id FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.vendor_id = dist.id AND prod.trading_good = 1 "
				."AND DATEDIFF( CURRENT_TIMESTAMP, IFNULL( prod.checked, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL IFNULL( JSON_VALUE(dist.pricelist, '$.samplecheck_interval'), " . INI['lifespan']['mdr14_sample_interval'] . " ) DAY ) ) ) "
				."< IFNULL( JSON_VALUE(dist.pricelist, '$.samplecheck_interval'), " . INI['lifespan']['mdr14_sample_interval'] . " )",
			'sqlsrv' => "SELECT prod.vendor_id FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.vendor_id = dist.id AND prod.trading_good = 1 AND "
				."DATEDIFF(day, ISNULL(prod.checked, DATEADD(DD, ISNULL( JSON_VALUE(dist.pricelist, '$.samplecheck_interval'), " . INI['lifespan']['mdr14_sample_interval'] . ") * -1, GETDATE())), GETDATE()) "
				."< ISNULL(JSON_VALUE(dist.pricelist, '$.samplecheck_interval'), " . INI['lifespan']['mdr14_sample_interval'] . ") "
		],
		'consumables_get_not_reusable_checked' => [
			'mysql' => "SELECT prod.id AS id FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.vendor_id = dist.id AND prod.trading_good = 1 "
				."AND DATEDIFF( CURRENT_TIMESTAMP, IFNULL( prod.checked, DATE_SUB( CURRENT_TIMESTAMP, INTERVAL IFNULL( JSON_VALUE(dist.pricelist, '$.samplecheck_reusable'), " . INI['lifespan']['mdr14_sample_reusable'] . " ) + 1 DAY ) ) ) "
				."< IFNULL( JSON_VALUE(dist.pricelist, '$.samplecheck_reusable'), " . INI['lifespan']['mdr14_sample_reusable'] . " )",
			'sqlsrv' => "SELECT prod.id FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.vendor_id = dist.id AND prod.trading_good = 1 AND "
				."DATEDIFF(day, ISNULL(prod.checked, DATEADD(DD, ISNULL(JSON_VALUE(dist.pricelist, '$.samplecheck_reusable'), " . INI['lifespan']['mdr14_sample_reusable'] . ") * -1 - 1, GETDATE())), GETDATE()) "
				."< ISNULL(JSON_VALUE(dist.pricelist, '$.samplecheck_reusable'), " . INI['lifespan']['mdr14_sample_reusable'] . ") "
		],
		'consumables_get_last_checked' => [
			'mysql' => "SELECT prod.checked as checked, dist.id as vendor_id FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.trading_good = 1 AND prod.vendor_id = dist.id AND "
				. "prod.checked IS NOT NULL ORDER BY prod.checked DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP(1) prod.checked as checked, dist.id as vendor_id FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.trading_good = 1 AND prod.vendor_id = dist.id AND "
				. "prod.checked IS NOT NULL ORDER BY prod.checked"
		],
		'consumables_get_product_units' => [
			'mysql' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC",
			'sqlsrv' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC"
		],
		'consumables_get_products_by_vendor_id' => [
			'mysql' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE dist.id IN (:ids) AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE CONVERT(VARCHAR, dist.id) IN (:ids) AND prod.vendor_id = dist.id"
		],
		'consumables_delete_all_unprotected_products' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE vendor_id = :id AND article_alias = '' AND checked IS NULL AND incorporated = '' AND protected = 0",
			'sqlsrv' => "DELETE FROM caro_consumables_products WHERE vendor_id = :id AND article_alias = '' AND checked IS NULL AND incorporated = '' AND protected = 0"
		],
		'consumables_delete_unprotected_product' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE id = :id AND article_alias = '' AND checked IS NULL AND incorporated = '' AND protected = 0",
			'sqlsrv' => "DELETE FROM caro_consumables_products WHERE id = :id AND article_alias = '' AND checked IS NULL AND incorporated = '' AND protected = 0"
		],



		'csvfilter_post' => [
			'mysql' => "INSERT INTO caro_csvfilter (id, name, date, author, content, hidden) VALUES (NULL, :name, CURRENT_TIMESTAMP, :author, :content, :hidden)",
			'sqlsrv' => "INSERT INTO caro_csvfilter (name, date, author, content, hidden) VALUES (:name, CURRENT_TIMESTAMP, :author, :content, :hidden)"
		],
		'csvfilter_put' => [
			'mysql' => "UPDATE caro_csvfilter SET hidden = :hidden WHERE id = :id",
			'sqlsrv' => "UPDATE caro_csvfilter SET hidden = :hidden WHERE id = :id"
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
			'sqlsrv' => "SELECT TOP 1 * FROM caro_csvfilter WHERE name= :name ORDER BY id DESC"
		],



		'file_bundles_post' => [
			'mysql' => "INSERT INTO caro_file_bundles (id, name, date, content, active) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content, :active)",
			'sqlsrv' => "INSERT INTO caro_file_bundles (name, date, content, active) VALUES (:name, CURRENT_TIMESTAMP, :content, :active)"
		],
		'file_bundles_datalist' => [
			'mysql' => "SELECT name FROM caro_file_bundles GROUP BY name ORDER BY name ASC",
			'sqlsrv' => "SELECT name FROM caro_file_bundles GROUP BY name ORDER BY name ASC"
		],
		'file_bundles_get' => [
			'mysql' => "SELECT * FROM caro_file_bundles WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_file_bundles WHERE name = :name ORDER BY id DESC"
		],
		'file_bundles_get_active' => [
			'mysql' => "SELECT * FROM caro_file_bundles WHERE active = 1 GROUP BY name",
			'sqlsrv' => "SELECT * from caro_file_bundles WHERE id IN (SELECT MAX(id) AS id FROM caro_file_bundles WHERE active = 1 GROUP BY name) ORDER BY name"
		],
		'file_external_documents_get' => [
			'mysql' => "SELECT * FROM caro_file_external_documents ORDER BY path ASC",
			'sqlsrv' => "SELECT * FROM caro_file_external_documents ORDER BY path ASC"
		],
		'file_external_documents_get_active' => [
			'mysql' => "SELECT * FROM caro_file_external_documents WHERE retired IS NULL ORDER BY path ASC",
			'sqlsrv' => "SELECT * FROM caro_file_external_documents WHERE retired IS NULL ORDER BY path ASC"
		],
		'file_external_documents_retire' => [
			'mysql' => "UPDATE caro_file_external_documents SET author = :author, retired = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_file_external_documents SET author = :author, retired = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'file_external_documents_unretire' => [
			'mysql' => "UPDATE caro_file_external_documents SET author = :author, retired = NULL WHERE id = :id",
			'sqlsrv' => "UPDATE caro_file_external_documents SET author = :author, retired = NULL WHERE id = :id"
		],
		'file_external_documents_context' => [
			'mysql' => "UPDATE caro_file_external_documents SET regulatory_context = :regulatory_context WHERE id = :id",
			'sqlsrv' => "UPDATE caro_file_external_documents SET regulatory_context = :regulatory_context WHERE id = :id"
		],
		'file_external_documents_post' => [
			'mysql' => "INSERT INTO caro_file_external_documents (id, path, author, regulatory_context, retired) VALUES (NULL, :path, :author, '', NULL)",
			'sqlsrv' => "INSERT INTO caro_file_external_documents (path, author, regulatory_context, retired) VALUES (:path, :author, '', NULL)"
		],



		'form_post' => [
			'mysql' => "INSERT INTO caro_form (id, name, alias, context, date, author, content, hidden, approval, regulatory_context, permitted_export) VALUES (NULL, :name, :alias, :context, CURRENT_TIMESTAMP, :author, :content, 0, '', :regulatory_context, :permitted_export)",
			'sqlsrv' => "INSERT INTO caro_form (name, alias, context, date, author, content, hidden, approval, regulatory_context, permitted_export) VALUES (:name, :alias, :context, CURRENT_TIMESTAMP, :author, :content, 0, '', :regulatory_context, :permitted_export)"
		],
		'form_put' => [
			'mysql' => "UPDATE caro_form SET alias = :alias, context = :context, hidden = :hidden, regulatory_context = :regulatory_context, permitted_export = :permitted_export WHERE id = :id",
			'sqlsrv' => "UPDATE caro_form SET alias = :alias, context = :context, hidden = :hidden, regulatory_context = :regulatory_context, permitted_export = :permitted_export WHERE id = :id"
		],
		'form_put_approve' => [
			'mysql' => "UPDATE caro_form SET approval = :approval WHERE id = :id",
			'sqlsrv' => "UPDATE caro_form SET approval = :approval WHERE id = :id"
		],
		'form_form_datalist' => [
			'mysql' => "SELECT * FROM caro_form WHERE context NOT IN ('component', 'bundle') ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context NOT IN ('component', 'bundle') ORDER BY name ASC, date DESC"
		],
		'form_component_datalist' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = 'component' ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context = 'component' ORDER BY name ASC, date DESC"
		],
		'form_bundle_datalist' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = 'bundle' ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context = 'bundle' ORDER BY name ASC, date DESC"
		],
		'form_form_get_by_name' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context NOT IN ('component', 'bundle') ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE name = :name AND context NOT IN ('component', 'bundle') ORDER BY id DESC"
		],
		'form_form_get_by_context' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = :context ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context = :context ORDER BY id DESC"
		],
		'form_component_get_by_name' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context = 'component' ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE name = :name AND context = 'component' ORDER BY id DESC"
		],
		'form_bundle_get_by_name' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context = 'bundle' ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE name = :name AND context = 'bundle' ORDER BY id DESC"
		],
		'form_get' => [
			'mysql' => "SELECT * FROM caro_form WHERE id = :id ",
			'sqlsrv' => "SELECT * FROM caro_form WHERE id = :id"
		],
		'form_delete' => [
			'mysql' => "DELETE FROM caro_form WHERE id = :id AND approval = ''",
			'sqlsrv' => "DELETE FROM caro_form WHERE id = :id AND approval = ''"
		],



		'message_get_unnotified' => [
			'mysql' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND notified = 0",
			'sqlsrv' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND notified = 0"
		],
		'message_put_notified' => [
			'mysql' => "UPDATE caro_messages SET notified = 1 WHERE user_id = :user",
			'sqlsrv' => "UPDATE caro_messages SET notified = 1 WHERE user_id = :user"
		],		
		'message_get_unseen' => [
			'mysql' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND seen = 0",
			'sqlsrv' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND seen = 0"
		],
		'message_post_message' => [
			'mysql' => "INSERT INTO caro_messages (id, user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (NULL, :from_user, :to_user, :from_user, :message, CURRENT_TIMESTAMP, 1, 1), (NULL, :to_user, :from_user, :from_user, :message, CURRENT_TIMESTAMP, 0, 0)",
			'sqlsrv' => "INSERT INTO caro_messages (user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (:from_user, :to_user, :from_user, :message, CURRENT_TIMESTAMP, 1, 1), (:to_user, :from_user, :from_user, :message, CURRENT_TIMESTAMP, 0, 0)"
		],
		'message_post_system_message' => [
			'mysql' => "INSERT INTO caro_messages (id, user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (NULL, :to_user, 1, 1, :message, CURRENT_TIMESTAMP, 0, 0)",
			'sqlsrv' => "INSERT INTO caro_messages (user_id, conversation_user, sender, message, timestamp, notified, seen) VALUES (:to_user, 1, 1, :message, CURRENT_TIMESTAMP, 0, 0)"
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
		'message_delete_conversation' => [
			'mysql' => "DELETE FROM caro_messages WHERE user_id = :user AND conversation_user = :conversation",
			'sqlsrv' => "DELETE FROM caro_messages WHERE user_id = :user AND conversation_user = :conversation"
		],



		'order_get_product_search' => [
			'mysql' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id AND dist.active = 1 AND prod.active = 1",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id AND dist.active = 1 AND prod.active = 1"
		],
		'order_post_prepared_order' => [
			'mysql' => "INSERT INTO caro_consumables_prepared_orders (id, order_data) VALUES (NULL, :order_data)",
			'sqlsrv' => "INSERT INTO caro_consumables_prepared_orders (order_data) VALUES (:order_data)"
		],
		'order_put_prepared_order' => [
			'mysql' => "UPDATE caro_consumables_prepared_orders SET order_data = :order_data WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_prepared_orders SET order_data = :order_data WHERE id = :id"
		],
		'order_get_prepared_order' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_consumables_prepared_orders WHERE CONVERT(VARCHAR, id) = :id"
		],
		'order_delete_prepared_orders' => [
			'mysql' => "DELETE FROM caro_consumables_prepared_orders WHERE id IN (:id)",
			'sqlsrv' => "DELETE FROM caro_consumables_prepared_orders WHERE id IN (:id)"
		],

		'order_get_prepared_orders' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders",
			'sqlsrv' => "SELECT * FROM caro_consumables_prepared_orders"
		],

		'order_post_approved_order' => [
			'mysql' => "INSERT INTO caro_consumables_approved_orders (id, order_data, organizational_unit, approval, approved, ordered, received, archived, ordertype) VALUES (NULL, :order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL, :ordertype)",
			'sqlsrv' => "INSERT INTO caro_consumables_approved_orders (order_data, organizational_unit, approval, approved, ordered, received, archived, ordertype) VALUES (:order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL, :ordertype)"
		],
		'order_put_approved_order_ordered' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET ordered = :state WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET ordered = :state WHERE id = :id"
		],
		'order_put_approved_order_received' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET received = :state WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET received = :state WHERE id = :id"
		],
		'order_put_approved_order_archived' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET archived = :state WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET archived = :state WHERE id = :id"
		],
		'order_put_approved_order_addinformation' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data WHERE id = :id"
		],
		'order_put_approved_order_cancellation' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data, ordered = NULL, received = NULL, archived = NULL, ordertype = 'cancellation' WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data, ordered = NULL, received = NULL, archived = NULL, ordertype = 'cancellation' WHERE id = :id"
		],

		'order_get_approved_order_by_unit' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) ORDER BY id DESC"
		],
		'order_get_approved_order_by_id' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE id = :id"
		],
		'order_get_approved_order_by_substr' => [ // CASE SENSITIVE JUST TO BE SURE
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE order_data LIKE CONCAT('%', :substr, '%')",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE order_data LIKE CONCAT('%', :substr, '%')"
		],
		'order_get_approved_order_by_received' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE received < :date_time AND archived IS NULL",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE received < CONVERT(SMALLDATETIME, :date_time, 120) AND archived IS NULL"
		],		
		'order_delete_approved_order' => [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id"
		],
		'order_get_filter' => [
			'mysql' => "SELECT id FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) AND LOWER(order_data) LIKE LOWER(CONCAT('%', :orderfilter, '%'))",
			'sqlsrv' => "SELECT id FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) AND LOWER(order_data) LIKE LOWER(CONCAT('%', :orderfilter, '%'))"
		],
		'order_get_approved_unprocessed' => [
			'mysql' => "SELECT count(id) as num FROM caro_consumables_approved_orders WHERE ordered IS NULL",
			'sqlsrv' => "SELECT count(id) as num FROM caro_consumables_approved_orders WHERE ordered IS NULL",
		],



		'records_post' => [
			'mysql' => "INSERT INTO caro_records (id, context, form_name, form_id, identifier, date, author, author_id, content) VALUES (NULL, :context, :form_name, :form_id, :identifier, :entry_timestamp, :author, :author_id, :content)",
			'sqlsrv' => "INSERT INTO caro_records (context, form_name, form_id, identifier, date, author, author_id, content) VALUES (:context, :form_name, :form_id, :identifier, CONVERT(SMALLDATETIME, :entry_timestamp, 120), :author, :author_id, :content)"
		],
		'records_import' => [
			'mysql' => "SELECT caro_records.*, caro_form.date as form_date FROM caro_records INNER JOIN caro_form on caro_records.form_id = caro_form.id WHERE caro_records.identifier = :identifier ORDER BY caro_records.id ASC",
			'sqlsrv' => "SELECT caro_records.*, caro_form.date as form_date FROM caro_records INNER JOIN caro_form on caro_records.form_id = caro_form.id WHERE caro_records.identifier = :identifier ORDER BY caro_records.id ASC"
		],
		'records_identifiers' => [
			'mysql' => "SELECT MAX(r.id) AS id, r.context, r.identifier, MIN(IFNULL(r.closed, 0)) AS closed, r.author_id AS author_id, u.units AS units FROM caro_records r LEFT JOIN caro_user u ON r.author_id = u.id GROUP BY r.context, u.units, r.identifier",
			'sqlsrv' => "SELECT MAX(r.id) AS id, r.context, r.identifier, MIN(ISNULL(r.closed, 0)) AS closed, MAX(r.author_id) AS author_id, u.units AS units FROM caro_records r LEFT JOIN caro_user u ON r.author_id = u.id GROUP BY r.context, u.units, r.identifier"
		],
		'records_close' => [
			'mysql' => "UPDATE caro_records SET closed = 1 WHERE identifier = :identifier",
			'sqlsrv' => "UPDATE caro_records SET closed = 1 WHERE identifier = :identifier"
		],



		'risk_post' => [
			'mysql' => "INSERT INTO caro_risks (id, process, risk, cause, effect, probability, damage, measure, measure_probability, measure_damage, risk_benefit, measure_remainder) VALUES (NULL, :process, :risk, :cause, :effect, :probability, :damage, :measure, :measure_probability, :measure_damage, :risk_benefit, :measure_remainder)",
			'sqlsrv' => "INSERT INTO caro_risks (process, risk, cause, effect, probability, damage, measure, measure_probability, measure_damage, risk_benefit, measure_remainder) VALUES (:process, :risk, :cause, :effect, :probability, :damage, :measure, :measure_probability, :measure_damage, :risk_benefit, :measure_remainder)"
		],
		'risk_put' => [
			'mysql' => "UPDATE caro_risks SET process = :process, risk = :risk, cause = :cause, effect = :effect, probability = :probability, damage = :damage, measure = :measure, measure_probability = :measure_probability, measure_damage = :measure_damage, risk_benefit = :risk_benefit, measure_remainder = :measure_remainder WHERE id = :id",
			'sqlsrv' => "UPDATE caro_risks SET process = :process, risk = :risk, cause = :cause, effect = :effect, probability = :probability, damage = :damage, measure = :measure, measure_probability = :measure_probability, measure_damage = :measure_damage, risk_benefit = :risk_benefit, measure_remainder = :measure_remainder WHERE id = :id"
		],
		'risk_datalist' => [
			'mysql' => "SELECT * FROM caro_risks ORDER BY process, risk, cause, effect",
			'sqlsrv' => "SELECT * FROM caro_risks ORDER BY process, risk, cause, effect"
		],
		'risk_get' => [
			'mysql' => "SELECT * FROM caro_risks WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_risks WHERE id = :id"
		],
		'risk_delete' => [
			'mysql' => "DELETE FROM caro_risks WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_risks WHERE id = :id"
		],



		'texttemplate_post' => [
			'mysql' => "INSERT INTO caro_texttemplates (id, name, unit, date, author, content, language, type, hidden) VALUES (NULL, :name, :unit, CURRENT_TIMESTAMP, :author, :content, :language, :type, :hidden)",
			'sqlsrv' => "INSERT INTO caro_texttemplates (name, unit, date, author, content, language, type, hidden) VALUES (:name, :unit, CURRENT_TIMESTAMP, :author, :content, :language, :type, :hidden)"
		],
		'texttemplate_put' => [
			'mysql' => "UPDATE caro_texttemplates SET hidden = :hidden, unit = :unit WHERE id = :id",
			'sqlsrv' => "UPDATE caro_texttemplates SET hidden = :hidden, unit = :unit WHERE id = :id"
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



		'user_post' => [
			'mysql' => "INSERT INTO caro_user (id, name, permissions, units, token, orderauth, image, app_settings, skills) VALUES ( NULL, :name, :permissions, :units, :token, :orderauth, :image, :app_settings, :skills)",
			'sqlsrv' => "INSERT INTO caro_user (name, permissions, units, token, orderauth, image, app_settings, skills) VALUES ( :name, :permissions, :units, :token, :orderauth, :image, :app_settings, :skills)"
		],
		'user_put' => [
			'mysql' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, orderauth = :orderauth, image = :image, app_settings = :app_settings, skills = :skills WHERE id = :id",
			'sqlsrv' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, orderauth = :orderauth, image = :image, app_settings = :app_settings, skills = :skills WHERE id = :id"
		],
		'user_get_datalist' => [
			'mysql' => "SELECT id, name, orderauth, permissions, units, app_settings, skills FROM caro_user ORDER BY name ASC",
			'sqlsrv' => "SELECT id, name, orderauth, permissions, units, app_settings, skills FROM caro_user ORDER BY name ASC"
		],
		'user_get' => [
			'mysql' => "SELECT * FROM caro_user WHERE id IN (:id) OR name IN (:name)",
			'sqlsrv' => "SELECT * FROM caro_user WHERE CONVERT(VARCHAR, id) IN (:id) OR name IN (:name)"
		],
		'user_get_cached' => [
			'mysql' => "SELECT * FROM caro_user WHERE SHA2(CONCAT(SHA2(id, 256), :checksum), 256) = :hash",
			'sqlsrv' => "SELECT * FROM caro_user WHERE LOWER(CONVERT(VARCHAR(100), HASHBYTES('SHA2_256', CONCAT(LOWER(CONVERT(VARCHAR(100), HASHBYTES('SHA2_256', CONVERT(VARCHAR, id)), 2)), :checksum)), 2)) = :hash"
		],
		'user_get_orderauth' => [
			'mysql' => "SELECT * FROM caro_user WHERE orderauth = :orderauth",
			'sqlsrv' => "SELECT * FROM caro_user WHERE orderauth = :orderauth"
		],
		'user_delete' => [
			'mysql' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id; DELETE FROM caro_calendar WHERE affected_user_id = :id; DELETE FROM caro_user_training WHERE user_id = :id",
			'sqlsrv' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id; DELETE FROM caro_calendar WHERE affected_user_id = :id; DELETE FROM caro_user_training WHERE user_id = :id"
		],



		'user_training_post' => [
			'mysql' => "INSERT INTO caro_user_training (id, user_id, name, date, expires, experience_points, file_path) VALUES ( NULL, :user_id, :name, :date, :expires, :experience_points, :file_path)",
			'sqlsrv' => "INSERT INTO caro_user_training (user_id, name, date, expires, experience_points, file_path) VALUES ( :user_id, :name, CONVERT(DATE, :date, 23), CONVERT(DATE, :expires, 23), :experience_points, :file_path)"
		],
		'user_training_get_user' => [
			'mysql' => "SELECT * FROM caro_user_training WHERE user_id IN (:ids)",
			'sqlsrv' => "SELECT * FROM caro_user_training WHERE user_id IN (:ids)"
		],
		'user_training_delete' => [
			'mysql' => "DELETE FROM caro_user_training WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_user_training WHERE id = :id"
		],
	];
}
?>