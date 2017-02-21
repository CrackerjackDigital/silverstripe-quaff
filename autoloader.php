<?php
if (isset($_REQUEST['flush'])) {
	if (!file_exists(__DIR__ . '/_manifest_exclude')) {
// require an autoloader for traits in this module if we're doing a dev/build
		spl_autoload_register(function ($class) {
			if (substr($class, 0, 6) == 'Quaff\\') {
				$class = current(array_reverse(explode('\\', $class)));
				// traits are all lower case
				if (strtolower($class) == $class) {
					$flags = FilesystemIterator::KEY_AS_PATHNAME
						| FilesystemIterator::SKIP_DOTS
						| FilesystemIterator::CURRENT_AS_PATHNAME;
					
					$fileName = "$class.php";
					$fileLength = strlen($fileName);
					
					$itr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, $flags));
					/** @var SplFileInfo $fileInfo */
					foreach ($itr as $path) {
						if (substr($path, -$fileLength) == $fileName) {
							require_once($path);
							break;
						}
					}
				}
			}
		});
	}
}
