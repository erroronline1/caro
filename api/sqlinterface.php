<?php
class SQLQUERY {
	public static function PREPARE($context){
		return self::QUERIES[$context][INI['sql'][INI['sql']['use']]['driver']];
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
			'sqlsrv' => "SELECT TOP 1 * FROM caro_user WHERE CONVERT(VARCHAR, token) = :token"
		],

		'form_component-post' => [
			'mysql' => "INSERT INTO caro_form_components (id, name, date, content) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content)",
			'sqlsrv' => "INSERT INTO caro_form_components (name, date, content) VALUES (:name, CURRENT_TIMESTAMP, :content)"
		],
		'form_component-datalist' => [
			'mysql' => "SELECT name FROM caro_form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => "SELECT CONVERT(VARCHAR, name) as name FROM caro_form_components GROUP BY CONVERT(VARCHAR, name) ORDER BY CONVERT(VARCHAR, name) ASC"
		],
		'form_component-get' => [
			'mysql' => "SELECT name, content FROM caro_form_components WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 name, content FROM caro_form_components WHERE CONVERT(VARCHAR, name) = :name ORDER BY id DESC"
		],
		'form_datalist' => [
			'mysql' => "SELECT name FROM caro_form_forms GROUP BY name ORDER BY name ASC",
			'sqlsrv' => "SELECT CONVERT(VARCHAR, name) as name FROM caro_form_forms GROUP BY CONVERT(VARCHAR, name) ORDER BY CONVERT(VARCHAR, name) ASC"
		],
		'form_get' => [
			'mysql' => "SELECT name, content FROM caro_form_forms WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => "SELECT TOP 1 name, content FROM caro_form_forms WHERE CONVERT(VARCHAR, name) = :name ORDER BY id DESC"
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
			'mysql' => "SELECT name FROM caro_user ORDER BY name ASC",
			'sqlsrv' => "SELECT name FROM caro_user ORDER BY CONVERT(VARCHAR,name) ASC"
		],
		'user_get' => [
			'mysql' => "SELECT * FROM caro_user WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => "SELECT * FROM caro_user WHERE CONVERT(VARCHAR, id) = :id OR CONVERT(VARCHAR, name) = :id"
		],
		'user_delete' => [
			'mysql' => "DELETE FROM caro_user WHERE id = :id LIMIT 1",
			'sqlsrv' => "DELETE FROM caro_user WHERE id = :id"
		],

		'consumables_post-distributor' => [
			'mysql' => "INSERT INTO caro_consumables_distributors (id, active, name, info, certificate, pricelist, immutable_fileserver) VALUES ( NULL, :active, :name, :info, :certificate, :pricelist, :immutable_fileserver)",
			'sqlsrv' => ""
		],
		'consumables_put-distributor' => [
			'mysql' => "UPDATE caro_consumables_distributors SET active = :active, name = :name, info = :info, certificate = :certificate, pricelist = :pricelist WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'consumables_get-distributor-datalist' => [
			'mysql' => "SELECT name FROM caro_consumables_distributors ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'consumables_get-distributor' => [
			'mysql' => "SELECT * FROM caro_consumables_distributors WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'consumables_post-product' => [
			'mysql' => "INSERT INTO caro_consumables_products (id, distributor_id, article_no, article_name, article_unit, active, protected) VALUES (NULL, :distributor_id, :article_no, :article_name, :article_unit, :active, :protected)",
			'sqlsrv' => ""
		],
		'consumables_put-product' => [
			'mysql' => "UPDATE caro_consumables_products SET distributor_id = :distributor_id, article_no = :article_no, article_name = :article_name, article_unit = :article_unit, active = :active, protected = :protected WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'consumables_put-product-protected' => [
			'mysql' => "UPDATE caro_consumables_products SET article_name = :article_name, article_unit = :article_unit WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'consumables_get-product' => [
			'mysql' => "SELECT prod.*, dist.name as distributor_name, dist.immutable_fileserver as distributor_immutable_fileserver FROM caro_consumables_products AS prod, caro_consumables_distributors AS dist WHERE prod.id = :id AND prod.distributor_id = dist.id LIMIT 1",
			'sqlsrv' => ""
		],
		'consumables_get-product-units' => [
			'mysql' => "SELECT article_unit FROM caro_consumables_products GROUP BY article_unit ORDER BY article_unit ASC",
			'sqlsrv' => ""
		],
		'consumables_get-product-search' => [
			'mysql' => "SELECT prod.*, dist.name as distributor_name FROM caro_consumables_products AS prod, caro_consumables_distributors AS dist WHERE (prod.id = :search OR prod.article_no LIKE CONCAT('%', :search, '%') OR prod.article_name LIKE CONCAT('%', :search, '%')) AND prod.distributor_id = dist.id",
			'sqlsrv' => ""
		],
		'consumables_delete-all-unprotected-products' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE distributor_id = :id AND protected = 0",
			'sqlsrv' => ""
		],
		'consumables_delete-unprotected-product' => [
			'mysql' => "DELETE FROM caro_consumables_products WHERE id = :id AND protected = 0",
			'sqlsrv' => ""
		],

		'order_get-product-search' => [
			'mysql' => "SELECT prod.*, dist.name as distributor_name FROM caro_consumables_products AS prod, caro_consumables_distributors AS dist WHERE (prod.id = :search OR prod.article_no LIKE CONCAT('%', :search, '%') OR prod.article_name LIKE CONCAT('%', :search, '%')) AND prod.distributor_id = dist.id AND prod.active = 1",
			'sqlsrv' => ""
		],
		'order_post-prepared-order' => [
			'mysql' => "INSERT INTO caro_consumables_prepared_orders (id, order_data) VALUES (NULL, :order_data)",
			'sqlsrv' => ""
		],
		'order_put-prepared-order' => [
			'mysql' => "UPDATE caro_consumables_prepared_orders SET order_data = :order_data WHERE id = :id",
			'sqlsrv' => ""
		],
		'order_get-prepared-order' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'order_delete-prepared-order' => [
			'mysql' => "DELETE FROM caro_consumables_prepared_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],

		'order_get-prepared-orders' => [
			'mysql' => "SELECT * FROM caro_consumables_prepared_orders",
			'sqlsrv' => ""
		],

		'order_post-approved-order' => [
			'mysql' => "INSERT INTO caro_consumables_approved_orders (id, order_data, organizational_unit, approval, approved, ordered, received) VALUES (NULL, :order_data, :organizational_unit, :approval, CURRENT_TIMESTAMP, NULL, NULL)",
			'sqlsrv' => ""
		],
		'order_put-approved-order-ordered' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET ordered = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => ""
		],
		'order_put-approved-order-received' => [
			'mysql' => "UPDATE caro_consumables_approved_orders SET received = CURRENT_TIMESTAMP WHERE id = :id",
			'sqlsrv' => ""
		],
		'order_get-approved-order' => [
			'mysql' => "SELECT * FROM caro_consumables_approved_orders WHERE organizational_unit IN (:organizational_unit)",
			'sqlsrv' => ""
		],
		'order_delete-approved-order' => [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],

		'message_get_message' => [
			'mysql' => "SELECT t1.*, t2.name as from_user, t3.name as to_user FROM caro_messages as t1, caro_user as t2, caro_user as t3 WHERE t1.id = :id AND t1.from_user = t2.id AND t1.to_user = t3.id LIMIT 1",
			'sqlsrv' => ""
		],
		'message_post_message' => [
			'mysql' => "INSERT INTO caro_messages (id, from_user, to_user, message, timestamp, alert) VALUES (NULL, :from_user, :to_user, :message, CURRENT_TIMESTAMP, 0)",
			'sqlsrv' => ""
		],
		'message_delete_message' => [
			'mysql' => "DELETE FROM caro_messages WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'message_get_inbox' => [
			'mysql' => "SELECT t1.*, t2.name as from_user FROM caro_messages as t1, caro_user as t2 WHERE t1.to_user = :user AND t1.from_user = t2.id ORDER BY t1.timestamp DESC",
			'sqlsrv' => ""
		],
		'message_get_sent' => [
			'mysql' => "SELECT t1.*, t2.name as to_user FROM caro_messages as t1, caro_user as t2 WHERE t1.from_user = :user AND t1.to_user = t2.id ORDER BY t1.timestamp DESC",
			'sqlsrv' => ""
		],
	];
}
?>