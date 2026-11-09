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
		'website' => [
			'rule' => '_validWebsite',
			'allowEmpty' => true,
			'message' => 'Please enter a web address starting with http:// or https://.',
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

/**
 * The website becomes a link on the commenter's name, so only web addresses
 * are accepted: a javascript: address would run in the browser of whoever
 * clicks the name. A path on this site, like the sample admin's "/about", is
 * accepted too, because a logged-in user's comment takes the website from
 * their profile.
 *
 * @param array $check Field name => value
 * @return boolean
 */
	protected function _validWebsite($check) {
		$website = current($check);
		if (!is_string($website)) {
			return false;
		}
		if (preg_match('#^/(?!/)#', $website)) {
			return true;
		}
		return preg_match('#^https?://#i', $website) === 1 && filter_var($website, FILTER_VALIDATE_URL) !== false;
	}

}
