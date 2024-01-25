<?php
class SQLQUERY {
	public static function PREPARE($context){
		return self::QUERIES[$context][INI['sql'][INI['sql']['use']]['driver']];
	}

	public static function CHUNKIFY($chunks, $query = null){
		if ($query){
			$chunkIndex=count($chunks)-1;
			if (array_key_exists($chunkIndex, $chunks)){
				if (strlen($chunks[$chunkIndex] . $query) < INI['sql'][INI['sql']['use']]['packagesize']) $chunks[$chunkIndex] .= $query;
				else $chunks[] = $query;
			}
			else $chunks[] = $query;
		}
		return $chunks;
	}

	public const QUERIES = [
		/*'context' => [
			'mysql' => "SELECT age FROM person ORDER BY age ASC LIMIT 3",
			'sqlsrv' => "SELECT TOP 3 WITH TIES * FROM person ORDER BY age ASC"
		],*/
		'INSTALL' =>[
			'mysql' => "",
			'sqlsrv' => ""
		],

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

		'form_component-post' => [
			'mysql' => "INSERT INTO caro_form_components (id, name, date, content) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content)",
			'sqlsrv' => "INSERT INTO caro_form_components (name, date, content) VALUES (:name, CURRENT_TIMESTAMP, :content)"
		],
		'form_component-datalist' => [
			'mysql' => "SELECT name FROM caro_form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => "SELECT name FROM caro_form_components GROUP BY name ORDER BY name ASC"
		],
		'form_component-get' => [
			'mysql' => "SELECT name, content FROM caro_form_components WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 name, content FROM caro_form_components WHERE name = :name ORDER BY id DESC"
		],
		'form_datalist' => [
			'mysql' => "SELECT name FROM caro_form_forms GROUP BY name ORDER BY name ASC",
			'sqlsrv' => "SELECT name FROM caro_form_forms GROUP BY name ORDER BY name ASC"
		],
		'form_get' => [
			'mysql' => "SELECT name, content FROM caro_form_forms WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 name, content FROM caro_form_forms WHERE name = :name ORDER BY id DESC"
		],
		'form_post' => [
			'mysql' => "",
			'sqlsrv' => ""
		],

		'user_post' => [
			'mysql' => "INSERT INTO caro_user (id, name, permissions, units, token, image) VALUES ( NULL, :name, :permissions, :units, :token, :image)",
			'sqlsrv' => "INSERT INTO caro_user (name, permissions, units, token, image) VALUES ( :name, :permissions, :units, :token, :image)"
		],
		'user_put' => [
			'mysql' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, image = :image WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, image = :image WHERE id = :id"
		],
		'user_get-datalist' => [
			'mysql' => "SELECT id, name FROM caro_user ORDER BY name ASC",
			'sqlsrv' => "SELECT id, name FROM caro_user ORDER BY name ASC"
		],
		'user_get' => [
			'mysql' => "SELECT * FROM caro_user WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => "SELECT * FROM caro_user WHERE CONVERT(VARCHAR, id) = :id OR name = :id"
		],
		'user_delete' => [
			'mysql' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id",
			'sqlsrv' => "DELETE FROM caro_user WHERE id = :id; DELETE FROM caro_messages WHERE user_id = :id"
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
			'mysql' => "SELECT name FROM caro_consumables_vendors ORDER BY name ASC",
			'sqlsrv' => "SELECT name as name FROM caro_consumables_vendors ORDER BY name ASC"
		],
		'consumables_get-vendor' => [
			'mysql' => "SELECT * FROM caro_consumables_vendors WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => "SELECT * FROM caro_consumables_vendors WHERE CONVERT(VARCHAR, id) = :id OR name = :id"
		],
		'consumables_post-product' => [
			'mysql' => "INSERT INTO caro_consumables_products (id, vendor_id, article_no, article_name, article_alias, article_unit, article_ean, active, protected) VALUES (NULL, :vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :active, :protected)",
			'sqlsrv' => "INSERT INTO caro_consumables_products (vendor_id, article_no, article_name, article_alias, article_unit, article_ean, active, protected) VALUES (:vendor_id, :article_no, :article_name, :article_alias, :article_unit, :article_ean, :active, :protected)"
		],
		'consumables_put-product' => [
			'mysql' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, active = :active, protected = :protected WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_consumables_products SET vendor_id = :vendor_id, article_no = :article_no, article_name = :article_name, article_alias = :article_alias, article_unit = :article_unit, article_ean = :article_ean, active = :active, protected = :protected WHERE id = :id"
		],
		'consumables_put-product-protected' => [
			'mysql' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean WHERE id = :id LIMIT 1",
			'sqlsrv' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit, article_ean = :article_ean WHERE id = :id"
		],
		'consumables_get-product' => [
			'mysql' => "SELECT prod.*, dist.name as vendor_name, dist.immutable_fileserver as vendor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE prod.id = :id AND prod.vendor_id = dist.id LIMIT 1",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name, dist.immutable_fileserver as vendor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE CONVERT(VARCHAR, prod.id) = :id AND prod.vendor_id = dist.id"
		],
		'consumables_get-product-units' => [
			'mysql' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC",
			'sqlsrv' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC"
		],
		'consumables_get-product-search' => [
			'mysql' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (prod.id = :search OR LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id = dist.id",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (CONVERT(VARCHAR, prod.id) = :search OR LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id = dist.id"
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
			'mysql' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (prod.id = :search OR LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id = dist.id AND prod.active = 1",
			'sqlsrv' => "SELECT prod.*, dist.name as vendor_name FROM caro_consumables_products AS prod, caro_consumables_vendors AS dist WHERE (CONVERT(VARCHAR, prod.id) = :search OR LOWER(prod.article_no) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_name) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_alias) LIKE LOWER(CONCAT('%', :search, '%')) OR LOWER(prod.article_ean) LIKE LOWER(CONCAT('%', :search, '%'))) AND prod.vendor_id = dist.id AND prod.active = 1"
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
		'order_delete-prepared-order' => [
			'mysql' => "DELETE FROM caro_consumables_prepared_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => "DELETE FROM caro_consumables_prepared_orders WHERE id = :id"
		],

		'order_get-prepared-orders' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders",
			'sqlsrv' => "SELECT * FROM caro_consumables_prepared_orders"
		],

		'order_post-approved-order' => [
			'mysql' => "INSERT INTO caro_consumables_approved_orders (id, order_data, organizational_unit, approval, approved, ordered, received, archived) VALUES (NULL, :order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL)",
			'sqlsrv' => "INSERT INTO caro_consumables_approved_orders (order_data, organizational_unit, approval, approved, ordered, received, archived) VALUES (:order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL, NULL)"
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
		'order_get-approved-order-by-unit' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) ORDER BY id DESC",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) ORDER BY id DESC"
		],
		'order_get-approved-order-by-id' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE id = :id",
			'sqlsrv' => "SELECT * FROM caro_consumables_approved_orders WHERE id = :id"
		],
		'order_delete-approved-order' => [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id"
		],
		'order_get_filter' => [
			'mysql' => "SELECT id FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) AND LOWER(order_data) LIKE LOWER(CONCAT('%', :orderfilter, '%'))",
			'sqlsrv' => "SELECT id FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit) AND LOWER(order_data) LIKE LOWER(CONCAT('%', :orderfilter, '%'))"
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
			'mysql' => "SELECT caro_messages.*, caro_user.name as from_user, caro_user.image FROM caro_messages LEFT JOIN caro_user on caro_messages.from_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.to_user = :user ORDER BY caro_messages.timestamp DESC",
			'sqlsrv' => "SELECT caro_messages.*, caro_user.name as from_user, caro_user.image FROM caro_messages LEFT JOIN caro_user on caro_messages.from_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.to_user = :user ORDER BY caro_messages.timestamp DESC"
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
			'mysql' => "SELECT caro_messages.*, caro_user.name as to_user, caro_user.image FROM caro_messages LEFT JOIN caro_user on caro_messages.to_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.from_user = :user ORDER BY caro_messages.timestamp DESC",
			'sqlsrv' => "SELECT caro_messages.*, caro_user.name as to_user, caro_user.image FROM caro_messages LEFT JOIN caro_user on caro_messages.to_user=caro_user.id WHERE caro_messages.user_id = :user AND caro_messages.from_user = :user ORDER BY caro_messages.timestamp DESC"
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

	];
}
?>