<?php

namespace APIFramework;

/**
* Routes
*/

Router::registerRoute('GET','/Test/index','Test','index');

Router::registerRoute(array('GET','POST'),'/Instances/create/:id','Instances','create');
Router::registerRoute(array('GET','POST'),'/Instances/delete/:id','Instances','delete');
Router::registerRoute(array('GET','POST'),'/Instances/resize/:id/:flavorId','Instances','resize');
Router::registerRoute(array('GET','POST'),'/Instances/confirmResize/:id/:confirm','Instances','confirmResize');
Router::registerRoute(array('GET','POST'),'/Instances/reboot/:id','Instances','reboot');
Router::registerRoute(array('GET','POST'),'/Instances/rebuild/:id','Instances','rebuild');
Router::registerRoute(array('GET','POST'),'/Instances/getStatus/:id','Instances','getStatus');

Router::registerRoute(array('GET','POST'),'/Dns/addDeviceARecord/:id','Dns','addDeviceARecord');
