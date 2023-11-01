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
		'form_components_save' => [
			'mysql' => "INSERT INTO form_components (id, name, date, content) VALUES (NULL, :name, CURRENT_TIMESTAMP, :content)",
			'sqlsrv' => ""
		],
		'form_components_edit-datalist' => [
			'mysql' => "SELECT name FROM form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_components_edit-selected' => [
			'mysql' => "SELECT name, content FROM form_components WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_edit-datalist' => [
			'mysql' => "SELECT name FROM forms GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_edit-components_datalist' => [
			'mysql' => "SELECT name FROM form_components GROUP BY name ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'form_edit-selected' => [
			'mysql' => "SELECT name, content FROM forms WHERE name = :name ORDER BY id DESC LIMIT 1",
			'sqlsrv' => ""
		],
		'form_components_add' => [
			'mysql' => "SELECT name, content FROM form_components WHERE name = :name ORDER BY id DESC",
			'sqlsrv' => ""
		],

		'user_save-get_by_id' => [
			'mysql' => "SELECT * FROM users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_save' => [
			'mysql' => "INSERT INTO users (id, name, permissions, token, image) VALUES ( :id, :name, :permissions, :token, :image) ON DUPLICATE KEY UPDATE name = :name, permissions = :permissions, token = :token, image = :image",
			'sqlsrv' => ""
		],
		'user_current' => [
			'mysql' => "SELECT * FROM users WHERE token = :token LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete-selected' => [
			'mysql' => "SELECT id, name FROM users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_delete' => [
			'mysql' => "DELETE FROM users WHERE id = :id LIMIT 1",
			'sqlsrv' => ""
		],
		'user_edit-datalist' => [
			'mysql' => "SELECT name FROM users ORDER BY name ASC",
			'sqlsrv' => ""
		],
		'user_edit-selected' => [
			'mysql' => "SELECT * FROM users WHERE id = :id OR name = :id LIMIT 1",
			'sqlsrv' => ""
		],
	];

}
?>