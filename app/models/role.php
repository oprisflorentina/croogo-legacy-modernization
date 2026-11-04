<?php
/**
 * Role
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
class Role extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Role';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
	public $actsAs = [
		'Acl' => [
			'type' => 'requester',
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
			'notEmpty' => [
				'rule' => 'notEmpty',
				'message' => 'Alias cannot be empty.',
				'last' => true,
			],
			'validName' => [
				'rule' => '_validName',
				'message' => 'This field must be alphanumeric',
				'last' => true,
			],
		],
		'alias' => [
			'isUnique' => [
				'rule' => 'isUnique',
				'message' => 'This alias has already been taken.',
				'last' => true,
			],
			'notEmpty' => [
				'rule' => 'notEmpty',
				'message' => 'Alias cannot be empty.',
				'last' => true,
			],
			'validAlias' => [
				'rule' => '_validAlias',
				'message' => 'This field must be alphanumeric',
				'last' => true,
			],
		],
	];

	public function parentNode() {
		return null;
	}

}
