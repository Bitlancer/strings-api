<?php

namespace StringsLoadBalancer;

class Algorithm
{
	public function __construct($name){
		$this->name = $name;
	}

	public function getName(){
		return $this->name;
	}

	public function __toString(){
		return $this->name;
	}
}
