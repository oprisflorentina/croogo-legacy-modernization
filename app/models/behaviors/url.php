<?php
/**
 * Url Behavior
 *
 * PHP version 5
 *
 * @category Behavior
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class UrlBehavior extends ModelBehavior {
/**
 * Setup
 *
 * @param object $model
 * @param array  $config
 * @return void
 */
	public function setup(&$model, $config = []) {
		$_config = [
			'url' => [
				'plugin' => false,
				'controller' => 'nodes',
				'action' => 'view',
			],
			'fields' => [
				'type',
				'slug',
			],
		];

		if (is_string($config)) {
			$config = [$config];
		}

		$config = array_merge($_config, $config);

		$this->settings[$model->alias] = $config;
	}

/**
 * afterFind callback
 *
 * @param object  $model
 * @param array   $created
 * @param boolean $primary
 * @return array
 */
	public function afterFind(&$model, $results = [], $primary = false) {
		if ($primary && isset($results[0][$model->alias])) {
			foreach ($results AS $i => $result) {
				$url = $this->settings[$model->alias]['url'];
				$fields = $this->settings[$model->alias]['fields'];
				if (is_array($fields)) {
					foreach ($fields AS $field) {
						if (isset($results[$i][$model->alias][$field])) {
							$url[$field] = $results[$i][$model->alias][$field];
						}
					}
				}
				$results[$i][$model->alias]['url'] = $url;
			}
		} elseif (isset($results[$model->alias])) {
			$url = $this->settings[$model->alias]['url'];
			$fields = $this->settings[$model->alias]['fields'];
			if (is_array($fields)) {
				foreach ($fields AS $field) {
					if (isset($results[$i][$model->alias][$field])) {
						$url[$field] = $results[$i][$model->alias][$field];
					}
				}
			}
			$results[$model->alias]['url'] = $url;
		}

		return $results;
	}

}
