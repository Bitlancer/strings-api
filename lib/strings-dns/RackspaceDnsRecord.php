<?php

namespace StringsDns;

class RackspaceDnsRecord extends DnsRecord {

	public function __construct($id=null,$type,$name,$data,$ttl,$priority=false){

		parent::__construct($id,$type,$name,$data,$ttl,$priority);
	}

	public static function fromProviderObject($object){
		$record = new RackspaceDnsRecord(
			$object->id,
			$object->type,
			$object->name,
			$object->data,
			$object->ttl,
			$object->priority
		);

		return $record;
	}
}
