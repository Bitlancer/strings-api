<?php

namespace StringsInfrastructure;

spl_autoload_register(function($class){

	//Remove namespace
	if(strrpos($class,"\\")){
		$class = substr($class,strrpos($class,"\\")+1);
	}

	$classPath = __DIR__ . DIRECTORY_SEPARATOR . "$class.php";

	if(file_exists($classPath))
		include($classPath);
});
