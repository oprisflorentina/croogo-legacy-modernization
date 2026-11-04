<?php
/**
 * Language
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
class Language extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Language';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
	public $actsAs = [
		'Ordered' => ['field' => 'weight', 'foreign_key' => null],
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

}
