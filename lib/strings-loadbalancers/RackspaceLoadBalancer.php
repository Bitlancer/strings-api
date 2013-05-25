<?php

namespace StringsLoadBalancer;

class RackspaceLoadBalancer extends LoadBalancer
{
	public static function fromProviderObject($object){

        $lb = new RackspaceLoadBalancer(
            $object->name,
            $object->protocol,
            $object->port,
			$object->algorithm,
			$object->virtualIpType
        );

        return $lb;
    }	
}
