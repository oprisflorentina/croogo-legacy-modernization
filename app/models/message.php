<?php
/**
 * Message
 *
 * PHP version 5
 *
 * @category Model
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class Message extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Message';

/**
 * Validation
 *
 * @var array
 * @access public
 */
	public $validate = [
		'name' => [
			'rule' => 'notEmpty',
			'message' => 'This field cannot be left blank.',
		],
		'email' => [
			'rule' => 'email',
			'message' => 'Please provide a valid email address.',
		],
		'title' => [
			'rule' => 'notEmpty',
			'message' => 'This field cannot be left blank.',
		],
		'body' => [
			'rule' => 'notEmpty',
			'message' => 'This field cannot be left blank.',
		],
	];

/**
 * Model associations: belongsTo
 *
 * @var array
 * @access public
 */
	public $belongsTo = [
		'Contact' => [
			'className' => 'Contact',
			'foreignKey' => 'contact_id',
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'counterCache' => true,
		],
	];

}
