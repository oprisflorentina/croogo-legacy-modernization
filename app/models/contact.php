<?php
/**
 * Contact
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
class Contact extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Contact';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
	public $actsAs = [
		'Cached' => [
			'prefix' => [
				'contact_',
			],
		],
	];

/**
 * Validation
 *
 * @var array
 * @access public
 */
	public $validate = [
		'title' => [
			'rule' => 'notEmpty',
			'message' => 'This field cannot be left blank.',
		],
		'alias' => [
			'isUnique' => [
				'rule' => 'isUnique',
				'message' => 'This alias has already been taken.',
			],
			'minLength' => [
				'rule' => ['minLength', 1],
				'message' => 'Alias cannot be empty.',
			],
		],
		'email' => [
			'rule' => 'email',
			'message' => 'Please provide a valid email address.',
		],
	];

/**
 * Model associations: hasMany
 *
 * @var array
 * @access public
 */
	public $hasMany = [
		'Message' => [
			'className' => 'Message',
			'foreignKey' => 'contact_id',
			'dependent' => false,
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'limit' => '3',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => '',
		],
	];
}
