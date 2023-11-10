<?php
class SQLQUERY {
	public static function PREPARE($context){
		return self::QUERIES[$context][INI['sql']['driver']];
	}

	public static function SANITIZE($text){
		return addslashes(trim($text));
	}

	public const QUERIES = [
		/*'context' => [
			'mysql' => "SELECT age FROM person ORDER BY age ASC LIMIT 3",
			'sqlsrv' => "SELECT TOP 3 WITH TIES * FROM person ORDER BY age ASC"
		],*/
		'application_login' => [
			'mysql' => "SELECT * FROM webqs_users WHERE token = :token LIMIT 1",
			'sqlsrv' => ""
		],

		'form_component-post' => [
			'mysql' => "INSERT INTO webqs_form_components (id, name, date, content) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content)",
			'sqlsrv' => ""
		],
		'form_component-datalist' => [
			'mysql' => "SELECT name FROM webqs_form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_component-get' => [
			'mysql' => "SELECT name, content FROM webqs_form_components WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_datalist' => [
			'mysql' => "SELECT name FROM webqs_forms GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_get' => [
			'mysql' => "SELECT name, content FROM webqs_forms WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_post' => [
			'mysql' => "",
			'sqlsrv' => ""
		],

		'user_post' => [
			'mysql' => "INSERT INTO webqs_users (id, name, permissions, token, image) VALUES ( NULL, :name, :permissions, :token, :image)",
			'sqlsrv' => ""
		],
		'user_put' => [
			'mysql' => "UPDATE webqs_users SET name = :name, permissions = :permissions, token = :token, image = :image WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_get-datalist' => [
			'mysql' => "SELECT name FROM webqs_users ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'user_get' => [
			'mysql' => "SELECT * FROM webqs_users WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_get-id' => [
			'mysql' => "SELECT * FROM webqs_users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete-prefetch' => [
			'mysql' => "SELECT id, name FROM webqs_users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete' => [
			'mysql' => "DELETE FROM webqs_users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],

		'purchase_post-distributor' => [
			'mysql' => "INSERT INTO webqs_distributors (id, name, info, certificate_validity, certificate_path, pricelist_validity, pricelist_filter) VALUES ( NULL, :name, :info, :certificate_validity, :certificate_path, :pricelist_validity, :pricelist_filter)",
			'sqlsrv' => ""
		],
		'purchase_put-distributor' => [
			'mysql' => "UPDATE webqs_distributors SET name = :name, info = :info, certificate_validity = :certificate_validity, certificate_path = :certificate_path, pricelist_validity = :pricelist_validity, pricelist_filter = :pricelist_filter WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'purchase_get-distributor-datalist' => [
			'mysql' => "SELECT name FROM webqs_distributors ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'purchase_get-distributor' => [
			'mysql' => "SELECT * FROM webqs_distributors WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'purchase_get-distributor-id' => [
			'mysql' => "SELECT * FROM webqs_distributors WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'purchase_delete-distributor-prefetch' => [
			'mysql' => "SELECT id, name FROM webqs_distributors WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'purchase_delete-distributor' => [
			'mysql' => "DELETE FROM webqs_distributors WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'purchase_delete-purchase-items' => [
			'mysql' => "DELETE FROM webqs_purchase_items WHERE distributor_id = :id",
			'sqlsrv' => ""
		],
		'purchase_insert-purchase-items' => [
			'mysql' => "INSERT INTO webqs_purchase_items (distributor_id, article_no, article_name, article_unit) VALUES (:distributor_id, :article_no, :article_name, :article_unit)",
			'sqlsrv' => ""
		],

	];

}
?>