#!/usr/bin/php
<?php

require 'Autoload.php';

$providerConnection = array(
	'region' => 'DFW',
    'identityApiEndpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
    'credentials' => array(
        'username' => '',
        'secret' => ''
    )
);

$rackspace = new \StringsLoadBalancer\RackspaceLoadBalancerDriver($providerConnection);

