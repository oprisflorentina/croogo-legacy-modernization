<?php
/**
 * Legacy layout
 *
 * Renders Croogo's page layout (header, main menu, sidebar blocks, footer)
 * around two placeholders, for the parts of the site that moved to Symfony.
 * Symfony fetches this page and puts its own title and content in place of
 * the placeholders (transclusion), so a moved page keeps the same layout
 * without a second copy of it.
 *
 * The menu's current link and the blocks' visibility rules depend on the page
 * being viewed, so the page to render for is passed as ?path=/contact. Only a
 * plain path on this site is accepted; anything else renders for "/". It
 * renders for an anonymous visitor: the request comes from Symfony, not from
 * the visitor's browser.
 *
 * @property AuthComponent $Auth
 * @property CroogoComponent $Croogo
 */
class LegacyLayoutController extends AppController {
/**
 * Controller name
 *
 * @var string
 * @access public
 */
	public $name = 'LegacyLayout';

/**
 * No model: the layout's data comes from the Croogo component.
 *
 * @var array
 * @access public
 */
	public $uses = [];

	const TITLE = '%%LEGACY_LAYOUT_TITLE%%';
	const CONTENT = '<!--%%LEGACY_LAYOUT_CONTENT%%-->';

	public function beforeFilter() {
		parent::beforeFilter();
		// Infrastructure for Symfony, not a page in Croogo's permissions.
		$this->Auth->allow('index');
	}

	public function index() {
		$path = $this->params['url']['path'] ?? '/';
		if (!is_string($path) || !preg_match('#^/(?!/)[A-Za-z0-9/_.\-]*$#', $path)) {
			$path = '/';
		}

		// Auth and the Security component have checked this request at its own
		// address. From here on, the layout is built for the requested page:
		// CroogoComponent::blocks() and LayoutHelper::menu() read this value.
		$this->params['url']['url'] = ltrim($path, '/');
		$this->Croogo->blocks();

		$this->header('X-Robots-Tag: noindex');
		$this->set('title_for_layout', self::TITLE);
		$this->set('content', self::CONTENT);
	}
}
