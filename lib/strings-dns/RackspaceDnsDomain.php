<?php

namespace StringsDns;

class RackspaceDnsDomain extends DnsDomain {

	public function __construct($id=null,$domain,$ttl,$contactInformation){

		if(!isset($contactInformation['email']))
			throw new \InvalidArgumentException('Rackspace requires an email address is defined for each domain.');

		parent::__construct($id,$domain,$ttl,$contactInformation);
	}

	public static function fromProviderObject($object){
		$record = new RackspaceDnsDomain(
			$object->id,
			$object->name,
			$object->ttl,
			array(
				'email' => $object->emailAddress
			)
		);

		return $record;
	}
}
