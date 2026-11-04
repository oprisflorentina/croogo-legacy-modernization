<?php
/**
 * Type
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
class Type extends AppModel {
/**
 * Model name
 *
 * @var string
 * @access public
 */
	public $name = 'Type';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
	public $actsAs = [
		'Cached' => [
			'prefix' => [
				'croogo_types_',
				'types_',
				'type_',
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
 * Model associations: hasAndBelongsToMany
 *
 * @var array
 * @access public
 */
	public $hasAndBelongsToMany = [
		'Vocabulary' => [
			'className' => 'Vocabulary',
			'joinTable' => 'types_vocabularies',
			'foreignKey' => 'type_id',
			'associationForeignKey' => 'vocabulary_id',
			'unique' => true,
			'conditions' => '',
			'fields' => '',
			'order' => 'Vocabulary.weight ASC',
			'limit' => '',
			'offset' => '',
			'finderQuery' => '',
			'deleteQuery' => '',
			'insertQuery' => '',
		],
	];
}
