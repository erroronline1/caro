<?php
class SQLQUERY {
	public static function PREPARE($context){
		return self::QUERIES[$context][INI['sql']['driver']];
	}

	public const QUERIES = [
		/*'context' => [
			'mysql' => "SELECT age FROM person ORDER BY age ASC LIMIT 3",
			'sqlsrv' => "SELECT TOP 3 WITH TIES * FROM person ORDER BY age ASC"
		],*/
		'application_login' => [
			'mysql' => "SELECT * FROM caro_user WHERE token = :token LIMIT 1",
			'sqlsrv' => ""
		],

		'form_component-post' => [
			'mysql' => "INSERT INTO caro_form_components (id, name, date, content) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content)",
			'sqlsrv' => ""
		],
		'form_component-datalist' => [
			'mysql' => "SELECT name FROM caro_form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_component-get' => [
			'mysql' => "SELECT name, content FROM caro_form_components WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_datalist' => [
			'mysql' => "SELECT name FROM caro_form_forms GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_get' => [
			'mysql' => "SELECT name, content FROM caro_form_forms WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_post' => [
			'mysql' => "",
			'sqlsrv' => ""
		],

		'user_post' => [
			'mysql' => "INSERT INTO caro_user (id, name, permissions, units, token, image) VALUES ( NULL, :name, :permissions, :units, :token, :image)",
			'sqlsrv' => ""
		],
		'user_put' => [
			'mysql' => "UPDATE caro_user SET name = :name, permissions = :permissions, units = :units, token = :token, image = :image WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_get-datalist' => [
			'mysql' => "SELECT name FROM caro_user ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'user_get' => [
			'mysql' => "SELECT * FROM caro_user WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete' => [
			'mysql' => "DELETE FROM caro_user WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
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

	];

}
?>