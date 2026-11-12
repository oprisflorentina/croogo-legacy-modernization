<?php
	CroogoRouter::plugins();
	Router::parseExtensions('json', 'rss');

	// Installer
	if (!file_exists(CONFIGS.'settings.yml')) {
		CroogoRouter::connect('/', ['plugin' => 'install' ,'controller' => 'install']);
	}

	// Basic
	CroogoRouter::connect('/', ['controller' => 'nodes', 'action' => 'promoted']);
	CroogoRouter::connect('/promoted/*', ['controller' => 'nodes', 'action' => 'promoted']);
	CroogoRouter::connect('/admin', ['admin' => true, 'controller' => 'settings', 'action' => 'dashboard']);
	CroogoRouter::connect('/search/*', ['controller' => 'nodes', 'action' => 'search']);

	// Blog
	CroogoRouter::connect('/blog', ['controller' => 'nodes', 'action' => 'index', 'type' => 'blog']);
	CroogoRouter::connect('/blog/archives/*', ['controller' => 'nodes', 'action' => 'index', 'type' => 'blog']);
	CroogoRouter::connect('/blog/:slug', ['controller' => 'nodes', 'action' => 'view', 'type' => 'blog']);
	CroogoRouter::connect('/blog/term/:slug/*', ['controller' => 'nodes', 'action' => 'term', 'type' => 'blog']);

	// Node
	CroogoRouter::connect('/node', ['controller' => 'nodes', 'action' => 'index', 'type' => 'node']);
	CroogoRouter::connect('/node/archives/*', ['controller' => 'nodes', 'action' => 'index', 'type' => 'node']);
	CroogoRouter::connect('/node/:slug', ['controller' => 'nodes', 'action' => 'view', 'type' => 'node']);
	CroogoRouter::connect('/node/term/:slug/*', ['controller' => 'nodes', 'action' => 'term', 'type' => 'node']);

	// Page
	CroogoRouter::connect('/about', ['controller' => 'nodes', 'action' => 'view', 'type' => 'page', 'slug' => 'about']);
	CroogoRouter::connect('/page/:slug', ['controller' => 'nodes', 'action' => 'view', 'type' => 'page']);

	// Users
	CroogoRouter::connect('/register', ['controller' => 'users', 'action' => 'add']);
	CroogoRouter::connect('/user/:username', ['controller' => 'users', 'action' => 'view'], ['pass' => ['username']]);

	// Contact
	CroogoRouter::connect('/contact', ['controller' => 'contacts', 'action' => 'view', 'contact']);

	// Croogo's layout around placeholders, for the pages that moved to Symfony
	CroogoRouter::connect('/legacy-layout', ['controller' => 'legacy_layout', 'action' => 'index']);
