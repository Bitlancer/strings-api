<?php

namespace StringsLoadBalancer;

class Protocol
{

	public function __construct($name,$port){
		$this->name = $name;
		$this->port = $port;
	}

	public function getName(){
		return $this->name;
	}

	public function getPort(){
		return $this->port;
	}

	public function __toString(){
		return $this->name."(".$this->port.")"; 
	}
}
