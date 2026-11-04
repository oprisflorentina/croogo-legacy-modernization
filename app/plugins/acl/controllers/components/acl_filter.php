<?php
/**
 * AclFilter Component
 *
 * PHP version 5
 *
 * @category Component
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class AclFilterComponent extends CakeObject {

/**
 * @param object $controller controller
 * @param array  $settings   settings
 */
	public function initialize(&$controller, $settings = []) {
		$this->controller =& $controller;
	}

/**
 * acl and auth
 *
 * @return void
 */
	public function auth() {
		//Configure AuthComponent
		$this->controller->Auth->authorize = 'actions';
		$this->controller->Auth->loginAction = [
			'plugin' => null,
			'controller' => 'users',
			'action' => 'login',
		];
		$this->controller->Auth->logoutRedirect = [
			'plugin' => null,
			'controller' => 'users',
			'action' => 'login',
		];
		$this->controller->Auth->loginRedirect = [
			'plugin' => null,
			'controller' => 'users',
			'action' => 'index',
		];
		$this->controller->Auth->userScope = [
			'User.status' => 1,
		];
		$this->controller->Auth->actionPath = 'controllers/';

		if ($this->controller->Auth->user() && $this->controller->Auth->user('role_id') == 1) {
			// Role: Admin
			$this->controller->Auth->allowedActions = ['*'];
		} else {
			if ($this->controller->Auth->user()) {
				$roleId = $this->controller->Auth->user('role_id');
			} else {
				$roleId = 3; // Role: Public
			}

			$aro = $this->controller->Acl->Aro->find('first', [
				'conditions' => [
					'Aro.model' => 'Role',
					'Aro.foreign_key' => $roleId,
				],
				'recursive' => -1,
			]);
			$aroId = $aro['Aro']['id'];
			$thisControllerNode = $this->controller->Acl->Aco->node($this->controller->Auth->actionPath.$this->controller->name);
			if ($thisControllerNode) {
				$thisControllerNode = $thisControllerNode['0'];
				$thisControllerActions = $this->controller->Acl->Aco->find('list', [
					'conditions' => [
						'Aco.parent_id' => $thisControllerNode['Aco']['id'],
					],
					'fields' => [
						'Aco.id',
						'Aco.alias',
					],
					'recursive' => '-1',
				]);
				$thisControllerActionsIds = array_keys($thisControllerActions);
				$allowedActions = $this->controller->Acl->Aco->Permission->find('list', [
					'conditions' => [
						'Permission.aro_id' => $aroId,
						'Permission.aco_id' => $thisControllerActionsIds,
						'Permission._create' => 1,
						'Permission._read' => 1,
						'Permission._update' => 1,
						'Permission._delete' => 1,
					],
					'fields' => [
						'id',
						'aco_id',
					],
					'recursive' => '-1',
				]);
				$allowedActionsIds = array_values($allowedActions);
			}

			$allow = [];
			if (isset($allowedActionsIds) &&
				is_array($allowedActionsIds) &&
				count($allowedActionsIds) > 0) {
				foreach ($allowedActionsIds AS $i => $aId) {
					$allow[] = $thisControllerActions[$aId];
				}
			}
			$this->controller->Auth->allowedActions = $allow;
		}
	}

}
