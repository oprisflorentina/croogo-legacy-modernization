<?php
class AclArosAco extends AppModel {

	public $name = 'AclArosAco';
	public $useTable = 'aros_acos';

	public $belongsTo = [
		'AclAro' => [
			'className' => 'Acl.AclAro',
			'foreignKey' => 'aro_id',
		],
		'AclAco' => [
			'className' => 'Acl.AclAco',
			'foreignKey' => 'aco_id',
		],
	];

}
