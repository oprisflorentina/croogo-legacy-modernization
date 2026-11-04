<?php
/**
 * Configuration
 */
	Configure::write('Tinymce.actions', [
		'Nodes/admin_add' => [
			[
				'elements' => 'NodeBody',
			],
		],
		'Nodes/admin_edit' => [
			[
				'elements' => 'NodeBody',
			],
		],
		'Translate/admin_edit' => [
			[
				'elements' => 'NodeBody',
			],
		],
	]);

/**
 * Hook helper
 */
	foreach (Configure::read('Tinymce.actions') AS $action => $settings) {
		$actionE = explode('/', $action);
		Croogo::hookHelper($actionE['0'], 'Tinymce.Tinymce');
	}
	Croogo::hookHelper('Attachments', 'Tinymce.Tinymce');

