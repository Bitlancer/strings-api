<?php

namespace APIFramework;

/**
* Routes
*/

Router::registerRoute('GET','/Test/index','Test','index');

//Instances
Router::registerRoute(array('GET','POST'),'/Instances/create/:deviceId','Instances','create');
Router::registerRoute(array('GET','POST'),'/Instances/delete/:deviceId','Instances','delete');
Router::registerRoute(array('GET','POST'),'/Instances/resize/:deviceId/:flavorId','Instances','resize');
Router::registerRoute(array('GET','POST'),'/Instances/confirmResize/:deviceId/:confirm','Instances','confirmResize');
Router::registerRoute(array('GET','POST'),'/Instances/reboot/:deviceId','Instances','reboot');
Router::registerRoute(array('GET','POST'),'/Instances/rebuild/:deviceId','Instances','rebuild');
Router::registerRoute(array('GET','POST'),'/Instances/getStatus/:deviceId','Instances','getStatus');
Router::registerRoute(array('GET','POST'),'/Instances/updateServerStatuses/:implementationId/:region/','Instances','updateServerStatuses');

//Load balancers
Router::registerRoute(array('GET','POST'),'/LoadBalancers/create/:deviceId','LoadBalancers','create');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/delete/:deviceId','LoadBalancers','delete');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/algorithms/:implementationId/:region','LoadBalancers','getAlgorithms');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/protocols/:implementationId/:region','LoadBalancers','getProtocols');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/updateNodes/:deviceId','LoadBalancers','updateNodes');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/addNode/:deviceId/:nodeIp/:nodePort','LoadBalancers','addNode');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/removeNode/:deviceId/:nodeId','LoadBalancers','removeNode');

//DNS
Router::registerRoute(array('GET','POST'),'/Dns/addDeviceARecord/:deviceId','Dns','addDeviceARecord');
