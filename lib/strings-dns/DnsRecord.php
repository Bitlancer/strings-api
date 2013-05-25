<?php

namespace StringsDns;

class DnsRecord {

	public function __construct($id=null,$type,$name,$data,$ttl,$priority=false){

		$this->id = $id;
		$this->type = $type;
		$this->name = $name;
		$this->data = $data;
		$this->ttl = $ttl;
		$this->priority = $priority;
	}

	public function getId(){
		return $this->id;
	}

	public function getType(){
		return $this->type;
	}

	public function getName(){
		return $this->name;
	}

	public function getData(){
		return $this->data;
	}

	public function getTtl(){
		return $this->ttl;
	}

	public function getPriority(){
		return $this->priority;
	}

	public function toArray(){
		return array(
			'id' => $this->getId(),
			'type' => $this->getType(),
			'name' => $this->getname(),
			'data' => $this->getData(),
			'ttl' => $this->getTtl(),
			'priority' => $this->getData()
		);
	}

	public function __toString(){
		return json_encode($this);
	}
}
