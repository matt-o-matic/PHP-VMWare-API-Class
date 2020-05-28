# VMWareAPI version 0.1
Copyright (c) 2020 P Matthew Bradford

## Description
An API for VMWare designed to gather performance telemetry from a VMWare installation that is easy to use.

## Notable features:
- Flexible return types - Can return raw XML, JSON, or PHP arrays.
- Rate limiting - Ensure you don't overwhelm your server with too many requests
- Performance statistics - Keep track of how well the API is performing for tuning
- Low-level exposure to API - Submit any valid SOAP call directly
- Mid-level abstractions - All inventory and telemetry related functions are wrapped in easy to use functions
- High-level abstractions - Combine several calls to get commonly desired results (ex: show me all the metrics in plain english available for all VMs
  
And much more!
  
## Basic usage
```
include_once "..\class\vmwareapi.class.php";

// Create object
$api = new VMWareAPI(["strictSSL"=>false,"url"=>"https://your.url/sdk/", "username"=>"root", "password"=>"yourpass"]);
$api=>login();

// Get the names of all the host groups in the cluster
$res = $api->getInventoryInfo("ComputeResource", ["name"], false);
print_r($res);

// Get the names of all the host systems in the cluster
$res = $api->getInventoryInfo("HostSystem", ["name"], false);
print_r($res);

// Get the names of all the VMs (Guests) in the cluster
$res = $api->getInventoryInfo("VirtualMachine", ["name"], false);
print_r($res);

// Get the names of all the Virtual Switches in the cluster
$res = $api->getInventoryInfo("DistributedVirtualSwitch", ["name"], false);
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
```

