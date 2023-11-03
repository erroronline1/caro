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
			'mysql' => "SELECT * FROM users WHERE token = :token LIMIT 1",
			'sqlsrv' => ""
		],

		'form_component-post' => [
			'mysql' => "INSERT INTO form_components (id, name, date, content) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content)",
			'sqlsrv' => ""
		],
		'form_component-datalist' => [
			'mysql' => "SELECT name FROM form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_component-get' => [
			'mysql' => "SELECT name, content FROM form_components WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_datalist' => [
			'mysql' => "SELECT name FROM forms GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_get' => [
			'mysql' => "SELECT name, content FROM forms WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_post' => [
			'mysql' => "",
			'sqlsrv' => ""
		],

		'user_post' => [
			'mysql' => "INSERT INTO users (id, name, permissions, token, image) VALUES ( NULL, :name, :permissions, :token, :image)",
			'sqlsrv' => ""
		],
		'user_put' => [
			'mysql' => "UPDATE users SET name = :name, permissions = :permissions, token = :token, image = :image WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_get-datalist' => [
			'mysql' => "SELECT name FROM users ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'user_get' => [
			'mysql' => "SELECT * FROM users WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_get-id' => [
			'mysql' => "SELECT * FROM users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete-prefetch' => [
			'mysql' => "SELECT id, name FROM users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete' => [
			'mysql' => "DELETE FROM users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
	];

}
?>