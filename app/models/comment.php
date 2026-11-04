<?php
/**
 * Comment
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
class Comment extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Comment';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
	public $actsAs = [
		'Tree',
		'Containable',
		'Cached' => [
			'prefix' => [
				'comment_',
				'nodes_',
				'node_',
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
		'body' => [
			'rule' => 'notEmpty',
			'message' => 'This field cannot be left blank.',
		],
		'name' => [
			'rule' => 'notEmpty',
			'message' => 'This field cannot be left blank.',
		],
		'email' => [
			'rule' => 'email',
			'required' => true,
			'message' => 'Please enter a valid email address.',
		],
	];

/**
 * Model associations: belongsTo
 *
 * @var array
 * @access public
 */
	public $belongsTo = [
		'Node' => [
			'counterCache' => true,
			'counterScope' => ['Comment.status' => 1],
		],
		'User',
	];

}
