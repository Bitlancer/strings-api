<?php

if(!class_exists('\OpenCloud\OpenStack')){

    $libraryPath = __DIR__ . DIRECTORY_SEPARATOR . 'lib';
    require_once($libraryPath . DIRECTORY_SEPARATOR . 'php-opencloud.php');
}
