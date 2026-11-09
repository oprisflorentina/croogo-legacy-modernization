<?php
/**
 * Attachments Controller
 *
 * This file will take care of file uploads (with rich text editor integration).
 *
 * PHP version 5
 *
 * @category Controller
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 *
 * @property SessionComponent $Session
 */
class AttachmentsController extends AppController {
/**
 * Controller name
 *
 * @var string
 * @access public
 */
	public $name = 'Attachments';

/**
 * Models used by the Controller
 *
 * @var array
 * @access public
 */
	public $uses = ['Node'];

/**
 * Helpers used by the Controller
 *
 * @var array
 * @access public
 */
	public $helpers = ['Filemanager', 'Text', 'Image'];

/**
 * Node type
 *
 * If the Controller uses Node model,
 * this is, most of the time, the singular of the Controller name in lowercase.
 *
 * @var string
 * @access public
 */
	public $type = 'attachment';

/**
 * Uploads directory
 *
 * relative to the webroot.
 *
 * @var string
 * @access public
 */
	public $uploadsDir = 'uploads';

/**
 * File extensions an attachment may have. Anything a web server could run
 * (php, phtml, phar, cgi...) or a browser could run as part of this site
 * (html, svg, js) is left out.
 *
 * @var array
 * @access public
 */
	public $allowedExtensions = [
		'jpg', 'jpeg', 'png', 'gif', 'webp',
		'pdf', 'txt', 'csv', 'zip',
		'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
		'mp3', 'mp4',
	];

/**
 * Before executing controller actions
 *
 * @return void
 * @access public
 */
	public function beforeFilter() {
		parent::beforeFilter();

		// Comment, Category, Tag not needed
		$this->Node->unbindModel(['hasMany' => ['Comment'], 'hasAndBelongsToMany' => ['Category', 'Tag']]);

		$this->Node->type = $this->type;
		$this->Node->Behaviors->attach('Tree', ['scope' => ['Node.type' => $this->type]]);
		$this->set('type', $this->type);
	}

/**
 * Admin index
 *
 * @return void
 * @access public
 */
	public function admin_index() {
		$this->set('title_for_layout', __('Attachments', true));

		$this->Node->recursive = 0;
		$this->paginate['Node']['order'] = 'Node.created DESC';
		$this->set('attachments', $this->paginate());
	}

/**
 * Admin add
 *
 * @return void
 * @access public
 */
	public function admin_add() {
		$this->set('title_for_layout', __('Add Attachment', true));

		if (isset($this->params['named']['editor'])) {
			$this->layout = 'admin_full';
		}

		if (!empty($this->data)) {
			$file = $this->data['Node']['file'] ?? null;
			unset($this->data['Node']['file']);

			if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
				$this->Session->setFlash(__('The file could not be uploaded. Please, try again.', true), 'default', ['class' => 'error']);
				return;
			}
			$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
			if (!in_array($extension, $this->allowedExtensions, true)) {
				$this->Session->setFlash(__('This file type is not allowed.', true), 'default', ['class' => 'error']);
				return;
			}

			$newFileName = $this->_safeFileName($file['name'], $extension);
			$destination = WWW_ROOT . $this->uploadsDir . DS . $newFileName;

			// The title keeps the name as uploaded, without its extension.
			$this->data['Node']['title'] = pathinfo($file['name'], PATHINFO_FILENAME);
			$this->data['Node']['slug'] = $newFileName;
			// Detected from the content: the type the browser sends is whatever it claims.
			$this->data['Node']['mime_type'] = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
			//$this->data['Node']['guid'] = Router::url('/' . $this->uploadsDir . '/' . $newFileName, true);
			$this->data['Node']['path'] = '/' . $this->uploadsDir . '/' . $newFileName;

			$this->Node->create();
			if ($this->Node->save($this->data)) {
				// move the file
				move_uploaded_file($file['tmp_name'], $destination);

				$this->Session->setFlash(__('The Attachment has been saved', true), 'default', ['class' => 'success']);

				if (isset($this->params['named']['editor'])) {
					$this->redirect(['action' => 'browse']);
				} else {
					$this->redirect(['action'=>'index']);
				}
			} else {
				$this->Session->setFlash(__('The Attachment could not be saved. Please, try again.', true), 'default', ['class' => 'error']);
			}
		}
	}

/**
 * Admin edit
 *
 * @param int $id
 * @return void
 * @access public
 */
	public function admin_edit($id = null) {
		$this->set('title_for_layout', __('Edit Attachment', true));

		if (!$id && empty($this->data)) {
			$this->Session->setFlash(__('Invalid Attachment', true), 'default', ['class' => 'error']);
			$this->redirect(['action'=>'index']);
		}
		if (!empty($this->data)) {
			if ($this->Node->save($this->data)) {
				$this->Session->setFlash(__('The Attachment has been saved', true), 'default', ['class' => 'success']);
				$this->redirect(['action'=>'index']);
			} else {
				$this->Session->setFlash(__('The Attachment could not be saved. Please, try again.', true), 'default', ['class' => 'error']);
			}
		}
		if (empty($this->data)) {
			$this->data = $this->Node->read(null, $id);
		}
	}

/**
 * Admin delete
 *
 * @param int $id
 * @return void
 * @access public
 */
	public function admin_delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(__('Invalid id for Attachment', true), 'default', ['class' => 'error']);
			$this->redirect(['action'=>'index']);
		}
		if (!isset($this->params['named']['token']) || ($this->params['named']['token'] != $this->params['_Token']['key'])) {
			$blackHoleCallback = $this->Security->blackHoleCallback;
			$this->$blackHoleCallback();
		}

		$attachment = $this->Node->find('first', [
			'conditions' => [
				'Node.id' => $id,
				'Node.type' => $this->type,
			],
		]);
		if (isset($attachment['Node'])) {
			if ($this->Node->delete($id)) {
				unlink(WWW_ROOT . $this->uploadsDir . DS . $attachment['Node']['slug']);
				$this->Session->setFlash(__('Attachment deleted', true), 'default', ['class' => 'success']);
				$this->redirect(['action'=>'index']);
			}
		} else {
			$this->Session->setFlash(__('Invalid id for Attachment', true), 'default', ['class' => 'error']);
			$this->redirect(['action'=>'index']);
		}
	}

	public function admin_browse() {
		$this->layout = 'admin_full';
		$this->admin_index();
	}

/**
 * A file name for the uploads folder: lowercase letters, digits and hyphens,
 * then the extension. Every dot in the uploaded name becomes a hyphen, so
 * "x.php.png" is stored as "x-php.png". If the name is taken, a random
 * suffix is added.
 *
 * @param string $name File name as uploaded
 * @param string $extension Allowed extension, lowercase
 * @return string
 */
	protected function _safeFileName($name, $extension) {
		$base = strtolower(Inflector::slug(pathinfo($name, PATHINFO_FILENAME), '-'));
		$base = substr(trim((string)preg_replace('/[^a-z0-9]+/', '-', $base), '-'), 0, 100);
		if ($base === '') {
			$base = 'file';
		}

		$fileName = $base . '.' . $extension;
		while (file_exists(WWW_ROOT . $this->uploadsDir . DS . $fileName)) {
			$fileName = $base . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
		}
		return $fileName;
	}

}
