<?php
/**
 * Block
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
class Block extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Block';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
	public $actsAs = [
		'Encoder',
		'Ordered' => [
			'field' => 'weight',
			'foreign_key' => false,
		],
		'Cached' => [
			'prefix' => [
				'block_',
				'blocks_',
				'croogo_blocks_',
			],
		],
		'Params',
	];

/**
 * Validation
 *
 * @var array
 * @access public
 */
	public $validate = [
		'title' => [
			'rule' => ['minLength', 1],
			'message' => 'Title cannot be empty.',
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
	];

/**
 * Model associations: belongsTo
 *
 * @var array
 * @access public
 */
	public $belongsTo = [
		'Region' => [
			'className' => 'Region',
			'foreignKey' => 'region_id',
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'counterCache' => true,
			'counterScope' => ['Block.status' => 1],
		],
	];

}
