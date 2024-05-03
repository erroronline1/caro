<?php
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
	 * creates packages of sql queries to handle sql package size
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
	 * @param string $query sql query
	 * @param array $items named array to replace query by strtr
	 * @return array $chunks extended packages so far
	 * 
	 * this does make sense to only have to define one valid (and standalone as well) reusable dummy query
	 */
	public static function CHUNKIFY_INSERT($query = null, $items = null){
		$chunks = [];
		if ($query && $items){
			[$query, $values] = explode('VALUES', $query);
			$chunkeditems = [];
			foreach($items as $item){
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
	 * creates packages of sql queries with IN clause to handle sql package size
	 * e.g. for update where id in (huge list) clauses
	 * @param string $query sql query
	 * @param string $replace string for strtr replacement
	 * @param array $items for IN clause
	 * @return array $chunks packages
	 */
	public static function CHUNKIFY_IN($query = null, $replace = null, $items = null){
		$chunks = [];
		if ($query && $replace && $items){
			$chunkeditems = [];
			foreach($items as $item){
				if (count($chunkeditems)){
					$index = count($chunkeditems) - 1;
					if (strlen(strtr($query, [$replace=> implode(',', [$item, ...$chunkeditems[$index]])])) < INI['sql'][INI['sql']['use']]['packagesize']){
						$chunkeditems[$index][] = $item;
					}
					else $chunkeditems[] = [$item];
				} else $chunkeditems[] = [$item];
			}
			foreach($chunkeditems as $items){
				$chunks[] = strtr($query, [$replace => implode(',', $items)]);
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
		'application_get_manual-by-id' => [
			'mysql' => "SELECT * FROM caro_manual WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_manual WHERE id = :id"
		],
		'application_delete_manual' => [
			'mysql' => "DELETE FROM caro_manual WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_manual WHERE id = :id"
		],

		'user_post' => [
			'mysql' => "INSERT INTO caro_user (id, name, permissions, units, token, orderauth, image, app_settings) VALUES ( NULL, :name, :permissions, :units, :token, :orderauth, :image, :app_settings)",
			'sqlsrv' => "INSERT INTO caro_user (name, permissions, units, token, orderauth, image, app_settings) VALUES ( :name, :permissions, :units, :token, :orderauth, :image, :app_settings)"
		],
		'user_put' => [
			'mysql' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, orderauth = :orderauth, image = :image, app_settings = :app_settings WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, orderauth = :orderauth, image = :image, app_settings = :app_settings WHERE id = :id"
		],
		'user_get-datalist' => [
			'mysql' => "SELECT id, name, orderauth, permissions, units FROM caro_user ORDER BY name ASC",
			'sqlsrv' => "SELECT id, name, orderauth, permissions, units FROM caro_user ORDER BY name ASC"
		],
		'user_get' => [
			'mysql' => "SELECT * FROM caro_user WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => "SELECT * FROM caro_user WHERE CONVERT(VARCHAR, id) = :id OR name = :id"
		],
		'user_get-orderauth' => [
			'mysql' => "SELECT * FROM caro_user WHERE orderauth = :orderauth LIMIT 1",
			'sqlsrv' => "SELECT * FROM caro_user WHERE orderauth = :orderauth"
		],
		'user_delete' => [
			'mysql' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id",
			'sqlsrv' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id"
		],

		'message_get_message' => [
			'mysql' => "SELECT t1.*, t2.name as from_user, t3.name as to_user FROM caro_messages as t1, caro_user as t2, caro_user as t3 WHERE t1.id = :id AND t1.user_id = :user AND t1.from_user = t2.id AND t1.to_user = t3.id LIMIT 1",
			'sqlsrv' => "SELECT t1.*, t2.name as from_user, t3.name as to_user FROM caro_messages as t1, caro_user as t2, caro_user as t3 WHERE t1.id = :id AND t1.user_id = :user AND t1.from_user = t2.id AND t1.to_user = t3.id"
		],
		'message_get_unnotified' => [
			'mysql' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND notified = 0",
			'sqlsrv' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND notified = 0"
		],
		'message_get_unseen' => [
			'mysql' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND seen = 0",
			'sqlsrv' => "SELECT COUNT(id) as number FROM caro_messages WHERE user_id = :user AND seen = 0"
		],
		'message_get_filter' => [
			'mysql' => "SELECT id FROM caro_messages WHERE user_id = :user AND LOWER(message) LIKE LOWER(CONCAT('%', :msgfilter, '%'))",
			'sqlsrv' => "SELECT id FROM caro_messages WHERE user_id = :user AND LOWER(message) LIKE LOWER(CONCAT('%', :msgfilter, '%'))"
		],
		'message_post_message' => [
			'mysql' => "INSERT INTO caro_messages (id, user_id, from_user, to_user, message, timestamp, notified, seen) VALUES (NULL, :from_user, :from_user, :to_user, :message, CURRENT_TIMESTAMP, 1, 1), (NULL, :to_user, :from_user, :to_user, :message, CURRENT_TIMESTAMP, 0, 0)",
			'sqlsrv' => "INSERT INTO caro_messages (user_id, from_user, to_user, message, timestamp, notified, seen) VALUES (:from_user, :from_user, :to_user, :message, CURRENT_TIMESTAMP, 1, 1), (:to_user, :from_user, :to_user, :message, CURRENT_TIMESTAMP, 0, 0)"
		],
		'message_post_system_message' => [
			'mysql' => "INSERT INTO caro_messages (id, user_id, from_user, to_user, message, timestamp, notified, seen) VALUES (NULL, :to_user, 1, :to_user, :message, CURRENT_TIMESTAMP, 0, 0)",
			'sqlsrv' => "INSERT INTO caro_messages (user_id, from_user, to_user, message, timestamp, notified, seen) VALUES (:to_user, 1, :to_user, :message, CURRENT_TIMESTAMP, 0, 0)"
		],
		'message_delete_message' => [
			'mysql' => "DELETE FROM caro_messages WHERE id = :id and user_id = :user LIMIT 1",
			'sqlsrv' => "DELETE FROM caro_messages WHERE id = :id and user_id = :user"
		],
		'message_get_inbox' => [
			'mysql' => "SELECT caro_messages.*, caro_user.name as from_user, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.from_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.to_user = :user ORDER BY caro_messages.timestamp DESC",
			'sqlsrv' => "SELECT caro_messages.*, caro_user.name as from_user, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.from_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.to_user = :user ORDER BY caro_messages.timestamp DESC"
		],
		'message_put_notified' => [
			'mysql' => "UPDATE caro_messages SET notified = 1 WHERE user_id = :user",
			'sqlsrv' => "UPDATE caro_messages SET notified = 1 WHERE user_id = :user"
		],		
		'message_put_seen' => [
			'mysql' => "UPDATE caro_messages SET notified = 1, seen = 1 WHERE user_id = :user",
			'sqlsrv' => "UPDATE caro_messages SET notified = 1, seen = 1 WHERE user_id = :user"
		],		
		'message_get_sent' => [
			'mysql' => "SELECT caro_messages.*, caro_user.name as to_user, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.to_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.from_user = :user ORDER BY caro_messages.timestamp DESC",
			'sqlsrv' => "SELECT caro_messages.*, caro_user.name as to_user, caro_user.image FROM caro_messages LEFT JOIN caro_user ON caro_messages.to_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.from_user = :user ORDER BY caro_messages.timestamp DESC"
		],

		'texttemplate-post' => [
			'mysql' => "INSERT INTO caro_texttemplates (id, name, unit, date, author, content, language, type, hidden) VALUES (NULL, :name, :unit, CURRENT_TIMESTAMP, :author, :content, :language, :type, :hidden)",
			'sqlsrv' => "INSERT INTO caro_texttemplates (name, unit, date, author, content, language, type, hidden) VALUES (:name, :unit, CURRENT_TIMESTAMP, :author, :content, :language, :type, :hidden)"
		],
		'texttemplate-put' => [
			'mysql' => "UPDATE caro_texttemplates SET hidden = :hidden, unit = :unit WHERE id = :id",
			'sqlsrv' => "UPDATE caro_texttemplates SET hidden = :hidden, unit = :unit WHERE id = :id"
		],
		'texttemplate-datalist' => [
			'mysql' => "SELECT * FROM caro_texttemplates ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_texttemplates name ORDER BY name ASC, date DESC"
		],
		'texttemplate_get-chunk' => [
			'mysql' => "SELECT * FROM caro_texttemplates WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_texttemplates WHERE id = :id"
		],
		'texttemplate_get-latest-by-name' => [
			'mysql' => "SELECT * FROM caro_texttemplates WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_texttemplates WHERE name= :name ORDER BY id DESC"
		],

		'file_bundles-post' => [
			'mysql' => "INSERT INTO caro_file_bundles (id, name, date, content, active) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content, :active)",
			'sqlsrv' => "INSERT INTO caro_file_bundles (name, date, content, active) VALUES (:name, CURRENT_TIMESTAMP, :content, :active)"
		],
		'file_bundles-datalist' => [
			'mysql' => "SELECT name FROM caro_file_bundles GROUP BY name ORDER BY name ASC",
			'sqlsrv' => "SELECT name FROM caro_file_bundles GROUP BY name ORDER BY name ASC"
		],
		'file_bundles-get' => [
			'mysql' => "SELECT * FROM caro_file_bundles WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_file_bundles WHERE name = :name ORDER BY id DESC"
		],
		'file_bundles-get-active' => [
			'mysql' => "SELECT * FROM caro_file_bundles WHERE active = 1 GROUP BY name",
			'sqlsrv' => "SELECT * from caro_file_bundles WHERE id IN (SELECT MAX(id) AS id FROM caro_file_bundles WHERE active=1 GROUP BY name) ORDER BY name"
		],

		'form_post' => [
			'mysql' => "INSERT INTO caro_form (id, name, alias, context, date, author, content, hidden, ceo_approval, qmo_approval, supervisor_approval, regulatory_context) VALUES (NULL, :name, :alias, :context, CURRENT_TIMESTAMP, :author, :content, 0, NULL, NULL, NULL, :regulatory_context)",
			'sqlsrv' => "INSERT INTO caro_form (name, alias, context, date, author, content, hidden, ceo_approval, qmo_approval, supervisor_approval, regulatory_context) VALUES (:name, :alias, :context, CURRENT_TIMESTAMP, :author, :content, 0, NULL, NULL, NULL, :regulatory_context)"
		],
		'form_put' => [
			'mysql' => "UPDATE caro_form SET alias = :alias, context = :context, hidden = :hidden, regulatory_context = :regulatory_context WHERE id = :id",
			'sqlsrv' => "UPDATE caro_form SET alias = :alias, context = :context, hidden = :hidden, regulatory_context = :regulatory_context WHERE id = :id"
		],
		'form_put-approve' => [
			'mysql' => "UPDATE caro_form SET ceo_approval = :ceo_approval, qmo_approval = :qmo_approval, supervisor_approval = :supervisor_approval WHERE id = :id",
			'sqlsrv' => "UPDATE caro_form SET ceo_approval = :ceo_approval, qmo_approval = :qmo_approval, supervisor_approval = :supervisor_approval WHERE id = :id"
		],
		'form_form-datalist' => [
			'mysql' => "SELECT * FROM caro_form WHERE context NOT IN ('component', 'bundle') ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context NOT IN ('component', 'bundle') ORDER BY name ASC, date DESC"
		],
		'form_form-datalist-approved' => [
			'mysql' => "SELECT * FROM caro_form WHERE context NOT IN ('component', 'bundle') AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context NOT IN ('component', 'bundle') AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY name ASC, date DESC"
		],
		'form_component-datalist' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = 'component' ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context = 'component' ORDER BY name ASC, date DESC"
		],
		'form_component-datalist-approved' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = 'component' AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context = 'component' AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY name ASC, date DESC"
		],
		'form_bundle-datalist-edit' => [
			'mysql' => "SELECT * FROM caro_form WHERE context != 'component' AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context != 'component' AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY name ASC, date DESC"
		],
		'form_bundle-datalist' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = 'bundle' ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_form WHERE context = 'bundle' ORDER BY name ASC, date DESC"
		],
		'form_form-get-latest-by-name' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context NOT IN ('component', 'bundle') AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_form WHERE name= :name AND context NOT IN ('component', 'bundle') AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY id DESC"
		],
		'form_form-get-latest-by-context' => [
			'mysql' => "SELECT * FROM caro_form WHERE context = :context AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_form WHERE context= :context AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY id DESC"
		],
		'form_component-get-latest-by-name-approved' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context = 'component' AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_form WHERE name= :name AND context = 'component' AND ceo_approval IS NOT NULL AND qmo_approval IS NOT NULL AND supervisor_approval IS NOT NULL ORDER BY id DESC"
		],
		'form_component-get-latest-by-name' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context = 'component' ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_form WHERE name= :name AND context = 'component' ORDER BY id DESC"
		],
		'form_bundle-get-latest-by-name' => [
			'mysql' => "SELECT * FROM caro_form WHERE name = :name AND context = 'bundle' ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_form WHERE name= :name AND context = 'bundle' ORDER BY id DESC"
		],
		'form_get' => [
			'mysql' => "SELECT * FROM caro_form WHERE id = :id ",
			'sqlsrv' => "SELECT * FROM caro_form WHERE id = :id"
		],
		'form_delete' => [
			'mysql' => "DELETE FROM caro_form WHERE id = :id AND (ceo_approval IS NULL OR qmo_approval IS NULL OR supervisor_approval IS NULL)",
			'sqlsrv' => "DELETE FROM caro_form WHERE id = :id AND (ceo_approval IS NULL OR qmo_approval IS NULL OR supervisor_approval IS NULL)"
		],

		'records_post' => [
			'mysql' => "INSERT INTO caro_records (id, context, form_name, form_id, identifier, date, author, author_id, content) VALUES (NULL, :context, :form_name, :form_id, :identifier, CURRENT_TIMESTAMP, :author, :author_id, :content)",
			'sqlsrv' => "INSERT INTO caro_records (context, form_name, form_id, identifier, date, author, author_id, content) VALUES (:context, :form_name, :form_id, :identifier, CURRENT_TIMESTAMP, :author, :author_id, :content)"
		],
		'records_import' => [
			'mysql' => "SELECT caro_records.*, caro_form.date as form_date FROM caro_records inner join caro_form on caro_records.form_id = caro_form.id WHERE caro_records.identifier = :identifier ORDER BY caro_records.id ASC",
			'sqlsrv' => "SELECT caro_records.*, caro_form.date as form_date FROM caro_records inner join caro_form on caro_records.form_id = caro_form.id WHERE caro_records.identifier = :identifier ORDER BY caro_records.id ASC"
		],
		'records_identifiers' => [
			'mysql' => "SELECT MAX(r.id) AS id, r.context, r.identifier, MIN(IFNULL(r.closed, 0)) AS closed, r.author_id AS author_id, u.units AS units FROM caro_records r LEFT JOIN caro_user u ON r.author_id = u.id GROUP BY r.context, u.units, r.identifier",
			'sqlsrv' => "SELECT MAX(r.id) AS id, r.context, r.identifier, MIN(ISNULL(r.closed, 0)) AS closed, MAX(r.author_id) AS author_id, u.units AS units FROM caro_records r LEFT JOIN caro_user u ON r.author_id = u.id GROUP BY r.context, u.units, r.identifier"
		],
		'records_close' => [
			'mysql' => "UPDATE caro_records SET closed = 1 WHERE identifier = :identifier",
			'sqlsrv' => "UPDATE caro_records SET closed = 1 WHERE identifier = :identifier"
		],

		'consumables_post-vendor' => [
			'mysql' => "INSERT INTO caro_consumables_vendors (id, active, name, info, certificate, pricelist, immutable_fileserver) VALUES ( NULL, :active, :name, :info, :certificate, :pricelist, :immutable_fileserver)",
			'sqlsrv' => "INSERT INTO caro_consumables_vendors (active, name, info, certificate, pricelist, immutable_fileserver) VALUES ( :active, :name, :info, :certificate, :pricelist, :immutable_fileserver)"
		],
		'consumables_put-vendor' => [
			'mysql' => "UPDATE caro_consumables_vendors SET active = :active, name = :name, info = :info, certificate = :certificate, pricelist = :pricelist WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_consumables_vendors SET active = :active, name = :name, info = :info, certificate = :certificate, pricelist = :pricelist WHERE id = :id"
		],
		'consumables_get-vendor-datalist' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors ORDER BY name ASC",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors ORDER BY name ASC"
		],
		'consumables_get-vendor' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors WHERE CONVERT(VARCHAR, id) = :id OR name = :id"
		],
		'consumables_post-product' => [
			'mysql' => "INSERT INTO caro_consumables_products (id, vendor_id, article_no, article_name, article_alias, article_unit, article_ean, active, protected, trading_good, checked, incorporated) VALUES (NULL, :vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :active, :protected, :trading_good, NULL, NULL)",
			'sqlsrv' => "INSERT INTO caro_consumables_products (vendor_id, article_no, article_name, article_alias, article_unit, article_ean, active, protected, trading_good, checked, incorporated) VALUES (:vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :active, :protected, :trading_good, NULL, NULL)"
		],
		'consumables_put-product' => [
			'mysql' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, active = :active, protected = :protected, trading_good = :trading_good, incorporated = :incorporated WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, active = :active, protected = :protected, trading_good = :trading_good, incorporated = :incorporated WHERE id = :id"
		],
		'consumables_put-product-protected' => [
			'mysql' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean, trading_good = :trading_good, incorporated = :incorporated WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean, trading_good = :trading_good, incorporated = :incorporated WHERE id = :id"
		],
		'consumables_put-trading-good' => [
			'mysql' => "UPDATE caro_consumables_products SET trading_good = 1 WHERE id IN (:ids)",
			'sqlsrv' => "UPDATE caro_consumables_products SET trading_good = 1 WHERE id IN (:ids)"
		],
		'consumables_put-check' => [
			'mysql' => "UPDATE caro_consumables_products SET protected = 1, checked = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET protected = 1, checked = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'consumables_put-incorporation' => [
			'mysql' => "UPDATE caro_consumables_products SET protected = 1, incorporated = :incorporated WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_products SET protected = 1, incorporated = :incorporated WHERE id = :id"
		],
		'consumables_get-product' => [
			'mysql' => "SELECT prod.*, IFNULL(prod.incorporated, 100) as incorporated, dist.name as vendor_name, dist.immutable_fileserver as vendor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.id = :id AND prod.vendor_id = dist.id LIMIT 1",
			'sqlsrv' => "SELECT prod.*, ISNULL(prod.incorporated, 100) as incorporated, dist.name as vendor_name, dist.immutable_fileserver as vendor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE CONVERT(VARCHAR, prod.id) = :id AND prod.vendor_id = dist.id"
		],
		'consumables_get-product-search' => [
			'mysql' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id"
		],
		'consumables_get-not-checked' => [
			'mysql' => "SELECT prod.id AS id, prod.article_no AS article_no, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.trading_good = 1 AND prod.vendor_id = dist.id AND "
				. "(IFNULL(prod.checked, " . (INI['limits']['mdr14_sample_interval'] + 1) . ") > " . INI['limits']['mdr14_sample_interval'] . " OR DATEDIFF(prod.checked, CURRENT_TIMESTAMP) > " . INI['limits']['mdr14_sample_interval'] . ") AND "
				. "prod.vendor_id NOT IN (SELECT vendor_id from caro_consumables_products WHERE DATEDIFF(checked, CURRENT_TIMESTAMP) < " . INI['limits']['mdr14_sample_interval'] . ") AND "
				. "prod.id NOT IN (select id from caro_consumables_products where IFNULL(checked, 0) != 0 AND DATEDIFF(checked, CURRENT_TIMESTAMP) < " . INI['limits']['mdr14_sample_reusable'] . ")",
			'sqlsrv' => "SELECT prod.id AS id, prod.article_no AS article_no, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.trading_good = 1 AND prod.vendor_id = dist.id AND "
				. "(ISNULL(prod.checked, " . (INI['limits']['mdr14_sample_interval'] + 1) . ") > " . INI['limits']['mdr14_sample_interval'] . " OR DATEDIFF(day, prod.checked, GETDATE()) > " . INI['limits']['mdr14_sample_interval'] . ") AND "
				. "prod.vendor_id NOT IN (SELECT vendor_id from caro_consumables_products WHERE DATEDIFF(day, checked, GETDATE()) < " . INI['limits']['mdr14_sample_interval'] . ") AND "
				. "prod.id NOT IN(select id from caro_consumables_products where ISNULL(checked, 0) != 0 AND DATEDIFF(day, checked, GETDATE()) < " . INI['limits']['mdr14_sample_reusable'] . ")"
		],
		'consumables_get-last_checked' => [
			'mysql' => "SELECT prod.checked as checked, dist.id as vendor_id FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.trading_good = 1 AND prod.vendor_id = dist.id AND "
				. "(IFNULL(prod.checked, 100) != 100) ORDER BY prod.checked DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP(1) prod.checked as checked, dist.id as vendor_id FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.trading_good = 1 AND prod.vendor_id = dist.id AND "
			. "(ISNULL(prod.checked, 100) != 100) ORDER BY prod.checked"
		],

		'consumables_get-not-incorporated' => [
			'mysql' => "SELECT prod.id AS id, prod.article_no AS article_no, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.vendor_id = dist.id AND IFNULL(prod.incorporated, 100) = 100",
			'sqlsrv' => "SELECT prod.id AS id, prod.article_no AS article_no, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors as dist WHERE prod.vendor_id = dist.id AND ISNULL(prod.incorporated, 100) = 100"
		],

		'consumables_get-product-units' => [
			'mysql' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC",
			'sqlsrv' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC"
		],
		'consumables_get-products-by-vendor-id' => [
			'mysql' => "SELECT prod.*, IFNULL(prod.incorporated, 100) as incorporated, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE dist.id = :search AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.*, ISNULL(prod.incorporated, 100) as incorporated, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE CONVERT(VARCHAR, dist.id) = :search AND prod.vendor_id = dist.id"
		],
		'consumables_delete-all-unprotected-products' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE vendor_id = :id AND protected = 0",
			'sqlsrv' => "DELETE FROM caro_consumables_products WHERE vendor_id = :id AND protected = 0"
		],
		'consumables_delete-unprotected-product' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE id = :id AND protected = 0",
			'sqlsrv' => "DELETE FROM caro_consumables_products WHERE id = :id AND protected = 0"
		],

		'order_get-product-search' => [
			'mysql' => "SELECT prod.*, IFNULL(prod.incorporated, 100) as incorporated, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id AND dist.active = 1 AND prod.active = 1",
			'sqlsrv' => "SELECT prod.*, ISNULL(prod.incorporated, 100) as incorporated, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id IN (:vendors) AND prod.vendor_id = dist.id AND dist.active = 1 AND prod.active = 1"
		],
		'order_post-prepared-order' => [
			'mysql' => "INSERT INTO caro_consumables_prepared_orders (id, order_data) VALUES (NULL, :order_data)",
			'sqlsrv' => "INSERT INTO caro_consumables_prepared_orders (order_data) VALUES (:order_data)"
		],
		'order_put-prepared-order' => [
			'mysql' => "UPDATE caro_consumables_prepared_orders SET order_data = :order_data WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_prepared_orders SET order_data = :order_data WHERE id = :id"
		],
		'order_get-prepared-order' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_consumables_prepared_orders WHERE CONVERT(VARCHAR, id) = :id"
		],
		'order_delete-prepared-orders' => [
			'mysql' => "DELETE FROM caro_consumables_prepared_orders WHERE id IN (:id)",
			'sqlsrv' => "DELETE FROM caro_consumables_prepared_orders WHERE id IN (:id)"
		],

		'order_get-prepared-orders' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders",
			'sqlsrv' => "SELECT * FROM caro_consumables_prepared_orders"
		],

		'order_post-approved-order' => [
			'mysql' => "INSERT INTO caro_consumables_approved_orders (id, order_data, organizational_unit, approval, approved, ordered, received, archived, ordertype) VALUES (NULL, :order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL, :ordertype)",
			'sqlsrv' => "INSERT INTO caro_consumables_approved_orders (order_data, organizational_unit, approval, approved, ordered, received, archived, ordertype) VALUES (:order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL, :ordertype)"
		],
		'order_put-approved-order-ordered' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET ordered = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET ordered = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'order_put-approved-order-received' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET received = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET received = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'order_put-approved-order-archived' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET archived = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET archived = CURRENT_TIMESTAMP WHERE id = :id"
		],
		'order_put-approved-order-addinformation' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data WHERE id = :id"
		],
		'order_put-approved-order-cancellation' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data, ordered = NULL, received = NULL, archived = NULL, ordertype = 'cancellation' WHERE id = :id",
			'sqlsrv' => "UPDATE caro_consumables_approved_orders SET order_data = :order_data, ordered = NULL, received = NULL, archived = NULL, ordertype = 'cancellation' WHERE id = :id"
		],

		'order_get-approved-order-by-unit' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) ORDER BY id DESC"
		],
		'order_get-approved-order-by-id' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE id = :id"
		],
		'order_get-approved-order-by-substr' => [ // CASE SENSITIVE JUST TO BE SURE
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE order_data LIKE CONCAT('%', :substr, '%')",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE order_data LIKE CONCAT('%', :substr, '%')"
		],
		'order_get-approved-order-by-received' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE received < :date_time AND archived IS NULL",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE received < CONVERT(SMALLDATETIME, :date_time, 120) AND archived IS NULL"
		],		
		'order_delete-approved-order' => [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id"
		],
		'order_get_filter' => [
			'mysql' => "SELECT id FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) AND LOWER(order_data) LIKE LOWER(CONCAT('%', :orderfilter, '%'))",
			'sqlsrv' => "SELECT id FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) AND LOWER(order_data) LIKE LOWER(CONCAT('%', :orderfilter, '%'))"
		],
		'order_get_approved_unprocessed' => [
			'mysql' => "SELECT count(id) as num FROM caro_consumables_approved_orders WHERE IFNULL(ordered, 100) = 100",
			'sqlsrv' => "SELECT count(id) as num FROM caro_consumables_approved_orders WHERE ISNULL(ordered, 100) = 100",
		],

		'csvfilter-post' => [
			'mysql' => "INSERT INTO caro_csvfilter (id, name, date, author, content, hidden) VALUES (NULL, :name, CURRENT_TIMESTAMP, :author, :content, :hidden)",
			'sqlsrv' => "INSERT INTO caro_csvfilter (name, date, author, content, hidden) VALUES (:name, CURRENT_TIMESTAMP, :author, :content, :hidden)"
		],
		'csvfilter-put' => [
			'mysql' => "UPDATE caro_csvfilter SET hidden = :hidden WHERE id = :id",
			'sqlsrv' => "UPDATE caro_csvfilter SET hidden = :hidden WHERE id = :id"
		],
		'csvfilter-datalist' => [
			'mysql' => "SELECT * FROM caro_csvfilter ORDER BY name ASC, date DESC",
			'sqlsrv' => "SELECT * FROM caro_csvfilter name ORDER BY name ASC, date DESC"
		],
		'csvfilter_get-filter' => [
			'mysql' => "SELECT * FROM caro_csvfilter WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_csvfilter WHERE id = :id"
		],
		'csvfilter_get-latest-by-name' => [
			'mysql' => "SELECT * FROM caro_csvfilter WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 * FROM caro_csvfilter WHERE name= :name ORDER BY id DESC"
		],

		'checks_post' => [
			'mysql' => "INSERT INTO caro_checks (id, type, date, author, content) VALUES (NULL, :type, CURRENT_TIMESTAMP, :author, :content)",
			'sqlsrv' => "INSERT INTO caro_checks (type, date, author, content) VALUES (:type, CURRENT_TIMESTAMP, :author, :content)"
		],
		'checks_get-types' => [
			'mysql' => "SELECT type FROM caro_checks GROUP BY type",
			'sqlsrv' => "SELECT type FROM caro_checks GROUP BY type"
		],
		'checks_get' => [
			'mysql' => "SELECT * FROM caro_checks WHERE type = :type ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_checks WHERE type = :type ORDER BY id DESC"
		],

		'calendar_post' => [
			'mysql' => "INSERT INTO caro_calendar (id, date, due, type, author, organizational_unit, content, completed) VALUES (NULL, :date, :due, :type, :author, :organizational_unit, :content, '')",
			'sqlsrv' => "INSERT INTO caro_calendar (date, due, type, author, organizational_unit, content, completed) VALUES (CAST(:date AS DATE), CAST(:due AS DATE), :type, :author, :organizational_unit, :content, '')",
		],
		'calendar_put' => [
			'mysql' => "UPDATE caro_calendar SET date = :date, due = :due, author = :author, organizational_unit = :organizational_unit, content = :content WHERE id = :id",
			'sqlsrv' => "UPDATE caro_calendar SET date = CAST(:date AS DATE), due = CAST(:due AS DATE), author = :author, organizational_unit = :organizational_unit, content = :content WHERE id = :id",
		],
		'calendar_complete' => [
			'mysql' => "UPDATE caro_calendar SET completed = :completed WHERE id = :id",
			'sqlsrv' => "UPDATE caro_calendar SET completed = :completed WHERE id = :id",
		],
		'calendar_get-date' => [
			'mysql' => "SELECT * FROM caro_calendar WHERE date = :date ORDER BY due ASC",
			'sqlsrv' => "SELECT * FROM caro_calendar WHERE date = :date ORDER BY due ASC",
		],
		'calendar_get-date-range' => [
			'mysql' => "SELECT * FROM caro_calendar WHERE date >= :earlier and date <= :later ORDER BY due ASC",
			'sqlsrv' => "SELECT * FROM caro_calendar WHERE date >= CAST(:earlier AS DATE) and date <= CAST(:later AS DATE) ORDER BY due ASC",
		],
		'calendar_search' => [
			'mysql' => "SELECT * FROM caro_calendar WHERE LOWER(content) LIKE LOWER(CONCAT('%', :content, '%')) ORDER BY due ASC",
			'sqlsrv' => "SELECT * FROM caro_calendar WHERE LOWER(content) LIKE LOWER(CONCAT('%', :content, '%')) ORDER BY due ASC",
		],
		'calendar_delete' => [
			'mysql' => "DELETE FROM caro_calendar WHERE id = :id",
			'sqlsrv' => "DELETE FROM caro_calendar WHERE id = :id",
		],
	];
}
?>