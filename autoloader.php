<?php
if (isset($_REQUEST['flush'])) {
	if (!file_exists(__DIR__ . '/_manifest_exclude')) {
// require an autoloader for traits in this module if we're doing a dev/build
		spl_autoload_register(function ($class) {
			if (substr($class, 0, 6) == 'Quaff\\') {
				$class = current(array_reverse(explode('\\', $class)));
				// traits are all lower case
				if (strtolower($class) == $class) {
					foreach (glob(__DIR__ . '/code/**/*/') as $path) {
						$file = "$path/$class.php";
						if (file_exists($file)) {
							require_once($file);
							break;
						}
					}
				}
			}
		});
	}
}
