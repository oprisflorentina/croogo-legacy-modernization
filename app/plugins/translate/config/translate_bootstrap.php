<?php
/**
 * Configuration
 *
 */
	Configure::write('Translate.models', [
		'Node' => [
			'title' => 'titleTranslation',
			'excerpt' => 'excerptTranslation',
			'body' => 'bodyTranslation',
		],
		'Block' => [
			'title' => 'titleTranslation',
			'body' => 'bodyTranslation',
		],
		'Link' => [
			'title' => 'titleTranslation',
			'description' => 'descriptionTranslation',
		],
	]);

/**
 * Do not edit below this line unless you know what you are doing.
 *
 */
	foreach (Configure::read('Translate.models') AS $translateModel => $fields) {
		Croogo::hookBehavior($translateModel, 'CroogoTranslate', $fields);
		Croogo::hookAdminRowAction(Inflector::pluralize($translateModel) . '/admin_index', 'Translate', 'plugin:translate/controller:translate/action:index/:id/'.$translateModel);
	}
