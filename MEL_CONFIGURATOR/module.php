<?php /** @noinspection DegradedSwitchInspection */
/** @noinspection PhpRedundantClosingTagInspection */
/** @noinspection TypeUnsafeArraySearchInspection */
/** @noinspection SenselessProxyMethodInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection NestedPositiveIfStatementsInspection */
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection CurlSslServerSpoofingInspection */
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection SpellCheckingInspection */

require_once __DIR__ . '/../libs/helper_buffer.php';
require_once __DIR__ . '/../libs/helper_constants.php';
require_once __DIR__ . '/../libs/helper_debug.php';


class MELCloudConfigurator extends IPSModule
{
    use HelperBuffer, HelperDebug;


    /**
     * Create (internal SDK function)
     *
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Connect instance and set receive filter
        $this->ConnectParent('{E65CACF9-2088-48F6-946A-9DF65C1B4527}');
        $this->SetReceiveDataFilter('.*shallreceivenothing.*');

        // Register Properties
        $this->RegisterPropertyInteger('CategoryIDDeviceInstances', 0);
        $this->RegisterPropertyBoolean('debug', false);
    }


    /**
     * Destroy (internal SDK function)
     *
     */
    public function Destroy()
    {
        //Never delete this line!!
        parent::Destroy();

        return true;
    }


    /**
     * ApplyChanges (internal SDK function)
     *
     * @return bool
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Register Kernel Messages
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // IP-Symcon Kernel ready?
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Kernel is not ready! Kernel Runlevel = ') . IPS_GetKernelRunlevel(), 0);
            return false;
        }

        // Connect parent socket and register for status changes of this instance
        $ConnectParent = true;
        $ParentSocketID = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($ParentSocketID > 0) {
            $MELIO = IPS_GetInstanceListByModuleID('{E65CACF9-2088-48F6-946A-9DF65C1B4527}');
            if (@array_key_exists('0', $MELIO) === true) {
                if ($ParentSocketID === $MELIO[0]) {
                    $ConnectParent = false;
                }
            }
        }
        if ($ConnectParent === true) {
            $this->ConnectParent('{E65CACF9-2088-48F6-946A-9DF65C1B4527}');
            $ParentSocketID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        }
        $this->SetReceiveDataFilter('.*shallreceivenothing.*');
        $this->SetBufferX('ParentSocketID', $ParentSocketID);
        $this->RegisterMessage($ParentSocketID, IM_CHANGESTATUS);

        if ($this->ParentIsActive() === true) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);
        }

        return true;
    }



    /********** PUBLIC FUNCTIONS **********/

    /**
     * Devices_GetList (get list with all devices and all device-information)
     *
     * @return bool|array
     */
    public function Devices_GetList()
    {
        $dataAR['action'] = 'call_func';
        $dataAR['func'] = __FUNCTION__;

        $result = $this->SendData($dataAR);

        if ($result === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        return $result;
    }


    /**
     * Devices_GetListRAW (get complete list with all devices and all information)
     *
     * @return bool|array
     */
    public function Devices_GetListRAW()
    {
        $dataAR['action'] = 'call_func';
        $dataAR['func'] = __FUNCTION__;

        $result = $this->SendData($dataAR);

        if ($result === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        return $result;
    }



    /********** INTERNAL FUNCTIONS **********/

    /**
     * Check_DeviceInCloud (check if an existing device is available in MELCloud)
     *
     * @param $BuildingID
     * @param $DeviceID
     * @param $CloudDevicesAR
     * @return false|int
     */
    private function Check_DeviceInCloud($BuildingID, $DeviceID, $CloudDevicesAR)
    {
        if (count($CloudDevicesAR) > 0) {
            foreach ($CloudDevicesAR as $ARindex => $CloudDeviceEntryAR) {
                if ((@array_key_exists('BuildingID', $CloudDeviceEntryAR) === true) && (@array_key_exists('DeviceID', $CloudDeviceEntryAR) === true)) {
                    if (($CloudDeviceEntryAR['BuildingID'] == $BuildingID) && ($CloudDeviceEntryAR['DeviceID'] == $DeviceID)) {
                        return $ARindex;
                    }
                }
            }
        }

        return false;
    }


    /**
     * FormData_Generate (generate the json-string for the instance-form)
     *
     * @return false|string
     */
    private function FormData_Generate()
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $FormValue_CAPT_List = $this->Translate('Devices');
        $FormValue_LBL_Type = $this->Translate('Type');
        $FormValue_LBL_BuildingName = $this->Translate('Building-Name');
        $FormValue_LBL_BuildingID = $this->Translate('Building-ID');
        $FormValue_LBL_InstanceID = $this->Translate('Instance-ID');
        $CategoryIDDeviceInstances = $this->ReadPropertyInteger('CategoryIDDeviceInstances');

        $FormData_ElementsX = '{
	"elements":
	[
	    { "type": "Label", "label": "##### MELCloud Configurator v1.0 by Bayaro - www.bayaro.net #####" },
	    { "type": "Label", "label": "##### 28.12.2018 - 01:25 #####"},
	    { "type": "SelectCategory", "name": "CategoryIDDeviceInstances", "caption": "Category for device instance(s)" }
	],
	"actions":
	[
		{
			"type": "List",
			"name": "FormDeviceList",
			"caption": "' . $FormValue_CAPT_List . '",
			"rowCount": 0,
			"add": false,
			"delete": false,
			"sort": {
				"column": "devicename",
				"direction": "ascending"
			},
			"columns": [
				{
					"label": "Name",
					"name": "devicename",
					"width": "auto"
				}, {
					"label": "ID",
					"name": "deviceid",
					"width": "80px"					
				}, {
					"label": "' . $FormValue_LBL_Type . '",
					"name": "devicetype",
					"width": "110px"
				}, {
					"label": "' . $FormValue_LBL_BuildingName . '",
					"name": "buildingname",
					"width": "150px"
				}, {
					"label": "' . $FormValue_LBL_BuildingID . '",
					"name": "buildingid",
					"width": "80px"
				}, {
					"label": "' . $FormValue_LBL_InstanceID . '",
					"name": "instanceid", 
					"width": "80px"
				}
			],
			"values":
			[
			
			]
		}
	],
	"status":
	[
		{ "code": 101, "icon": "active", "caption": "Creating instance" },
		{ "code": 102, "icon": "active", "caption": "OK" },
		{ "code": 201, "icon": "inactive", "caption": "I/O instance is inactive" }
	]
}';

        $FormData = json_decode($FormData_ElementsX);

        $CloudDevicesAR = $this->Devices_GetList();

        if ($CloudDevicesAR === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Please check the debug messages of the I/O instance'), 0);
        }

        $IPSDeviceInstanceAR = array();
        $DeviceInstanceARx = IPS_GetInstanceListByModuleID('{E69F33DB-96B6-4C88-B446-14BCEDA86511}');
        if (count($DeviceInstanceARx) > 0) {
            if ($DebugActive === true) {
                $this->SendDebug(__FUNCTION__, 'DeviceInstanceARx = ' . json_encode($DeviceInstanceARx), 0);
                IPS_LogMessage('MELCloud-' . __FUNCTION__, 'DeviceInstanceARx = ' . json_encode($DeviceInstanceARx));
            }

            $dic = 0;
            foreach ($DeviceInstanceARx as $DeviceInstanceID) {
                $DeviceTypeNr = IPS_GetProperty($DeviceInstanceID, 'DeviceType');
                if ($DeviceTypeNr === 0) {
                    $DeviceType = $this->Translate('Air conditioner');
                } elseif ($DeviceTypeNr === 1) {
                    $DeviceType = $this->Translate('Heat pump');
                } else {
                    $DeviceType = $this->Translate('UNKNOWN');
                }

                $IPSDeviceInstanceAR[$dic]['DeviceName'] = IPS_GetProperty($DeviceInstanceID, 'DeviceName');
                $IPSDeviceInstanceAR[$dic]['DeviceID'] = IPS_GetProperty($DeviceInstanceID, 'DeviceID');
                $IPSDeviceInstanceAR[$dic]['DeviceType'] = $DeviceType;
                $IPSDeviceInstanceAR[$dic]['BuildingName'] = IPS_GetProperty($DeviceInstanceID, 'BuildingName');
                $IPSDeviceInstanceAR[$dic]['BuildingID'] = IPS_GetProperty($DeviceInstanceID, 'BuildingID');
                $IPSDeviceInstanceAR[$dic]['InstanceID'] = $DeviceInstanceID;
                $dic++;
            }
        }

        $DevicesAvail = false;

        if (count($IPSDeviceInstanceAR) > 0) {
            if ($DebugActive === true) {
                $this->SendDebug(__FUNCTION__, 'IPSDeviceInstanceAR = ' . json_encode($IPSDeviceInstanceAR), 0);
                IPS_LogMessage('MELCloud-' . __FUNCTION__, 'IPSDeviceInstanceAR = ' . json_encode($IPSDeviceInstanceAR));
            }

            foreach ($IPSDeviceInstanceAR as $IPSDeviceInstance) {
                $VALUE_devicename = $IPSDeviceInstance['DeviceName'];
                $VALUE_deviceid = $IPSDeviceInstance['DeviceID'];
                $VALUE_devicetype = $IPSDeviceInstance['DeviceType'];
                $VALUE_buildingname = $IPSDeviceInstance['BuildingName'];
                $VALUE_buildingid = $IPSDeviceInstance['BuildingID'];
                $VALUE_instanceid = $IPSDeviceInstance['InstanceID'];
                $VALUE_rowColor = '#ff0000';

                if ($CloudDevicesAR !== false) {
                    $DeviceInIpsAndCloud_Index = $this->Check_DeviceInCloud($IPSDeviceInstance['BuildingID'], $IPSDeviceInstance['DeviceID'], $CloudDevicesAR);

                    if ($DeviceInIpsAndCloud_Index !== false) {
                        unset($CloudDevicesAR[$DeviceInIpsAndCloud_Index]);
                        $VALUE_rowColor = '#00ff00';
                    }
                }

                $FormData->actions[0]->values[] = Array(
                    'devicename' => $VALUE_devicename,
                    'deviceid' => $VALUE_deviceid,
                    'devicetype' => $VALUE_devicetype,
                    'buildingname' => $VALUE_buildingname,
                    'buildingid' => $VALUE_buildingid,
                    'instanceid' => $VALUE_instanceid,
                    'rowColor' => $VALUE_rowColor
                );
            }
            $DevicesAvail = true;
        }

        if ($CloudDevicesAR !== false) {
            if (@count($CloudDevicesAR) > 0) {
                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, 'CloudDevicesAR = ' . json_encode($CloudDevicesAR), 0);
                    IPS_LogMessage('MELCloud-' . __FUNCTION__, 'CloudDevicesAR = ' . json_encode($CloudDevicesAR));
                }

                foreach ($CloudDevicesAR as $CloudDevice) {
                    if ($CloudDevice['Device']['DeviceType'] === 0) {
                        $DeviceType = $this->Translate('Air conditioner');
                    } elseif ($CloudDevice['Device']['DeviceType'] === 1) {
                        $DeviceType = $this->Translate('Heat pump');
                    } else {
                        $DeviceType = $this->Translate('UNKNOWN');
                    }
                    $VALUE_devicetype_BTN = $CloudDevice['Device']['DeviceType'];

                    $VALUE_devicename = $CloudDevice['DeviceName'];
                    $VALUE_deviceid = $CloudDevice['DeviceID'];
                    $VALUE_devicetype = $DeviceType;
                    $VALUE_buildingname = $CloudDevice['BuildingName'];
                    $VALUE_buildingid = $CloudDevice['BuildingID'];
                    $VALUE_instanceid = 0;

                    $FormData->actions[0]->values[] = array(
                        'devicename' => $VALUE_devicename,
                        'deviceid' => $VALUE_deviceid,
                        'devicetype' => $VALUE_devicetype,
                        'buildingname' => $VALUE_buildingname,
                        'buildingid' => $VALUE_buildingid,
                        'instanceid' => $VALUE_instanceid,
                        'rowColor' => ''
                    );

                    $Echo_Text = $this->Translate('The instance was created.');
                    $BTN_Label = $this->Translate('Create instance') . ': ' . $VALUE_devicename;
                    $FormData->actions[] = array(
                        'type' => 'Button',
                        'label' => $BTN_Label,
                        'onClick' => '$InstanceID = IPS_CreateInstance(\'{E69F33DB-96B6-4C88-B446-14BCEDA86511}\'); IPS_SetParent($InstanceID, ' . $CategoryIDDeviceInstances . '); MEL_DeviceInstance_Configuration($InstanceID, \'' . $VALUE_buildingid . '\', \'' . $VALUE_deviceid . '\', \'' . $VALUE_devicetype_BTN . '\'); echo \'' . $Echo_Text . '\';'
                    );
                }
                $DevicesAvail = true;
            }
        }

        if ($DevicesAvail === true) {
            $FormData->actions[0]->rowCount = count($FormData->actions[0]->values);
        } else {
            $FormData->actions[0]->rowCount = 0;
        }
        $FormDataX = json_encode($FormData);

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, 'FormData = ' . $FormDataX, 0);
            $this->SendDebug(__FUNCTION__, 'FormData (json last error msg) = ' . json_last_error_msg(), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, 'FormData = ' . $FormDataX);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, 'FormData (json last error msg) = ' . json_last_error_msg());
        }

        return $FormDataX;
    }


    /**
     * GetConfigurationForm (dynamic generation of form data for the module instance)
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        return $this->FormData_Generate();
    }


    /**
     * MessageSink (handles messages on kernel level)
     *
     * @param $TimeStamp
     * @param $SenderID
     * @param $Message
     * @param $Data
     * @return bool|void
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, 'SenderID = ' . $SenderID . ' // ' . $this->Translate('Message') . ' = ' . $Message . ' // ' . $this->Translate('Data') . ' = ' . json_encode($Data), 0);
        }

        switch ($Message) {
            case IPS_KERNELMESSAGE:
                switch ($Data[0]) {
                    case KR_READY:
                        $this->ApplyChanges();
                        break;
                }
                break;

            case IM_CHANGESTATUS:
                if ($SenderID === (int)$this->GetBufferX('ParentSocketID')) {
                    if ($Data[0] === IS_ACTIVE) {
                        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);
                        $this->ApplyChanges();
                    } else {
                        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 201);
                    }
                }
                break;
        }
    }


    /**
     * ParentIsActive (determine whether the parent instance is active or not)
     *
     * @return bool
     */
    private function ParentIsActive()
    {
        $InstanceInfoAR = IPS_GetInstance($this->InstanceID);
        if ($InstanceInfoAR['ConnectionID'] > 0) {
            $ParentInstanceInfoAR = IPS_GetInstance($InstanceInfoAR['ConnectionID']);
            if ($ParentInstanceInfoAR['InstanceStatus'] === 102) {
                return true;
            }
        }

        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 201); // Parent is inactive
        $this->SendDebug(__FUNCTION__, $this->Translate('I/O instance is inactive'), 0);

        return false;
    }


    /**
     * SendData (Sending data to the parent module instance)
     *
     * @param $dataAR
     * @return bool|array
     */
    private function SendData($dataAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($this->ParentIsActive() === false) {
            return false;
        }

        $dataAR['DataID'] = '{AF4E8025-2CFF-4B94-9C43-D68B868F281D}';

        $resultJson = @$this->SendDataToParent(json_encode($dataAR));
        if ($resultJson === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data'), 0);
        }

        $result = json_decode($resultJson, true);
        if (@array_key_exists('error', $result) === true) {
            $error = $result['error'];
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data') . ' // ' . $this->Translate('Error') . ' = ' . $error, 0);
            return false;
        }

        unset($result['DataID']);

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Response to the last command sent') . ' = ' . json_encode($result), 0);
        }

        return $result;
    }


    /**
     * SetStatus (override of internal SDK function)
     *
     * @param int $InstanceID
     * @param $Status
     * @return bool
     */
    protected function SetInstanceStatus_IfDifferent($InstanceID, $Status)
    {
        $result = true;
        $ParentInstanceInfoAR = IPS_GetInstance($InstanceID);
        if ($ParentInstanceInfoAR['InstanceStatus'] !== $Status) {
            $result = $this->SetStatus($Status);
        }

        return $result;
    }
}

?>