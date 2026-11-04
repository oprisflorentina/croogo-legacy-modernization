<?php
/* App schema generated on: 2011-03-28 03:44:07 : 1301280247*/
class AppSchema extends CakeSchema {
	public $name = 'App';

	public function before($event = []) {
		return true;
	}

	public function after($event = []) {
	}

	public $acos = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'parent_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'model' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'foreign_key' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'alias' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'lft' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'rght' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $aros = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'parent_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'model' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'foreign_key' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'alias' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'lft' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'rght' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $aros_acos = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'aro_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10],
		'aco_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10],
		'_create' => ['type' => 'string', 'null' => false, 'default' => '0', 'length' => 2, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'_read' => ['type' => 'string', 'null' => false, 'default' => '0', 'length' => 2, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'_update' => ['type' => 'string', 'null' => false, 'default' => '0', 'length' => 2, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'_delete' => ['type' => 'string', 'null' => false, 'default' => '0', 'length' => 2, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $blocks = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'region_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 20],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'body' => ['type' => 'text', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'show_title' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'class' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'element' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'visibility_roles' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'visibility_paths' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'visibility_php' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'params' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'alias' => ['column' => 'alias', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $comments = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'parent_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 20],
		'node_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20],
		'user_id' => ['type' => 'integer', 'null' => false, 'default' => '0', 'length' => 20],
		'name' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 50, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'email' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'website' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 200, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'ip' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'title' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'body' => ['type' => 'text', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'rating' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'notify' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'type' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'comment_type' => ['type' => 'string', 'null' => false, 'default' => 'comment', 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'lft' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'rght' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $contacts = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'body' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'name' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'position' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'address' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'address2' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'state' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'country' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'postcode' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'phone' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'fax' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'email' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'message_status' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'message_archive' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'message_count' => ['type' => 'integer', 'null' => false, 'default' => '0'],
		'message_spam_protection' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'message_captcha' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'message_notify' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $i18n = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'locale' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 6, 'key' => 'index', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'model' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'index', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'foreign_key' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'index'],
		'field' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'index', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'content' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'locale' => ['column' => 'locale', 'unique' => 0], 'model' => ['column' => 'model', 'unique' => 0], 'row_id' => ['column' => 'foreign_key', 'unique' => 0], 'field' => ['column' => 'field', 'unique' => 0]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $languages = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'native' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $links = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'parent_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 20],
		'menu_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'class' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'link' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'target' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'rel' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'lft' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'rght' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'visibility_roles' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'params' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $menus = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'class' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'link_count' => ['type' => 'integer', 'null' => false, 'default' => NULL],
		'params' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'alias' => ['column' => 'alias', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $messages = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'key' => 'primary'],
		'contact_id' => ['type' => 'integer', 'null' => false, 'default' => NULL],
		'name' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'email' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'title' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'body' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'website' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'phone' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'address' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'message_type' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $meta = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'model' => ['type' => 'string', 'null' => false, 'default' => 'Node', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'foreign_key' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 20],
		'key' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'value' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $nodes = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'parent_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 20],
		'user_id' => ['type' => 'integer', 'null' => false, 'default' => '0', 'length' => 20],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'slug' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'body' => ['type' => 'text', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'excerpt' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'mime_type' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'comment_status' => ['type' => 'integer', 'null' => false, 'default' => '1', 'length' => 1],
		'comment_count' => ['type' => 'integer', 'null' => true, 'default' => '0'],
		'promote' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'path' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'terms' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'sticky' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'lft' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'rght' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'visibility_roles' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'type' => ['type' => 'string', 'null' => false, 'default' => 'node', 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $nodes_taxonomies = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'node_id' => ['type' => 'integer', 'null' => false, 'default' => '0', 'length' => 20],
		'taxonomy_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $regions = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'block_count' => ['type' => 'integer', 'null' => false, 'default' => '0'],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'alias' => ['column' => 'alias', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $roles = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'created' => ['type' => 'datetime', 'null' => true, 'default' => NULL],
		'updated' => ['type' => 'datetime', 'null' => true, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'alias' => ['column' => 'alias', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $settings = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'key' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 64, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'value' => ['type' => 'text', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'input_type' => ['type' => 'string', 'null' => false, 'default' => 'text', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'editable' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'params' => ['type' => 'text', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'key' => ['column' => 'key', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $taxonomies = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'parent_id' => ['type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 20],
		'term_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10],
		'vocabulary_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10],
		'lft' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'rght' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $terms = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'slug' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'slug' => ['column' => 'slug', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $types = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'format_show_author' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'format_show_date' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'comment_status' => ['type' => 'integer', 'null' => false, 'default' => '1', 'length' => 1],
		'comment_approve' => ['type' => 'boolean', 'null' => false, 'default' => '1'],
		'comment_spam_protection' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'comment_captcha' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'params' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'plugin' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'alias' => ['column' => 'alias', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $types_vocabularies = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'type_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10],
		'vocabulary_id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $users = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 20, 'key' => 'primary'],
		'role_id' => ['type' => 'integer', 'null' => false, 'default' => NULL],
		'username' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 60, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'password' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'name' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 50, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'email' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'website' => ['type' => 'string', 'null' => true, 'default' => NULL, 'length' => 100, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'activation_key' => ['type' => 'string', 'null' => false, 'default' => NULL, 'length' => 60, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'image' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'bio' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'timezone' => ['type' => 'string', 'null' => false, 'default' => '0', 'length' => 10, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'status' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
	public $vocabularies = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'],
		'title' => ['type' => 'string', 'null' => false, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'alias' => ['type' => 'string', 'null' => false, 'default' => NULL, 'key' => 'unique', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'description' => ['type' => 'text', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'required' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'multiple' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'tags' => ['type' => 'boolean', 'null' => false, 'default' => '0'],
		'plugin' => ['type' => 'string', 'null' => true, 'default' => NULL, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
		'weight' => ['type' => 'integer', 'null' => true, 'default' => NULL],
		'updated' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'created' => ['type' => 'datetime', 'null' => false, 'default' => NULL],
		'indexes' => ['PRIMARY' => ['column' => 'id', 'unique' => 1], 'alias' => ['column' => 'alias', 'unique' => 1]],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_unicode_ci', 'engine' => 'MyISAM']
	];
}
