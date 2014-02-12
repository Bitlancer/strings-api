<?php

namespace APIFramework;

define('DS',DIRECTORY_SEPARATOR);
define('ROOT_DIR', realpath(__DIR__ . DS . ".."));
define('CORE_DIR', ROOT_DIR . DS . 'core');

require(CORE_DIR . DS . 'Bootstrap.php');

