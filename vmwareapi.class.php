<?php

// VMWareAPI version 0.1
// Copyright 2020 P Matthew Bradford
// Free for anyone to use or modify.

// Dependencies:
// 		DOMDocumentExtended.class.php - By Mathieu Bouchard

include_once "DOMDocumentExtended.class.php";

class VMWareAPI {

	/**************************************************************************
		These public properties are to configure the behavior of the VMWareAPI 
		object.
	**************************************************************************/
	
	// The URL to the VMWare api.  ex: "https://192.168.0.5/sdk"
	public $url = '';
	
	// The username to use to log in to the API
	public $username = '';
	
	// The password for the user account used to log in to the API
	public $password = '';
	
	// Set this to false to disable SSL checking, true to enforce 
	// strict SSL compliance
	public $strictSSL = false;

	// Minimum time (in microseconds) to wait between calls to the 
	// API.  Use this to do some rate limiting on the API server
	// if you expect to make a bunch of calls at once.
	public $apiWaitTm = 0; // mircoseconds
	
	// For any function, send back the raw results (if applicable)
	// otherwise will send a blank string.
	// Note: for direct API calls, this will return the raw XML from
	//       the server.
	public $sendRaw = true;
	
	// Send back the HTTP headers from the API server, if applicable,
	// otherwise will send back a blank string.
	public $sendHeaders = true;
	
	// Send back the resulting object in JSON form
	public $sendJson = true;
	
	// Send back the resulting object in a PHP array
	public $sendArray = true;


	/**************************************************************************
		These properties can be used to debug function calls or get at data
		normally not returned.
	**************************************************************************/
	
	// The full headers of the last request made to the API, including
	// the XML payload
	public $lastRequest = [];
	
	// The raw results of the last API call
	public $lastRaw = '';
	
	// The HTTP headers of the last API call
	public $lastHeaders = '';
	
	// The JSON encoded object of the last result set fetched
	public $lastJson = '';
	
	// The PHP array of the last result set fetched
	public $lastArray = [];

	public function __construct($opts = []) {
		/*	
			To configure the object upon creation, pass a variable of 
			options.  Valid entries are: 
				url
				username
				password
				strictSSL
				sendRaw
				sendHeaders
				sendJson
				sendArray
				apiWaitTm
			For descriptions of each property, see property descriptions
			above.
		*/
		if (isset($opts['url'])) $this->url = $opts['url'];
		if (isset($opts['username'])) $this->username = $opts['username'];
		if (isset($opts['password'])) $this->password = $opts['password'];
		if (isset($opts['strictSSL'])) $this->strictSSL = $opts['strictSSL'];
		if (isset($opts['sendRaw'])) $this->sendRaw = $opts['sendRaw'];
		if (isset($opts['sendHeaders'])) $this->sendHeaders = $opts['sendHeaders'];
		if (isset($opts['sendJson'])) $this->sendJson = $opts['sendJson'];
		if (isset($opts['sendArray'])) $this->sendArray = $opts['sendArray'];
		if (isset($opts['apiWaitTm'])) $this->apiWaitTm = $opts['apiWaitTm'];
		$this->saveResultConfig();
	}

	public function _callApi($xml) {
		/*
			Use this function to pass any XML to the VMWare API
			INPUTS:
				xml: Any properly formatted XML
				
			RETURNS:
				Raw output from the server, stores HTTP headers in $this->lastHeaders
		*/
		
		if ($this->apiWaitTm > 0) { // This means we might need to wait
			// Figure out the next timestamp a call is allowed
			$nextAllowedCall = $this->_lastApiCall + ($this->apiWaitTm/1000000);
			
			// Save "now" just in case we're on the bubble
			$now = microtime(true);
			
			if ($now < $nextAllowedCall) {
				// Can't fire it off just yet, figure out how much time we need to wait
				$timeToSleep = ($nextAllowedCall - $now) * 1000000;
				
				// Wait for the balance of the time
				usleep($timeToSleep);
			}
		}
		
		// Set HTTP options
		$opts = [
					"http" => [
						"ignore_errors" => true,
						"method" => "POST",
						"header" => $this->compileHeaders(),
						"content" => $xml
					]
				];
		
		if ($this->strictSSL == false) $opts["ssl"] = array('verify_peer' => false,'verify_peer_name' => false);
		
		$timer = microtime(true); // Begin timer for API call
		$ret = file_get_contents($this->url, false, stream_context_create($opts)); // Make the API call
		$this->lastHeaders = $this->parseHeaders($http_response_header); // Put the response headers into $this->lastHeaders
		$timer = microtime(true) - $timer; // Stop the timer and record the result
		$this->_lastApiCall = microtime(true); // Make sure we know the last time we ran something

		$this->lastRequest = $opts;
		
		// Do some statistical upkeep
		$this->_totalApiCalls ++;
		$this->_lastApiCallTm = $timer;
		$this->_avgApiCallTm = (($this->_avgApiCallTm *($this->_totalApiCalls-1)) + $timer) / $this->_totalApiCalls;
		$this->_totApiCallTm+=$timer;

		return $ret; // Return the raw server output
	}
	
	public function getApiStats() {
		/*
			This function will return an array with a few performance 
			statistics for the VMWare API calls made in this session.
			
			RETURNS:
				Array holding the following elements:
					- Total_API_Calls: The total number of times the API has been called during this session
					- Total_API_Time: The total amount of time in seconds spent calling the API
					- Avg_API_Resp: The average amount of time in seconds it takes the API to respond
					- Last_API_Resp: The response time in seconds of the last API call made
		*/
		
		return ["Total_API_Calls"=>$this->_totalApiCalls, "Total_API_Time"=>$this->_totApiCallTm, "Avg_API_Resp"=>$this->_avgApiCallTm, "Last_API_Resp"=>$this->_lastApiCallTm];
	}
	
	public function getServiceHeader() {
		/*
			This function gets the basic layout of the API implementation being used.
			Note: Typically this function does not need to be called manually.
			
			RETURNS: 
				Array holding the response from the server depending on the
				configuration of this object.
		*/
		$result = $this->prepResult();
		
		$api_function = "RetrieveServiceContent";

		if ($this->url != "") {
			$xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:vim25\">
					   <soapenv:Header/>
					   <soapenv:Body>
						  <urn:" . $api_function . ">
							 <urn:_this type=\"ServiceInstance\">ServiceInstance</urn:_this>
						  </urn:" . $api_function . ">
					   </soapenv:Body>
					</soapenv:Envelope>";
			
			$this->lastRaw = $this->_callApi($xml);
			$this->lastJson = $this->xml2json($this->lastRaw, ["returnval"]);
			$this->lastArray = json_decode($this->lastJson, true);
			if ($this->lastArray == false) $result['error'] = $this->lastRaw;
			elseif (isset($this->lastArray['Envelope']['Body'][$api_function . 'Response'])) $this->lastArray = $this->lastArray['Envelope']['Body'][$api_function . 'Response'];
			elseif (isset($this->lastArray['Envelope']['Body'])) $this->lastArray = $this->lastArray['Envelope']['Body'];

			if ($this->sendRaw) $result['raw'] = $this->lastRaw;
			if ($this->sendHeaders) $result['headers'] = $this->lastHeaders;
			if ($this->sendJson) $result['json'] = $this->lastJson;
			if ($this->sendArray) $result['array'] = $this->lastArray;
			
			$tmp = json_decode($result['json'], true);
			$this->api_info = $tmp['Envelope']['Body']['RetrieveServiceContentResponse']['returnval'][0];
			//print_r($this->api_info);
		} else {
			$result['error'] = "URL is not set.";
		}
		
		return $result;
		
	}
	
	public function login() {
		/*
			Sends user and password credentials to the API defined.  Upon 
			successful login, grabs the sessionID for later use.
			
			Note: MUST CALL THIS FUNCTION BEFORE ATTEMPTING ANY OTHER API
				  FUNCTION CALL.
		
			RETURNS: 
				Array holding the response from the server depending on the
				configuration of this object.
		*/
		$result = $this->prepResult();
		$api_function = "Login";
		
		if ($this->url != "" && $this->username != "" && $this->password != "") {
			
			if (count($this->api_info) == 0) {
				$res = $this->getServiceHeader();
				if ($res['error'] != "") {
					$result = $res;
					$result['error'] = "From getServiceHeader(): " . $res['error'];
					return $result;
				}
			}
			
			$xml = "<SOAP-ENV:Envelope SOAP-ENV:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:SOAP-ENC=\"http://schemas.xmlsoap.org/soap/encoding/\">
					<SOAP-ENV:Body>
						<" . $api_function . ">
							<_this>SessionManager</_this>
							<userName>" . htmlspecialchars($this->username, ENT_XML1, 'UTF-8') . "</userName>
							<password>" . htmlspecialchars($this->password, ENT_XML1, 'UTF-8') . "</password>
						</" . $api_function . ">
					</SOAP-ENV:Body>
				</SOAP-ENV:Envelope>";
			
			$this->lastRaw = $this->_callApi($xml);
			$this->lastJson = $this->xml2json($this->lastRaw, ["returnval"]);
			$this->lastArray = json_decode($this->lastJson, true);
			if ($this->lastArray == false) $result['error'] = $this->lastRaw;
			elseif (isset($this->lastArray['Envelope']['Body'][$api_function . 'Response'])) $this->lastArray = $this->lastArray['Envelope']['Body'][$api_function . 'Response'];
			elseif (isset($this->lastArray['Envelope']['Body'])) $this->lastArray = $this->lastArray['Envelope']['Body'];
			
			if ($this->sendRaw) $result['raw'] = $this->lastRaw;
			if ($this->sendHeaders) $result['headers'] = $this->lastHeaders;
			if ($this->sendJson) $result['json'] = $this->lastJson;
			if ($this->sendArray) $result['array'] = $this->lastArray;

			if (isset($result['headers']['Set-Cookie'])) {
				$this->headers[] = "Cookie: " . $result['headers']['Set-Cookie'];
				$this->validSession = true;
			} else {
				$result['error'] = "Session cookie not found during login.";
			}
		} else {
			$result['error'] = "Must set URL, Username, and Password";
		}
		
		return $result;
	}

	public function getInventoryInfo($itemType = "", $folder = "", $filters = ["guest","summary"], $getAll = false) {
		/*
			This routine gets the ID and instances of the available metrics for a given item/time/interval.
			Required:
				$itemType: Should be one of: VirtualMachine, ComputeResource, HostSystem, VirtualSwitch, VirtualPortGroup, DistributedVirtualSwitch, DistributedVirtualPortgroup, etc
				$folder: The name of the root folder to query.  Valid entries include: "vmFolder", "hostFolder", "networkFolder"
			Optional:
				$filters: The specific pieces of the tree to grab for each resource
				$getAll: Boolean value... get all available data

			RETURNS: 
				Array holding the response from the server depending on the
				configuration of this object.
		*/
		$result = $this->prepResult();
		$api_function = "RetrieveProperties";
		
		if ($itemType == "" || $folder == "") {
			$result['error'] = "Invalid function call, must supply itemType and itemID";
			return $result;
		}

		if (!$this->validSession) {
			$result['error'] = "Must log in before issuing commands";
			return $result;
		}
		
		if (!is_array($filters)) {
			$result['error'] = "Must supply array of filters. (Hint: Empty array is ok)";
			return $result;
		}

		$filterXML = "";
		foreach ($filters as $filter) $filterXML .= "<ns1:pathSet>" . htmlspecialchars($filter, ENT_XML1, 'UTF-8') . "</ns1:pathSet>\n";

		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
				<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"urn:vim25\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
				  <SOAP-ENV:Body>
					  <ns1:" . $api_function . ">
					  <ns1:_this type=\"PropertyCollector\">" . $this->api_info['propertyCollector'] . "</ns1:_this>
					  <ns1:specSet>
						<ns1:propSet>
						  <ns1:type>" . htmlspecialchars($itemType, ENT_XML1, 'UTF-8') . "</ns1:type>
						  <ns1:all>" . ($getAll?"true":"false") . "</ns1:all>
						  " . $filterXML . "
						</ns1:propSet>
						<ns1:objectSet>
						  <ns1:obj type=\"Folder\">" . $this->api_info['rootFolder'] . "</ns1:obj>
						  <ns1:skip>false</ns1:skip>
						  <ns1:selectSet xsi:type=\"TraversalSpec\">
							<name>FolderTraversalSpec</name>
							<type>Folder</type>
							<path>childEntity</path>
							<skip>false</skip>
							<selectSet>
							  <name>FolderTraversalSpec</name>
							</selectSet>
							<selectSet>
							  <name>DataCenterVMTraversalSpec</name>
							</selectSet>
						  </ns1:selectSet>
						  <ns1:selectSet xsi:type=\"TraversalSpec\">
							<name>DataCenterVMTraversalSpec</name>
							<type>Datacenter</type>
							<path>" . htmlspecialchars($folder, ENT_XML1, 'UTF-8') . "</path>
							<skip>false</skip>
							<selectSet>
							  <name>FolderTraversalSpec</name>
							</selectSet>
						  </ns1:selectSet>
						</ns1:objectSet>
					  </ns1:specSet>
					  </ns1:" . $api_function . ">
				  </SOAP-ENV:Body>
				</SOAP-ENV:Envelope>";
		
		$this->lastRaw = $this->_callApi($xml);
		$this->lastJson = $this->xml2json($this->lastRaw, ["returnval"]);
		$this->lastArray = json_decode($this->lastJson, true);
		if ($this->lastArray == false) $result['error'] = $this->lastRaw;
		elseif (isset($this->lastArray['Envelope']['Body'][$api_function . 'Response'])) $this->lastArray = $this->lastArray['Envelope']['Body'][$api_function . 'Response'];
		elseif (isset($this->lastArray['Envelope']['Body'])) $this->lastArray = $this->lastArray['Envelope']['Body'];
		
		if ($this->sendRaw) $result['raw'] = $this->lastRaw;
		if ($this->sendHeaders) $result['headers'] = $this->lastHeaders;
		if ($this->sendJson) $result['json'] = $this->lastJson;
		if ($this->sendArray) $result['array'] = $this->lastArray;
		
		return $result;
	}
	
	public function getAvailMetrics($itemType = "", $itemID = "", $beginTime = "", $endTime = "", $intervalId = -1) {
		/*
			This routine gets the ID and instances of the available metrics for a given item/time/interval.
			Required:
				$itemType: Should be one of: VirtualMachine, ComputeResource, VirtualSwitch, VirtualPortGroup, etc
				$itemID: The name of the item to be queried.  Must be of the type specified in "itemType"
			Optional:
				$beginTime: Any date/time string
				$endTime: Any date/time string
				$intervalId: An interval ID as defined in the VMware API

			RETURNS: 
				Array holding the response from the server depending on the
				configuration of this object.
		*/
		
		$api_function = "QueryAvailablePerfMetric";
		
		$result = $this->prepResult();
		
		if ($itemType == "" || $itemID == "") {
			$result['error'] = "Invalid function call, must supply itemType and itemID";
			return $result;
		}
		
		if (!$this->validSession) {
			$result['error'] = "Must log in before issuing commands";
			return $result;
		}
		
		$beginTimeXML = "";
		$endTimeXML = "";
		$intervalIdXML = "";
		
		if ($beginTime != "") $beginTimeXML = "<urn:beginTime>" . gmdate("Y-m-d\\TH:i:s\\Z", strtotime($beginTime)) . "</urn:beginTime>";
		if ($endTime != "") $endTimeXML = "<urn:endTime>" . gmdate("Y-m-d\\TH:i:s\\Z", strtotime($endTime)) . "</urn:endTime>";
		if ($intervalId != -1) $intervalIdXML = "<urn:intervalId>" . htmlspecialchars($intervalId, ENT_XML1, 'UTF-8') . "</urn:intervalId>";
		
		$xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:vim25\">
					<soapenv:Header/>
					<soapenv:Body>
						<urn:" . $api_function . ">
							<urn:_this>" . $this->api_info['perfManager'] . "</urn:_this>
							<urn:entity type=\"" . htmlspecialchars($itemType, ENT_XML1, 'UTF-8') . "\">" . htmlspecialchars($itemID, ENT_XML1, 'UTF-8') . "</urn:entity>
							" . $beginTimeXML . "
							" . $endTimeXML . "
							" . $intervalIdXML . "
						</urn:" . $api_function . ">
					</soapenv:Body>
					</soapenv:Envelope>";

		$this->lastRaw = $this->_callApi($xml);
		$this->lastJson = $this->xml2json($this->lastRaw, ["returnval"]);
		$this->lastArray = json_decode($this->lastJson, true);
		if ($this->lastArray == false) $result['error'] = $this->lastRaw;
		elseif (isset($this->lastArray['Envelope']['Body'][$api_function . 'Response'])) $this->lastArray = $this->lastArray['Envelope']['Body'][$api_function . 'Response'];
		elseif (isset($this->lastArray['Envelope']['Body'])) $this->lastArray = $this->lastArray['Envelope']['Body'];
		
		if ($this->sendRaw) $result['raw'] = $this->lastRaw;
		if ($this->sendHeaders) $result['headers'] = $this->lastHeaders;
		if ($this->sendJson) $result['json'] = $this->lastJson;
		if ($this->sendArray) $result['array'] = $this->lastArray;
		
		return $result;
	}

	public function getMetricInfo($counterIds = []) {
		/*
			This routine gets the description of one or more Performance Counter IDs.
			Required:
				$counterIds: an array of IDs to look up.

			RETURNS: 
				Array holding the response from the server depending on the
				configuration of this object.
		*/
		$result = $this->prepResult();
		
		$api_function = "QueryPerfCounter";
		
		if (is_array($counterIds) == false || count($counterIds) == 0) {
			$result['error'] = "Invalid function call, must supply array of counterIds";
			return $result;
		}
		
		if (!$this->validSession) {
			$result['error'] = "Must log in before issuing commands";
			return $result;
		}
		
		$counter_xml = "";
		foreach ($counterIds as $cid) $counter_xml .= "<urn:counterId>" . ($cid+0) . "</urn:counterId>\n";
				
		$xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:vim25\">
					<soapenv:Header/>
					<soapenv:Body>
						<urn:" . $api_function . ">
							<urn:_this>" . $this->api_info['perfManager'] . "</urn:_this>
							" . $counter_xml . "
						</urn:" . $api_function . ">
					</soapenv:Body>
				</soapenv:Envelope>";

		$this->lastRaw = $this->_callApi($xml);
		$this->lastJson = $this->xml2json($this->lastRaw, ["returnval","value"]);
		$this->lastArray = json_decode($this->lastJson, true);
		if ($this->lastArray == false) $result['error'] = $this->lastRaw;
		elseif (isset($this->lastArray['Envelope']['Body'][$api_function . 'Response'])) $this->lastArray = $this->lastArray['Envelope']['Body'][$api_function . 'Response'];
		elseif (isset($this->lastArray['Envelope']['Body'])) $this->lastArray = $this->lastArray['Envelope']['Body'];
		
		if ($this->sendRaw) $result['raw'] = $this->lastRaw;
		if ($this->sendHeaders) $result['headers'] = $this->lastHeaders;
		if ($this->sendJson) $result['json'] = $this->lastJson;
		if ($this->sendArray) $result['array'] = $this->lastArray;
		
		return $result;
	}
	
	public function getMetricValues($itemType = "", $itemId = "", $metricArray = [], $format = "csv", $beginTime = "", $endTime = "", $maxSample = -1, $intervalId = -1) {
		/*
			This routine retrieves performance metrics from the VMWare API
			Required:
				$itemType: Should be one of: VirtualMachine, ComputeResource, VirtualSwitch, VirtualPortGroup, etc
				$itemId: The name of the item to be queried.  Must be of the type specified in "itemType"
				$metricArray: An array object containing only the "id" and "instance" elements.  
						Ex. ["id"=>2, "instance"=>""]
			Optional:
				$format: "xml" or "csv" -- default: csv
				$beginTime: Any date/time string
				$endTime: Any date/time string
				$maxSample: The maximum number of samples to return (-1 for no limit)
				$intervalId: An interval ID as defined in the VMware API (-1 for auto select)

			RETURNS: 
				Array holding the response from the server depending on the
				configuration of this object.
		*/
		$result = $this->prepResult();
		$api_function = "QueryPerf";
		
		if ($itemType == "" || $itemId == "") {
			$result['error'] = "Invalid function call, must supply itemType and itemID";
			return $result;
		}

		if (is_array($metricArray) == false || count($metricArray) == 0) {
			$result['error'] = "Invalid function call, must supply array of metric definitions [\"id\"=>2, \"instance\"=>\"\"]";
			return $result;
		}
		
		if (!$this->validSession) {
			$result['error'] = "Must log in before issuing commands";
			return $result;
		}
		
		$beginTimeXML = "";
		$endTimeXML = "";
		$maxSampleXML = "";
		$intervalIdXML = "";
		$metricArrayXML = "";
		
		if ($beginTime != "") $beginTimeXML = "<urn:startTime>" . gmdate("Y-m-d\\TH:i:s\\Z", strtotime($beginTime)) . "</urn:startTime>";
		if ($endTime != "") $endTimeXML = "<urn:endTime>" . gmdate("Y-m-d\\TH:i:s\\Z", strtotime($endTime)) . "</urn:endTime>";
		if ($maxSample != -1) $maxSampleXML = "<urn:maxSample>" . htmlspecialchars($maxSample, ENT_XML1, 'UTF-8') . "</urn:maxSample>";
		if ($intervalId != -1) $intervalIdXML = "<urn:intervalId>" . htmlspecialchars($intervalId, ENT_XML1, 'UTF-8') . "</urn:intervalId>";

		if (!isset($metricArray['id']) || !isset($metricArray['instance'])) {
			$result['error'] = "Malformed array of metric definitions.  Each entry must have id and instance.";
			return $result;
		}
		$metricArrayXML .= "<urn:metricId>\n";
		$metricArrayXML .= "	<urn:counterId>" . ($metricArray['id']+0) . "</urn:counterId>\n";
		$metricArrayXML .= "	<urn:instance>" . htmlspecialchars($metricArray['instance'], ENT_XML1, 'UTF-8') . "</urn:instance>\n";
		$metricArrayXML .= "</urn:metricId>\n";

		$xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:vim25\">
					<soapenv:Header/>
					<soapenv:Body>
						<urn:" . $api_function . ">
							<urn:_this>" . $this->api_info['perfManager'] . "</urn:_this>
							<urn:querySpec>
								<urn:entity type=\"" . htmlspecialchars($itemType, ENT_XML1, 'UTF-8') . "\">" . htmlspecialchars($itemId, ENT_XML1, 'UTF-8') . "</urn:entity>
								" . $beginTimeXML . "
								" . $endTimeXML . "
								" . $maxSampleXML . "
								" . $metricArrayXML . "
								" . $intervalIdXML . "
								<urn:format>" . htmlspecialchars($format, ENT_XML1, 'UTF-8') . "</urn:format>
							</urn:querySpec>
						</urn:" . $api_function . ">
					</soapenv:Body>
				</soapenv:Envelope>";

		$this->lastRaw = $this->_callApi($xml);
		$this->lastJson = $this->xml2json($this->lastRaw, ["returnval"]);
		$this->lastArray = json_decode($this->lastJson, true);
		if ($this->lastArray == false) $result['error'] = $this->lastRaw;
		elseif (isset($this->lastArray['Envelope']['Body'][$api_function . 'Response'])) $this->lastArray = $this->lastArray['Envelope']['Body'][$api_function . 'Response'];
		elseif (isset($this->lastArray['Envelope']['Body'])) $this->lastArray = $this->lastArray['Envelope']['Body'];
		
		if ($this->sendRaw) $result['raw'] = $this->lastRaw;
		if ($this->sendHeaders) $result['headers'] = $this->lastHeaders;
		if ($this->sendJson) $result['json'] = $this->lastJson;
		if ($this->sendArray) $result['array'] = $this->lastArray;
		
		return $result;
	}
	
	public function getVirtualMachines() {
		/*
			This routine retrieves all VM instances and formats them in a nicely readable array/json
			
			RETURNS:
			Object containing the ID and Name of each VM instance
		*/
		$result = $this->prepResult();
		
		if (!$this->validSession) {
			$result['error'] = "Must log in before issuing commands";
			return $result;
		}
		
		$this->restoreResultConfig();
		$this->saveResultConfig();
		$this->quickSetResultConfig(false, false, false, true);
		$res = $this->getInventoryInfo("VirtualMachine", "vmFolder", ["name"], false);
		$this->restoreResultConfig();

		$vmList = [];
		
		foreach ($res["array"]["returnval"] as $vm) {
			$vmItem = ["obj_id"=>$vm["obj"], "name"=>$vm["propSet"]["val"]];
			$vmList[$vm["obj"]] = $vmItem;
		}
		
		if ($this->sendRaw) $result['raw'] = "";
		if ($this->sendHeaders) $result['headers'] = "";
		if ($this->sendJson) $result['json'] = json_encode($vmList);
		if ($this->sendArray) $result['array'] = $vmList;
		
		return $result;
	}
	
	public function getVirtualMachineMetricDefs() {
		/*
			This routine retrieves all VM instances along with the available metrics for 
			each of them and formats them in a nicely readable array/json
			
			RETURNS:
				- Object of metric definitions associated to virtual machines
				- Object of all metric definitions
		*/
		$result = $this->prepResult();
		
		if (!$this->validSession) {
			$result['error'] = "Must log in before issuing commands";
			return $result;
		}
		
		$this->restoreResultConfig();
		$vmList = $this->getVirtualMachines();
		$this->saveResultConfig();
		$this->quickSetResultConfig(false, false, false, true);
		//$res = $api->getAvailMetrics("VirtualMachine", "vm-1844");
		$vmList = $vmList['array'];
		$metricIds = [];
		$vmKeys = [];
		foreach ($vmList as $key=>$vm) $vmKeys[] = $key;
		
		foreach ($vmKeys as $key) {
			$vmList[$key]['metric_defs'] = [];
			$res = $this->getAvailMetrics("VirtualMachine", $vm['obj_id']);
			foreach ($res['array']['returnval'] as $metricDef) {
				$vmList[$key]['metric_defs'][] = $metricDef;
				$metricIds[] = $metricDef['counterId'];
			}
		}
		
		$metricIds = array_unique($metricIds);
		$res = $this->getMetricInfo($metricIds);
		$this->restoreResultConfig();
		$metricDetails = [];

		foreach ($res['array']['returnval'] as $raw_metric) {
			$metricDetails[$raw_metric['key']] = [
				"counterId"=>$raw_metric['key'],
				"name"=>$raw_metric['groupInfo']['label'] . " - " . $raw_metric['nameInfo']['label'],
				"desc"=>$raw_metric['nameInfo']['summary'],
				"unit"=>$raw_metric['unitInfo']['label'],
				"rollupType"=>$raw_metric['rollupType'],
				"statsType"=>$raw_metric['statsType'],
				"level"=>$raw_metric['level']
			];
		}
		
		foreach ($vmKeys as $key) {
			foreach ($vmList[$key]['metric_defs'] as $idx=>$md) {
				$instance = $md['instance'];
				$counterId = $md['counterId'];
				$vmList[$key]['metric_defs'][$idx] = $metricDetails[$counterId];
				$vmList[$key]['metric_defs'][$idx]['instance'] = $instance;
			}
		}
		
		if ($this->sendRaw) $result['raw'] = "";
		if ($this->sendHeaders) $result['headers'] = "";
		if ($this->sendJson) {
			$result['json'] = json_encode($vmList);
			$result['metricJson'] = json_encode($metricDetails);
		}
		if ($this->sendArray) {
			$result['array'] = $vmList;
			$result['metricArray'] = $metricDetails;
		}

		return $result;
	}

	/* Private variables and functions */
	private $validSession = false;
	private $savedResultConfig = [];
	private $_totalApiCalls = 0;
	private $_lastApiCallTm = 0;
	private $_avgApiCallTm = 0;
	private $_totApiCallTm = 0;
	private $_lastApiCall = 0;
	private $headers = ["SOAPAction: \"urn:vim25/4.0\"", "User-Agent: InsightETE VMWare SDK 1.1", "Content-Type: text/xml; charset=UTF-8"];
	private $api_info = [];
	
	private function saveResultConfig() {
		$this->savedResultConfig['sendRaw'] = $this->sendRaw;
		$this->savedResultConfig['sendHeaders'] = $this->sendHeaders;
		$this->savedResultConfig['sendJson'] = $this->sendJson;
		$this->savedResultConfig['sendArray'] = $this->sendArray;
	}
	
	private function restoreResultConfig() {
		$this->sendRaw = $this->savedResultConfig['sendRaw'];
		$this->sendHeaders = $this->savedResultConfig['sendHeaders'];
		$this->sendJson = $this->savedResultConfig['sendJson'];
		$this->sendArray = $this->savedResultConfig['sendArray'];
	}
	
	private function quickSetResultConfig($r, $h, $j, $a) {
		$this->sendRaw = $r;
		$this->sendHeaders = $h;
		$this->sendJson = $j;
		$this->sendArray = $a;
	}
	
	private function compileHeaders() {
		$result = "";
		foreach ($this->headers as $header) {
			$result .= $header . "\r\n";
		}
		return $result;
	}
	
	private function parseHeaders( $headers ) {
		$head = array();
		foreach( $headers as $k=>$v )
		{
			$t = explode( ':', $v, 2 );
			if( isset( $t[1] ) )
				$head[ trim($t[0]) ] = trim( $t[1] );
			else
			{
				$head[] = $v;
				if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
					$head['reponse_code'] = intval($out[1]);
			}
		}
		return $head;
	}
	
	private function xml2json($xml_string, $force_array = []) {
		$document = new DOMDocumentExtended();
		$document->loadXML($xml_string);
		return $document->saveJSON($force_array);
	}
	
	private function prepResult() {
		$result = [];
		if ($this->sendRaw) $result['raw'] = '';
		if ($this->sendHeaders) $result['headers'] = '';
		if ($this->sendJson) $result['json'] = '';
		if ($this->sendArray) $result['array'] = '';
		$result['error'] = '';
		return $result;
	}
}
?>