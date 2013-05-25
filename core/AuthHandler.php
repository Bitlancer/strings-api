<?php

namespace APIFramework;

class AuthHandler
{
	public static function authenticate($username,$password){
		
		if(AuthConfig::username == $username && AuthConfig::password == $password)
			return true;
		else
			return false;
	}
}
