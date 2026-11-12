<?php
/**
 * Filemanager Helper
 *
 * PHP version 5
 *
 * @category Helper
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link	 http://www.croogo.org
 *
 * The File Manager itself was removed (security findings 3 and 5). The
 * Attachments views still use the two methods kept here.
 */
class FilemanagerHelper extends AppHelper {
/**
 * Get extension from a file name, or "file" if it has none.
 *
 * @param string $filename file name
 * @return string
 */
	public function filename2ext($filename) {
		$filename_e = explode('.', strtolower($filename));
		if (count($filename_e) == 1) {
			return 'file';
		}
		return end($filename_e);
	}

/**
 * Get icon from mime type
 *
 * @param string $mimeType mine type
 * @return string
 */
	public function mimeTypeToImage($mimeType) {
		$mime = explode('/', $mimeType);
		$mime = $mime['0'];

		$mimeToImages = [];
		$mimeToImages['text'] = 'page_white.png';

		if (isset($mimeToImages[$mime])) {
			$output = $mimeToImages[$mime];
		} else {
			$output = 'page_white.png';
		}

		return $output;
	}
}
