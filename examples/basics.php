<pre>
<?php

include_once "..\class\vmwareapi.class.php";

$api = new VMWareAPI(["strictSSL"=>false,"url"=>"https://your.url/sdk/", "username"=>"root", "password"=>"yourpass"]);
$api=>login();

// Get the names of all the HOSTS (host groups) in the cluster
$res = $api->getInventoryInfo("ComputeResource", "hostFolder", ["name"], false);
print_r($res);

// Get the names of all the VMs (Guests) in the cluster
$res = $api->getInventoryInfo("VirtualMachine", "vmFolder", ["name"], false);
print_r($res);

// Get the names of all the Virtual Switches in the cluster
$res = $api->getInventoryInfo("DistributedVirtualSwitch", "networkFolder", ["name"], false);
print_r($res);

// Get the metric IDs for a particular VM
$res = $api->getAvailMetrics("VirtualMachine", "vm-1234");
print_r($res);

// Get metric info for all the metrics available for that VM
$res = $api->getMetricInfo([2,6,12,24,85,86,90,98,102,125,133,143,155,266,267,269]);
print_r($res);

// Choose a metric and get some actual telemetry 
$res = $api->getMetricValues("VirtualMachine", "vm-1234", ["id"=>125, "instance"=>""], "csv", "2020-05-22 6:30pm", "2020-05-22 7:30pm");
print_r($res);


?>
