<?php /** @noinspection NestedPositiveIfStatementsInspection */
/** @noinspection IfReturnReturnSimplificationInspection */
/** @noinspection IfReturnReturnSimplificationInspection */
/** @noinspection DegradedSwitchInspection */
/** @noinspection OffsetOperationsInspection */
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection TypeUnsafeComparisonInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpRedundantClosingTagInspection */

require_once __DIR__ . '/../libs/helper_buffer.php';
require_once __DIR__ . '/../libs/helper_constants.php';
require_once __DIR__ . '/../libs/helper_debug.php';
require_once __DIR__ . '/../libs/helper_variables.php';


class MELCloudDevice extends IPSModule
{
    use HelperBuffer, HelperDebug, HelperVariables;


    /**
     * Create (internal SDK function)
     *
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Connect instance
        $this->ConnectParent('{E65CACF9-2088-48F6-946A-9DF65C1B4527}');

        // Register properties
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('DeviceName', '');
        $this->RegisterPropertyInteger('DeviceType', 999);
        $this->RegisterPropertyString('BuildingID', '');
        $this->RegisterPropertyString('BuildingName', '');
        $this->RegisterPropertyString('FloorID', '');
        $this->RegisterPropertyString('FloorName', '');
        $this->RegisterPropertyString('AreaID', '');
        $this->RegisterPropertyString('AreaName', '');
        $this->RegisterPropertyBoolean('VariablesExtra_System', false);
        $this->RegisterPropertyBoolean('ShowDeviceImage', false);
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('debug', false);
    }


    /**
     * Destroy (internal SDK function)
     *
     */
    public function Destroy()
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return parent::Destroy();
        }

        // remove variable profiles that are only used by this instance
        $VarProfileAR = array('MEL.RoomTemperatureSET.' . $this->InstanceID, 'MEL.OperationMode.' . $this->InstanceID, 'MEL.Presets.' . $this->InstanceID, 'MEL.FanSpeed.' . $this->InstanceID, 'MEL.VaneVertical.' . $this->InstanceID, 'MEL.VaneHorizontal.' . $this->InstanceID);
        foreach ($VarProfileAR as $VarProfileName) {
            @IPS_DeleteVariableProfile($VarProfileName);
        }

        // Never delete this line!!
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

        // Set buffer for internal use
        $this->SetBufferX('Run_LastApplyChanges', time());

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
        $this->SetReceiveDataFilter('.*"buildingid":"' . $this->ReadPropertyString('BuildingID') . '","deviceid":"' . $this->ReadPropertyString('DeviceID') . '".*');
        $this->SetBufferX('ParentSocketID', $ParentSocketID);
        $this->RegisterMessage($ParentSocketID, IM_CHANGESTATUS);

        // Maintenance mode active?
        if ($this->ReadPropertyBoolean('MaintenanceMode') === true) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 207);
            return false;
        }

        if ($this->ParentIsActive() === false) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 201);
            return false;
        }

        $BuildingID = $this->ReadPropertyString('BuildingID');
        $DeviceID = $this->ReadPropertyString('DeviceID');
        $DeviceType = $this->ReadPropertyInteger('DeviceType');
        if (($BuildingID !== '') && ($DeviceID !== '') && ($DeviceType !== 999)) {
            // Everything ok
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);

            // Create variable with profiles and other objects
            $this->SetBuffer('Force_SendListToChilds', '1');
            $DeviceAR = $this->Device_GetListInfo();
            if ((@array_key_exists('DeviceID', $DeviceAR) === false) || (@array_key_exists('DeviceID', $DeviceAR) === NULL)) {
                //$this->SetInstanceStatus_IfDifferent($this->InstanceID, 202);
                return false;
            }

            // Permissions sufficient?
            $this->PermissionsOkCheck($DeviceAR);

            $result = $this->VariablesAndProfilesAndObjects_Create($DeviceAR);

            // Update all data - if all ok
            if ($result === true) {
                $this->Update();
            }
        } else {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 205);
            return false;
        }

        if (IPS_GetKernelVersion() >= 5.1) {
            $ReferenceIDsAR = $this->GetReferenceList();
            foreach ($ReferenceIDsAR as $ReferenceID) {
                $this->UnregisterReference($ReferenceID);
            }

            $ChildIDsAR = IPS_GetChildrenIDs($this->InstanceID);
            if (count($ChildIDsAR) > 0) {
                foreach ($ChildIDsAR as $ChildID) {
                    if (IPS_VariableExists($ChildID) === true) {
                        $this->RegisterReference($ChildID);
                    }
                }
            }
        }

        return true;
    }


    /**
     * GetConfigurationForm (dynamic generation of format data for the module instance)
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $DeviceType = $this->ReadPropertyInteger('DeviceType');
        if ($DeviceType === 0) {
            $DeviceType_String = $this->Translate('Air conditioner');
            $String_FanSpeed = $this->Translate('Fan speed');
            $DeviceSpecificFormActions = '{ "type": "Button", "label": "' . $String_FanSpeed . ' Auto", "onClick": "MEL_FanSpeed_Set($id, 0);" },
    { "type": "Button", "label": "' . $String_FanSpeed . ' 3", "onClick": "MEL_FanSpeed_Set($id, 3);" }';
        } elseif ($DeviceType === 1) {
            $DeviceType_String = $this->Translate('Heat pump');
            $DeviceType_LabelText = $this->Translate('The device type') . ' -' . $DeviceType_String . '- ' . $this->Translate('is currently not supported! Please contact the module creator!');
            $DeviceSpecificFormActions = '{ "type": "Label", "label": "' . $DeviceType_LabelText . '"}';
        } else {
            $DeviceType_String = $this->Translate('UNKNOWN');
            $DeviceType_LabelText = $this->Translate('The device type') . ' -' . $DeviceType_String . '- ' . $this->Translate('is currently not supported! Please contact the module creator!');
            $DeviceSpecificFormActions = '{ "type": "Label", "label": "' . $DeviceType_LabelText . '"}';
        }

        $FormValue_LBL_DeviceName = $this->Translate('Device-Name') . ':    ' . $this->ReadPropertyString('DeviceName');
        $FormValue_LBL_DeviceID = $this->Translate('Device-ID') . ':    ' . $this->ReadPropertyString('DeviceID');
        $FormValue_LBL_DeviceType = $this->Translate('Device-Type') . ': ' . $DeviceType_String;
        $FormValue_LBL_BuildingName = $this->Translate('Building-Name') . ': ' . $this->ReadPropertyString('BuildingName');
        $FormValue_LBL_FloorName = $this->Translate('Floor-Name') . ': ' . $this->ReadPropertyString('FloorName');
        $FormValue_LBL_AreaName = $this->Translate('Area-Name') . ': ' . $this->ReadPropertyString('AreaName');

        $FormData = '{
    "elements":
    [
        { "type": "Label", "label": "##### MELCloud Device v1.0 by Bayaro - www.bayaro.net #####" },
        { "type": "Label", "label": "##### 06.02.2019 - 07:40 #####"},
        { "type": "Label", "label": "' . $FormValue_LBL_DeviceName . '"},
        { "type": "Label", "label": "' . $FormValue_LBL_DeviceID . '"},
        { "type": "Label", "label": "' . $FormValue_LBL_DeviceType . '"},
        { "type": "Label", "label": "' . $FormValue_LBL_BuildingName . '"},
        { "type": "Label", "label": "' . $FormValue_LBL_FloorName . '"},
        { "type": "Label", "label": "' . $FormValue_LBL_AreaName . '"},
        { "type": "Label", "label": "----------------------------------------------------------------------------------------------------------------------------" },
        { "type": "CheckBox", "name": "VariablesExtra_System", "caption": "Show additional variables with system information" },
        { "type": "CheckBox", "name": "ShowDeviceImage", "caption": "Show device image" },
        { "type": "Label", "label": "----------------------------------------------------------------------------------------------------------------------------" },
        { "type": "CheckBox", "name": "MaintenanceMode", "caption": "Locking Operation (Maintenance Mode)" },
        { "type": "CheckBox", "name": "debug", "caption": "Debug" }
    ],
    "actions":
    [
        { "type": "Button", "label": "Update device data", "onClick": "MEL_Update($id);" },
        { "type": "Button", "label": "Switch device on", "onClick": "MEL_PowerState_Set($id, true);" },
        { "type": "Button", "label": "Switch device off", "onClick": "MEL_PowerState_Set($id, false);" },
        ' . $DeviceSpecificFormActions . '    
    ],
    "status":
    [
        { "code": 101, "icon": "active", "caption": "Creating instance" },
        { "code": 102, "icon": "active", "caption": "OK" },
        { "code": 201, "icon": "inactive", "caption": "I/O instance is inactive" },
        { "code": 202, "icon": "error", "caption": "ERROR // Device data could not be read from MELCloud" },
        { "code": 203, "icon": "error", "caption": "ERROR // Variable profiles could not be created" },
        { "code": 204, "icon": "error", "caption": "ERROR // Variables could not be created" },
        { "code": 205, "icon": "inactive", "caption": "Instance has not yet been configured" },
        { "code": 206, "icon": "error", "caption": "ERROR // Device-Image could not be created" },
        { "code": 207, "icon": "inactive", "caption": "Maintenance mode is active" }
    ]
}';
        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, 'FormData = ' . $FormData, 0);
            $this->SendDebug(__FUNCTION__, 'FormData (json last error msg) = ' . json_last_error_msg(), 0);
        }
        return $FormData;
    }


    /********** PUBLIC FUNCTIONS **********/

    /**
     * Update (update complete device data)
     *
     * @return bool
     */
    public function Update()
    {
        $result1 = $this->Device_GetData();
        if ($this->ReadPropertyBoolean('ShowDeviceImage') === true) {
            $result2 = $this->Device_GetImage();
            if ($result2 === false) {
                return false;
            }
        }

        if ($result1 === false) {
            return false;
        }

        return true;
    }


    /**
     * Device_GetData (get list with data from this device)
     *
     * @return false|array
     */
    public function Device_GetData()
    {
        $deviceDataAR = $this->Device_GetDataRAW();

        if ($deviceDataAR === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        $dataARtmp['Device'] = $deviceDataAR;
        $this->FillDeviceVariables($dataARtmp);

        $unsetAR = array('EffectiveFlags', 'LocalIPAddress', 'Name', 'WeatherObservations', 'HideVaneControls', 'HideDryModeControl', 'RoomTemperatureLabel', 'TemperatureIncrementOverride');
        $resultAR = array();
        foreach ($deviceDataAR as $index => $deviceDataARentry) {
            if (@array_search($index, $unsetAR) === false) {
                $resultAR[$index] = $deviceDataARentry;
            }
        }

        if (count($resultAR) > 0) {
            return $resultAR;
        }

        return $deviceDataAR;
    }


    /**
     * Device_GetDataRAW (get complete list with all data from this device)
     *
     * @return false|array
     */
    public function Device_GetDataRAW()
    {
        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        $dataAR['action'] = 'call_func';
        $dataAR['func'] = __FUNCTION__;
        $dataAR['func_param'] = $buildingid . ';;' . $deviceid;

        $result = $this->SendData($dataAR);

        if ($result === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        return $result;
    }


    /**
     * Device_GetListInfo (get device informations - Part of GetList only for this device)
     *
     * @return false|array
     */
    public function Device_GetListInfo()
    {
        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        $DevicesAR = $this->Devices_GetList();

        if ($DevicesAR === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        if ((isset($DevicesAR) === false) || (@array_key_exists('DeviceID', $DevicesAR[0]) === false) || (@array_key_exists('DeviceID', $DevicesAR[0]) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Device data could not be read from MELCloud') . ' // DevicesAR = ' . json_encode($DevicesAR), 0);
            return false;
        }

        foreach ($DevicesAR as $DeviceEntryAR) {
            if (($DeviceEntryAR['BuildingID'] == $buildingid) && ($DeviceEntryAR['DeviceID'] == $deviceid)) {
                $DeviceAR = $DeviceEntryAR;
                break;
            }
        }

        if ((@array_key_exists('DeviceID', $DeviceAR) === false) || (@array_key_exists('DeviceID', $DeviceAR) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('Device not found') . ' // DevicesAR = ' . json_encode($DeviceAR), 0);
            return false;
        }

        // fill buffers with valid min and max temperatures - for set-functions
        $minTemp = 9999;
        $maxTemp = 0;
        if (@array_key_exists('MinTempCoolDry', $DeviceAR['Device']) === true) {
            $minTemp_cooldry = $DeviceAR['Device']['MinTempCoolDry'];
            if ($minTemp_cooldry > 0) {
                $this->SetBuffer('MinTempCoolDry', $minTemp_cooldry);
                if ($minTemp_cooldry < $minTemp) {
                    $minTemp = $minTemp_cooldry;
                }
            }
        }
        if (@array_key_exists('MaxTempCoolDry', $DeviceAR['Device']) === true) {
            $maxTemp_cooldry = $DeviceAR['Device']['MaxTempCoolDry'];
            if ($maxTemp_cooldry > 0) {
                $this->SetBuffer('MaxTempCoolDry', $maxTemp_cooldry);
                if ($maxTemp_cooldry > $maxTemp) {
                    $maxTemp = $maxTemp_cooldry;
                }
            }
        }
        if (@array_key_exists('MinTempHeat', $DeviceAR['Device']) === true) {
            $minTemp_heat = $DeviceAR['Device']['MinTempHeat'];
            if ($minTemp_heat > 0) {
                $this->SetBuffer('MinTempHeat', $minTemp_heat);
                if ($minTemp_heat < $minTemp) {
                    $minTemp = $minTemp_heat;
                }
            }
        }
        if (@array_key_exists('MaxTempHeat', $DeviceAR['Device']) === true) {
            $maxTemp_heat = $DeviceAR['Device']['MaxTempHeat'];
            if ($maxTemp_heat > 0) {
                $this->SetBuffer('MaxTempHeat', $maxTemp_heat);
                if ($maxTemp_heat > $maxTemp) {
                    $maxTemp = $maxTemp_heat;
                }
            }
        }
        if (@array_key_exists('MinTempAutomatic', $DeviceAR['Device']) === true) {
            $minTemp_auto = $DeviceAR['Device']['MinTempAutomatic'];
            if ($minTemp_auto > 0) {
                $this->SetBuffer('MinTempAutomatic', $minTemp_auto);
                if ($minTemp_auto < $minTemp) {
                    $minTemp = $minTemp_auto;
                }
            }
        }
        if (@array_key_exists('MaxTempAutomatic', $DeviceAR['Device']) === true) {
            $maxTemp_auto = $DeviceAR['Device']['MaxTempAutomatic'];
            if ($maxTemp_auto > 0) {
                $this->SetBuffer('MaxTempAutomatic', $maxTemp_auto);
                if ($maxTemp_auto > $maxTemp) {
                    $maxTemp = $maxTemp_auto;
                }
            }
        }

        if ($minTemp !== 9999) {
            $this->SetBuffer('MinTemp', $minTemp);
        }
        if ($maxTemp !== 0) {
            $this->SetBuffer('MaxTemp', $maxTemp);
        }

        // fill buffers with valid values for some device features - for set-functions
        if (@array_key_exists('ModelSupportsFanSpeed', $DeviceAR['Device']) === true) {
            if ($DeviceAR['Device']['ModelSupportsFanSpeed'] === true) {
                if (@array_key_exists('NumberOfFanSpeeds', $DeviceAR['Device']) === true) {
                    $NumberOfFanSpeeds = $DeviceAR['Device']['NumberOfFanSpeeds'];
                    if ($NumberOfFanSpeeds > 0) {
                        $this->SetBuffer('NumberOfFanSpeeds', $NumberOfFanSpeeds);
                    }
                }
            }
        }
        if (@array_key_exists('ModelSupportsVaneHorizontal', $DeviceAR['Device']) === true) {
            $ModelSupportsVaneHorizontal = $DeviceAR['Device']['ModelSupportsVaneHorizontal'];
            if ($ModelSupportsVaneHorizontal === true) {
                $this->SetBuffer('ModelSupportsVaneHorizontal', 1);
            } else {
                $this->SetBuffer('ModelSupportsVaneHorizontal', 0);
            }
        }
        if (@array_key_exists('ModelSupportsVaneVertical', $DeviceAR['Device']) === true) {
            $ModelSupportsVaneVertical = $DeviceAR['Device']['ModelSupportsVaneVertical'];
            if ($ModelSupportsVaneVertical === true) {
                $this->SetBuffer('ModelSupportsVaneVertical', 1);
            } else {
                $this->SetBuffer('ModelSupportsVaneVertical', 0);
            }
        }

        // fill buffers with valid values operation modes - for set-functions
        $operationModesAR = array();
        // cooling mode
        if ($this->Check_Feature($DeviceAR, 'CanCool') === true) {
            $operationModesAR[3] = 1;
        } else {
            $operationModesAR[3] = 0;
        }
        // dry
        if ($this->Check_Feature($DeviceAR, 'CanDry') === true) {
            $operationModesAR[7] = 1;
        } else {
            $operationModesAR[7] = 0;
        }
        // auto mode
        if ($this->Check_Feature($DeviceAR, 'ModelSupportsAuto') === true) {
            $operationModesAR[8] = 1;
        } else {
            $operationModesAR[8] = 0;
        }
        // heat
        if ($this->Check_Feature($DeviceAR, 'CanHeat') === true) {
            $operationModesAR[1] = 1;
        } else {
            $operationModesAR[1] = 0;
        }

        foreach ($operationModesAR as $opModeNr => $opModeAvail) {
            $this->SetBufferX('OperationMode_' . $opModeNr, $opModeAvail);
        }

        return $DeviceAR;
    }


    /**
     * Device_GetImage (get the image of the device - available if uploaded before via cloud/app)
     *
     * @return false|array
     */
    public function Device_GetImage()
    {
        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        $dataAR['action'] = 'call_func';
        $dataAR['func'] = __FUNCTION__;
        $dataAR['func_param'] = $buildingid . ';;' . $deviceid;

        $resultAR = $this->SendData($dataAR);

        if ((@array_key_exists('image', $resultAR) === false) || (@array_key_exists('image', $resultAR) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The device image could not be read. Either there is no image or there was a problem.'), 0);
            return false;
        }

        if ($this->ReadPropertyBoolean('ShowDeviceImage') === true) {
            $imageID = @$this->GetIDForIdent('Device_Image');
            if ($imageID > 0) {
                IPS_SetMediaContent($imageID, $resultAR['image']);
                IPS_SendMediaEvent($imageID);
            } else {
                return false;
            }
        }

        return $resultAR;
    }


    /**
     * Device_GetPresets (get presets and update variable profile)
     *
     * @return false|array
     */
    public function Device_GetPresets()
    {
        $DeviceAR = $this->Device_GetListInfo();

        if ((@array_key_exists('Presets', $DeviceAR) === false) || (@array_key_exists('Presets', $DeviceAR) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('No presets available'), 0);
            return false;
        }

        $PresetsAR = $DeviceAR['Presets'];

        $this->VariablesProfiles_Update('Presets', $PresetsAR);

        $this->SetBufferX('MultiBuffer_PresetsAR', $PresetsAR);

        return $PresetsAR;
    }


    /**
     * Devices_GetList (get list with all devices and all device-information)
     *
     * @return false|array
     */
    public function Devices_GetList()
    {
        $force = $this->GetBuffer('Force_SendListToChilds');

        $dataAR['action'] = 'call_func';
        $dataAR['func'] = __FUNCTION__;

        if ($force === '1') {
            $dataAR['func_param'] = 'force';
            $this->SetBuffer('Force_SendListToChilds', '0');
        }

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
     * @return false|array
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


    /**
     * DeviceInstance_Configuration (possibility to automatically configure a device instance - set device informations, create variables and variables profiles)
     *
     * @param string $BuildingID
     * @param string $DeviceID
     * @param string $DeviceType (0 = Air condition)
     * @return bool
     */
    public function DeviceInstance_Configuration(string $BuildingID, string $DeviceID, string $DeviceType)
    {
        if ((($BuildingID === '') || ($DeviceID === '') || ($DeviceType === '')) || (($BuildingID === NULL) || ($DeviceID === NULL) || ($DeviceType === NULL))) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('Building-ID') . $this->Translate(' and ') . $this->Translate('Device-ID') . $this->Translate(' and ') . $this->Translate('Device-Type') . $this->Translate(' must not be empty!'), 0);
            return false;
        }

        @IPS_SetProperty($this->InstanceID, 'BuildingID', $BuildingID);
        @IPS_SetProperty($this->InstanceID, 'DeviceID', $DeviceID);
        @IPS_SetProperty($this->InstanceID, 'DeviceType', $DeviceType);
        if (IPS_HasChanges($this->InstanceID) === true) {
            $result = @IPS_ApplyChanges($this->InstanceID);
            if ($result === false) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Error setting ') . $this->Translate('Building-ID') . $this->Translate(' and ') . $this->Translate('Device-ID') . $this->Translate(' and ') . $this->Translate('Device-Type'), 0);
                return false;
            }
        }

        $DeviceAR = $this->Device_GetListInfo();

        if ((@array_key_exists('DeviceID', $DeviceAR) === false) || (@array_key_exists('DeviceID', $DeviceAR) === NULL)) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 202);
            return false;
        }

        // set instance propertys
        if (@array_key_exists('BuildingName', $DeviceAR) === true) {
            if (($DeviceAR['BuildingName'] !== '') && ($DeviceAR['BuildingName'] !== NULL) && ($this->ReadPropertyString('BuildingName') !== $DeviceAR['BuildingName'])) {
                @IPS_SetProperty($this->InstanceID, 'BuildingName', $DeviceAR['BuildingName']);
            }
        }
        if (@array_key_exists('DeviceName', $DeviceAR) === true) {
            if (($DeviceAR['DeviceName'] !== '') && ($DeviceAR['DeviceName'] !== NULL) && ($this->ReadPropertyString('DeviceName') !== $DeviceAR['DeviceName'])) {
                @IPS_SetProperty($this->InstanceID, 'DeviceName', $DeviceAR['DeviceName']);
            }
        }
        if (@array_key_exists('DeviceType', $DeviceAR) === true) {
            if (($DeviceAR['Type'] !== '') && ($DeviceAR['Type'] !== NULL) && ($this->ReadPropertyInteger('DeviceType') !== $DeviceAR['DeviceType'])) {
                @IPS_SetProperty($this->InstanceID, 'DeviceType', $DeviceAR['Type']);
            }
        }
        if (@array_key_exists('FloorID', $DeviceAR) === true) {
            if (($DeviceAR['FloorID'] !== '') && ($DeviceAR['FloorID'] !== NULL) && ($this->ReadPropertyString('FloorID') !== $DeviceAR['FloorID'])) {
                @IPS_SetProperty($this->InstanceID, 'FloorID', $DeviceAR['FloorID']);
            }
        }
        if (@array_key_exists('FloorName', $DeviceAR) === true) {
            if (($DeviceAR['FloorName'] !== '') && ($DeviceAR['FloorName'] !== NULL) && ($this->ReadPropertyString('FloorName') !== $DeviceAR['FloorName'])) {
                @IPS_SetProperty($this->InstanceID, 'FloorName', $DeviceAR['FloorName']);
            }
        }
        if (@array_key_exists('AreaID', $DeviceAR) === true) {
            if (($DeviceAR['AreaID'] !== '') && ($DeviceAR['AreaID'] !== NULL) && ($this->ReadPropertyString('AreaID') !== $DeviceAR['AreaID'])) {
                @IPS_SetProperty($this->InstanceID, 'AreaID', $DeviceAR['AreaID']);
            }
        }
        if (@array_key_exists('AreaName', $DeviceAR) === true) {
            if (($DeviceAR['AreaName'] !== '') && ($DeviceAR['AreaName'] !== NULL) && ($this->ReadPropertyString('AreaName') !== $DeviceAR['AreaName'])) {
                @IPS_SetProperty($this->InstanceID, 'AreaName', $DeviceAR['AreaName']);
            }
        }

        // Set instance name
        if (@array_key_exists('DeviceName', $DeviceAR) === true) {
            if ($DeviceAR['DeviceName'] !== '') {
                IPS_SetName($this->InstanceID, $this->Translate('MELCloud Device') . ' - ' . $DeviceAR['DeviceName']);
            } else {
                IPS_SetName($this->InstanceID, $this->Translate('MELCloud Device') . ' - ' . $DeviceAR['DeviceID']);
            }
        }

        // Apply changes
        $ApplyChangesDone = false;
        if (IPS_HasChanges($this->InstanceID) === true) {
            $result = @IPS_ApplyChanges($this->InstanceID);
            if ($result === false) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Error setting ') . $this->Translate('Building-ID') . $this->Translate(' and ') . $this->Translate('Device-ID') . $this->Translate(' and ') . $this->Translate('Device-Type'), 0);
                return false;
            }
            $ApplyChangesDone = $result;
        }

        // Update all data
        if ($ApplyChangesDone === false) {
            // Creating variable profiles and variables
            $result = $this->VariablesAndProfilesAndObjects_Create($DeviceAR);

            // Update all data - if all ok
            if ($result === true) {
                $this->Update();
            }
        }

        return true;
    }


    /**
     * FanSpeed_Set (fan speed control)
     *
     * @param $value
     * @return bool
     */
    public function FanSpeed_Set(int $value)
    {
        $NumberOfFanSpeeds = $this->GetBuffer('NumberOfFanSpeeds');
        if ($NumberOfFanSpeeds !== '') {
            if (($value > (int)$NumberOfFanSpeeds) || ($value < 0)) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Value is invalid for this function! Value = ') . $value, 0);
                return false;
            }
        }

        $resultAR = $this->Data_Set('SetFanSpeed', $value);

        if (@array_key_exists('SetFanSpeed', $resultAR) === true) {
            $this->FillDeviceVariables($resultAR);
            if ($resultAR['SetFanSpeed'] == $value) {
                return true;
            }
        }

        return false;
    }


    /**
     * OperationMode_Set (operation mode control)
     *
     * @param $value
     * @return bool
     */
    public function OperationMode_Set(int $value)
    {
        $ModelSupportsOperationModeX = $this->GetBuffer('OperationMode_' . $value);
        if ($ModelSupportsOperationModeX !== '') {
            if ((int)$ModelSupportsOperationModeX === 1) {
                $resultAR = $this->Data_Set('OperationMode', $value);

                if (@array_key_exists('OperationMode', $resultAR) === true) {
                    $this->FillDeviceVariables($resultAR);
                    if ($resultAR['OperationMode'] == $value) {
                        return true;
                    }
                }

                return false;
            }
        }

        $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Operation mode is not supported by this device! Value = ') . $value, 0);
        return false;
    }


    /**
     * PowerState_Set (powerstate control)
     *
     * @param $value
     * @return bool
     */
    public function PowerState_Set(bool $value)
    {
        $resultAR = $this->Data_Set('Power', $value);

        if (@array_key_exists('Power', $resultAR) === true) {
            $this->FillDeviceVariables($resultAR);
            if ($resultAR['Power'] == $value) {
                return true;
            }
        }

        return false;
    }


    /**
     * Preset_Set (preset control)
     *
     * @param $value
     * @return bool
     */
    public function Preset_Set(int $value)
    {
        $PresetsAR = $this->GetBufferX('MultiBuffer_PresetsAR');
        if ((@array_key_exists($value, $PresetsAR) === false) || (@array_key_exists($value, $PresetsAR) === NULL)) {
            $PresetsAR = $this->Device_GetPresets();

            if ((@array_key_exists($value, $PresetsAR) === false) || (@array_key_exists($value, $PresetsAR) === NULL)) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('Selected preset is not available'), 0);
                return false;
            }
        }

        $PresetAR = $PresetsAR[$value];

        $DeviceDataRawAR = $this->Device_GetDataRAW();
        if ((@array_key_exists('EffectiveFlags', $DeviceDataRawAR) === false) || (@array_key_exists('Number', $PresetAR) === false) || (@array_key_exists('EffectiveFlags', $DeviceDataRawAR) === NULL) || (@array_key_exists('Number', $PresetAR) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The data required for the action is not available!') . ' // PresetAR = ' . json_encode($PresetAR) . ' // DeviceDataRawAR = ' . json_encode($DeviceDataRawAR), 0);
            return false;
        }

        if (@array_key_exists('FanSpeed', $PresetAR) === true) {
            $tmp_FanSpeed = $PresetAR['FanSpeed'];
            $PresetAR['SetFanSpeed'] = $tmp_FanSpeed;
            unset($PresetAR['FanSpeed']);
        }
        if (@array_key_exists('ID', $PresetAR) === true) {
            unset($PresetAR['ID']);
        }
        if (@array_key_exists('Client', $PresetAR) === true) {
            unset($PresetAR['Client']);
        }
        if (@array_key_exists('DeviceLocation', $PresetAR) === true) {
            unset($PresetAR['DeviceLocation']);
        }
        if (@array_key_exists('Number', $PresetAR) === true) {
            unset($PresetAR['Number']);
        }
        if (@array_key_exists('Configuration', $PresetAR) === true) {
            unset($PresetAR['Configuration']);
        }
        if (@array_key_exists('NumberDescription', $PresetAR) === true) {
            unset($PresetAR['NumberDescription']);
        }

        $Array_SET = @array_replace($DeviceDataRawAR, $PresetAR);
        if ($Array_SET === NULL) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The data required for the action is not available!'), 0);
            return false;
        }

        if ((@array_key_exists('EffectiveFlags', $Array_SET) === false) || (@array_key_exists('EffectiveFlags', $Array_SET) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The data required for the action is not available!') . ' // Array_SET = ' . json_encode($Array_SET), 0);
            return false;
        }

        $this->SetValue_IfDifferent('Preset', 99);

        $resultAR = $this->Data_Set('Preset', $Array_SET);
        if (@array_key_exists('EffectiveFlags', $resultAR) === true) {
            $this->FillDeviceVariables($resultAR);
            if ($resultAR['EffectiveFlags'] === 287) {
                return true;
            }
        }

        return false;
    }


    /**
     * Temperature_Set (temperature control // depending on operating mode different minimum and maximum values possible/valid)
     *
     * @param $value
     * @return bool
     */
    public function Temperature_Set(float $value)
    {
        $deviceType = $this->ReadPropertyInteger('DeviceType');
        $operationMode = GetValue($this->GetIDForIdent('OperationMode'));

        switch ($operationMode) {
            case 1:
                if ($deviceType === 0) {
                    $min = $this->GetBuffer('MinTempCoolDry');
                    $max = $this->GetBuffer('MaxTempCoolDry');
                }
                break;

            case 3:
                if ($deviceType === 0) {
                    $min = $this->GetBuffer('MinTempHeat');
                    $max = $this->GetBuffer('MaxTempHeat');
                }
                break;

            case 8:
                if ($deviceType === 0) {
                    $min = $this->GetBuffer('MinTempAutomatic');
                    $max = $this->GetBuffer('MaxTempAutomatic');
                }
                break;
        }

        if ((isset($min) === true) && (isset($max) === true)) {
            if (($min !== '') && ($max !== '')) {
                if (($value < $min) || ($value > $max)) {
                    $operationModeText = GetValueFormatted($this->GetIDForIdent('OperationMode'));
                    $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('For operation mode') . ' "' . $operationModeText . '" ' . $this->Translate(', the following temperature range applies') . ': min = ' . $min . ', max = ' . $max, 0);
                    IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('For operation mode') . ' "' . $operationModeText . '" ' . $this->Translate(', the following temperature range applies') . ': min = ' . $min . ', max = ' . $max);
                    return false;
                }
            }
        }

        $temperatureWithDecimalDot = @number_format($value, 1, '.', '');
        $resultAR = $this->Data_Set('SetTemperature', $temperatureWithDecimalDot);

        if (@array_key_exists('SetTemperature', $resultAR) === true) {
            $this->FillDeviceVariables($resultAR);
            if ($resultAR['SetTemperature'] == $value) {
                return true;
            }
        }

        return false;
    }


    /**
     * VaneHorizontal_Set (horizontal vane control)
     *
     * @param $value
     * @return bool
     */
    public function VaneHorizontal_Set(int $value)
    {
        $ModelSupportsVaneHorizontal = $this->GetBuffer('ModelSupportsVaneHorizontal');
        if ($ModelSupportsVaneHorizontal !== '') {
            if ((int)$ModelSupportsVaneHorizontal === 0) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Value is invalid for this function! Value = ') . $value, 0);
                return false;
            }
        }

        $resultAR = $this->Data_Set('VaneHorizontal', $value);

        if (@array_key_exists('VaneHorizontal', $resultAR) === true) {
            $this->FillDeviceVariables($resultAR);
            if ($resultAR['VaneHorizontal'] == $value) {
                return true;
            }
        }

        return false;
    }


    /**
     * VaneVertical_Set (vertical vane control)
     *
     * @param $value
     * @return bool
     */
    public function VaneVertical_Set(int $value)
    {
        $ModelSupportsVaneVertical = $this->GetBuffer('ModelSupportsVaneVertical');
        if ($ModelSupportsVaneVertical !== '') {
            if ((int)$ModelSupportsVaneVertical === 0) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Value is invalid for this function! Value = ') . $value, 0);
                return false;
            }
        }

        $resultAR = $this->Data_Set('VaneVertical', $value);

        if (@array_key_exists('VaneVertical', $resultAR) === true) {
            $this->FillDeviceVariables($resultAR);
            if ($resultAR['VaneVertical'] == $value) {
                return true;
            }
        }

        return false;
    }


    /**
     * Weather_Get (get the weather data/forecast for this device)
     *
     * @return array|bool
     */
    public function Weather_Get()
    {
        $dataAR = $this->Device_GetDataRAW();
        if (@array_key_exists('WeatherObservations', $dataAR) === true) {
            return $dataAR['WeatherObservations'];
        }

        $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // No weather data available for this device in MELCloud'), 0);
        return false;
    }



    /********** INTERNAL FUNCTIONS **********/

    /**
     * Data_Set (send command to control a device or change a setting)
     *
     * @param $action
     * @param $parameter
     * @return false|array
     */
    private function Data_Set($action, $parameter)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        $maintenanceModeActive = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($maintenanceModeActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Maintenance mode activated in the device instance! No control of the device possible!'), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('INFO // Maintenance mode activated in the device instance! No control of the device possible!'));
            return false;
        }

        if (is_array($parameter) === true) {
            $parameter = json_encode($parameter);
        }

        $dataAR['action'] = 'call_func';
        $dataAR['func'] = __FUNCTION__;
        $dataAR['func_param'] = $buildingid . ';;' . $deviceid . ';;' . $action . ';;' . $parameter;

        $result = $this->SendData($dataAR);

        if (@array_key_exists('error', $result) === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data') . ' // ' . $this->Translate('Error') . ' = ' . $result['error'], 0);
            return false;
        }

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Data was sent successfully'), 0);
        }

        return $result;
    }


    /**
     * Check_Feature (check if a device supports a certain feature)
     *
     * @param $DeviceAR
     * @param $FeatureName
     * @return bool
     */
    private function Check_Feature($DeviceAR, $FeatureName)
    {
        if (@array_key_exists($FeatureName, $DeviceAR['Device']) === true) {
            if ($DeviceAR['Device'][$FeatureName] === true) {
                return true;
            }
        }

        return false;
    }


    /**
     * FillDeviceVariables (update device variables)
     *
     * @param $DeviceDataAR
     * @return bool
     */
    private function FillDeviceVariables($DeviceDataAR)
    {
        if ($DeviceDataAR === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        // update of the variable
        if (@array_key_exists('RoomTemperature', $DeviceDataAR['Device']) === true) {
            $this->SetValue('TemperatureRoom', $DeviceDataAR['Device']['RoomTemperature']);
        }
        if (@array_key_exists('RoomTemperature', $DeviceDataAR) === true) {
            $this->SetValue('TemperatureRoom', $DeviceDataAR['RoomTemperature']);
        }
        if (@array_key_exists('SetTemperature', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('TemperatureSET', $DeviceDataAR['Device']['SetTemperature']);
        }
        if (@array_key_exists('SetTemperature', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('TemperatureSET', $DeviceDataAR['SetTemperature']);
        }
        if (@array_key_exists('FanSpeed', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('FanSpeed', $DeviceDataAR['Device']['FanSpeed']);
        }
        if (@array_key_exists('FanSpeed', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('FanSpeed', $DeviceDataAR['FanSpeed']);
        }
        if (@array_key_exists('SetFanSpeed', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('FanSpeed', $DeviceDataAR['Device']['SetFanSpeed']);
        }
        if (@array_key_exists('SetFanSpeed', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('FanSpeed', $DeviceDataAR['SetFanSpeed']);
        }
        if (@array_key_exists('OperationMode', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('OperationMode', $DeviceDataAR['Device']['OperationMode']);
        }
        if (@array_key_exists('OperationMode', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('OperationMode', $DeviceDataAR['OperationMode']);
        }
        if (@array_key_exists('VaneHorizontalDirection', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('VaneHorizontal', $DeviceDataAR['Device']['VaneHorizontalDirection']);
        }
        if (@array_key_exists('VaneHorizontalDirection', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('VaneHorizontal', $DeviceDataAR['VaneHorizontalDirection']);
        }
        if (@array_key_exists('VaneHorizontal', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('VaneHorizontal', $DeviceDataAR['Device']['VaneHorizontal']);
        }
        if (@array_key_exists('VaneHorizontal', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('VaneHorizontal', $DeviceDataAR['VaneHorizontal']);
        }
        if (@array_key_exists('VaneVerticalDirection', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('VaneVertical', $DeviceDataAR['Device']['VaneVerticalDirection']);
        }
        if (@array_key_exists('VaneVerticalDirection', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('VaneVertical', $DeviceDataAR['VaneVerticalDirection']);
        }
        if (@array_key_exists('VaneVertical', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('VaneVertical', $DeviceDataAR['Device']['VaneVertical']);
        }
        if (@array_key_exists('VaneVertical', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('VaneVertical', $DeviceDataAR['VaneVertical']);
        }
        if (@array_key_exists('Offline', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('DeviceCloudState', !$DeviceDataAR['Device']['Offline']);
        }
        if (@array_key_exists('Offline', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('DeviceCloudState', !$DeviceDataAR['Offline']);
        }
        if (@array_key_exists('Power', $DeviceDataAR['Device']) == true) {
            $this->SetValue_IfDifferent('PowerState', $DeviceDataAR['Device']['Power']);
        }
        if (@array_key_exists('Power', $DeviceDataAR) == true) {
            $this->SetValue_IfDifferent('PowerState', $DeviceDataAR['Power']);
        }
        if (@array_key_exists('LastTimeStamp', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('LastCommunicationTimestamp', strtotime($DeviceDataAR['Device']['LastTimeStamp']));
        }
        if (@array_key_exists('LastTimeStamp', $DeviceDataAR) === true) {
            $this->SetValue_IfDifferent('LastCommunicationTimestamp', strtotime($DeviceDataAR['LastTimeStamp']));
        }

        if (@array_key_exists('HasError', $DeviceDataAR['Device']) === true) {
            $this->SetValue_IfDifferent('HasError', $DeviceDataAR['Device']['HasErrorMessages']);
            if ($DeviceDataAR['Device']['HasError'] === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('Error-Code') . ' = ' . $DeviceDataAR['ErrorCode'], 0);
                IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('Error-Code') . ' = ' . $DeviceDataAR['ErrorCode']);
            }
            if (@array_key_exists('ErrorCode', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('ErrorCode', $DeviceDataAR['Device']['ErrorCode']);
            }
        }

        if ($this->ReadPropertyBoolean('VariablesExtra_System') === true) {
            if (@array_key_exists('InstallationDate', $DeviceDataAR) === true) {
                $this->SetValue_IfDifferent('InstallationDate', strtotime($DeviceDataAR['InstallationDate']));
            }
            if (@array_key_exists('LastServiceDate', $DeviceDataAR) === true) {
                $this->SetValue_IfDifferent('LastServiceDate', strtotime($DeviceDataAR['LastServiceDate']));
            }
            if (@array_key_exists('WifiAdapterStatus', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('WifiAdapterStatus', $DeviceDataAR['Device']['WifiAdapterStatus']);
            }
            if (@array_key_exists('WifiSignalStrength', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('WifiSignalStrength', $DeviceDataAR['Device']['WifiSignalStrength']);
            }
            if (@array_key_exists('MacAddress', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('MacAddress', strtoupper($DeviceDataAR['Device']['MacAddress']));
            }
            if (@array_key_exists('SerialNumber', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('SerialNumber', $DeviceDataAR['Device']['SerialNumber']);
            }
            if (@array_key_exists('DiagnosticMode', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('DiagnosticMode', $DeviceDataAR['Device']['DiagnosticMode']);
            }
            if (@array_key_exists('LastReset', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('LastReset', strtotime($DeviceDataAR['Device']['LastReset']));
            }
            if (@array_key_exists('FirmwareAppVersion', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('FirmwareAppVersion', $DeviceDataAR['Device']['FirmwareAppVersion']);
            }
            if (@array_key_exists('FirmwareWebVersion', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('FirmwareWebVersion', $DeviceDataAR['Device']['FirmwareWebVersion']);
            }
            if (@array_key_exists('FirmwareWlanVersion', $DeviceDataAR['Device']) === true) {
                $this->SetValue_IfDifferent('FirmwareWlanVersion', $DeviceDataAR['Device']['FirmwareWlanVersion']);
            }
        }

        return true;
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
                        //$this->ApplyChanges();
                        break;
                }
                break;

            case IM_CHANGESTATUS:
                if ($SenderID === (int)$this->GetBufferX('ParentSocketID')) {
                    if ($Data[0] === IS_ACTIVE) {
                        $Run_TimeDiffLastApplyChanges = time() - $this->GetBufferX('Run_LastApplyChanges');
                        if ($Run_TimeDiffLastApplyChanges > 30) {
                            $this->ApplyChanges();
                        }
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
            if ($ParentInstanceInfoAR['InstanceStatus'] === IS_ACTIVE) {
                return true;
            }
        }

        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 201); // Parent is inactive
        $this->SendDebug(__FUNCTION__, $this->Translate('I/O instance is inactive'), 0);

        return false;
    }


    /**
     * PermissionsCheck (Check if this account has enough privileges to control this device)
     *
     * @param $DeviceAR
     * @return bool
     */
    private function PermissionsOkCheck($DeviceAR)
    {
        $permissionsOkFlag = true;
        if (@array_key_exists('Permissions', $DeviceAR) === true) {
            foreach ($DeviceAR['Permissions'] as $PermissionName => $PermissionValue) {
                if ($PermissionValue === false) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('WARNING') . ' // ' . $this->Translate('This user does not have permission for the following on this device') . ': ' . $PermissionName, 0);
                    IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('WARNING') . ' // ' . $this->Translate('This user does not have permission for the following on this device') . ': ' . $PermissionName);
                    $this->SetBufferX('PERM_' . $PermissionName, false);
                    $permissionsOkFlag = false;
                }

                $this->SetBufferX('PERM_' . $PermissionName, $PermissionValue);
            }
        }

        return $permissionsOkFlag;
    }


    /** ReceiveData (internal SDK function to receive data from a parent instance)
     *
     * @param $json
     * @return bool|void
     */
    public function ReceiveData($json)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $dataAR = json_decode($json, true);

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Received data') . ' = ' . $json, 0);
        }

        $this->ReceiveData_Processing($dataAR);
    }


    /**
     * ReceiveData_Processing (further processing of the data received from a parent instance)
     *
     * @param $dataAR
     * @return bool
     */
    private function ReceiveData_Processing($dataAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        $notforthisdevice = false;
        if ((@array_key_exists('buildingid', $dataAR) === true) && (@array_key_exists('deviceid', $dataAR) === true)) {
            if (($buildingid === '') || ($buildingid != $dataAR['buildingid'])) {
                if (($deviceid === '') || ($deviceid != $dataAR['deviceid'])) {
                    $notforthisdevice = true;
                }
            }
        }

        if (@array_key_exists('action', $dataAR) === true) {
            if ($dataAR['action'] === 'GetList_ByIOfunction') {
                if ((@array_key_exists('data', $dataAR) === false) || (@array_key_exists('data', $dataAR) === NULL)) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The data required for the action is not available!') . ' // ' . $this->Translate('Data') . ' = ' . json_encode($dataAR), 0);
                    return false;
                }
                $DeviceDataAR = $dataAR['data'];

                if ($notforthisdevice === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('WARNING // The received data does not seem to be for this device. Please contact the module creator!') . ' // ' . $this->Translate('Data') . ' = ' . json_encode($dataAR), 0);
                    return false;
                }

                //// From here only functions which are only for this device

                // Update variable profiles with device-presets
                if (@array_key_exists('Presets', $DeviceDataAR) === true) {
                    $this->VariablesProfiles_Update('Presets', $DeviceDataAR['Presets']);
                }

                // Update instance propertys
                $PropertyAR = array('DeviceName', 'BuildingName', 'FloorName', 'AreaName');
                foreach ($PropertyAR as $PropertyName) {
                    if (@array_key_exists($PropertyName, $DeviceDataAR) === true) {
                        if (($DeviceDataAR[$PropertyName] !== '') && ($DeviceDataAR[$PropertyName] !== NULL)) {
                            $PropertyValueX = $this->ReadPropertyString($PropertyName);
                            if ($PropertyValueX !== $DeviceDataAR[$PropertyName]) {
                                $result_SetProperty = @IPS_SetProperty($this->InstanceID, $PropertyName, $DeviceDataAR[$PropertyName]);
                                if ($DebugActive === true) {
                                    if ($result_SetProperty === true) {
                                        $this->SendDebug(__FUNCTION__, $this->Translate('Instance property changed successfully') . ' // ' . $this->Translate('Property') . ' = ' . $PropertyName . ' // ' . $this->Translate('Old value') . ' = ' . $PropertyValueX . ' // ' . $this->Translate('New value') . ' = ' . $DeviceDataAR[$PropertyName], 0);
                                    } else {
                                        $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Change instance property was not successful') . ' // ' . $this->Translate('Eigenschaft') . ' = ' . $PropertyName . ' // ' . $this->Translate('Old value') . ' = ' . $PropertyValueX . ' // ' . $this->Translate('New value') . ' = ' . $DeviceDataAR[$PropertyName], 0);
                                    }
                                }
                            }
                        }
                    }
                }

                // Apply instance property changes
                if (IPS_HasChanges($this->InstanceID) === true) {
                    $result_ApplyChanges = @IPS_ApplyChanges($this->InstanceID);
                    if ($DebugActive === true) {
                        if ($result_ApplyChanges === true) {
                            $this->SendDebug(__FUNCTION__, $this->Translate('Changes to instance properties saved successfully'), 0);
                        } else {
                            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Changes to instance properties could not be saved'), 0);
                        }
                    }
                }

                // Update of the variables
                $this->FillDeviceVariables($DeviceDataAR);
            }
        }

        return true;
    }


    /**
     * RequestAction (internal SDK function to evaluate action and call corresponding function)
     *
     * @param $Ident
     * @param $Value
     * @return bool|void
     */
    public function RequestAction($Ident, $Value)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, "Ident '" . $Ident . "' // Value '" . $Value . "'", 0);
        }

        $this->SetValue($Ident, $Value);

        switch ($Ident) {
            case 'FanSpeed':
                $this->FanSpeed_Set($Value);
                break;

            case 'OperationMode':
                $this->OperationMode_Set($Value);
                break;

            case 'PowerState':
                $this->PowerState_Set($Value);
                break;

            case 'Preset':
                $this->Preset_Set($Value);
                break;

            case 'TemperatureSET':
                $this->Temperature_Set($Value);
                break;

            case 'VaneHorizontal':
                $this->VaneHorizontal_Set($Value);
                break;

            case 'VaneVertical':
                $this->VaneVertical_Set($Value);
                break;
        }
    }


    /**
     * SendData (Sending data to the parent module instance)
     *
     * @param $dataAR
     * @return false|array
     */
    private function SendData($dataAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($this->ParentIsActive() === false) {
            return false;
        }

        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        $dataAR['DataID'] = '{AF4E8025-2CFF-4B94-9C43-D68B868F281D}';
        $dataAR['buildingid'] = $buildingid;
        $dataAR['deviceid'] = $deviceid;

        if ($DebugActive === true) {
            if (@array_key_exists('action', $dataAR) === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Send command') . '" ' . $dataAR['action'] . '" ' . $this->Translate('with the following data') . ': ' . json_encode($dataAR), 0);
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('Send command with following data') . ' = ' . json_encode($dataAR), 0);
            }
        }

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

        if (@array_key_exists('DataID', $result) === true) {
            unset($result['DataID']);
        }

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Response to the last command sent') . ' = ' . json_encode($result), 0);
        }

        return $result;
    }


    /**
     * SetInstanceStatus_IfDifferent (override of internal SDK function)
     *
     * @param int $InstanceID
     * @param $Status
     * @return bool
     */
    private function SetInstanceStatus_IfDifferent($InstanceID, $Status)
    {
        $result = true;
        $ParentInstanceInfoAR = IPS_GetInstance($InstanceID);
        if ($ParentInstanceInfoAR['InstanceStatus'] !== $Status) {
            $result = $this->SetStatus($Status);
        }

        return $result;
    }


    /**
     * VariablesAndProfiles_Create (create the required variable profiles and variables)
     *
     * @param $DeviceAR
     * @return bool
     */
    private function VariablesAndProfilesAndObjects_Create($DeviceAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $buildingid = $this->ReadPropertyString('BuildingID');
        $deviceid = $this->ReadPropertyString('DeviceID');

        // check for necessary data
        if ((@array_key_exists('Type', $DeviceAR) === false) || (@array_key_exists('Type', $DeviceAR) === NULL)) {
            if ($DebugActive === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Device type not available - Data unusable for further use') . ' // DeviceAR = ' . json_encode($DeviceAR), 0);
            }
            return false;
        }

        // Create variable profiles and variables
        $result_VarProfileCreate = $this->VariablesProfiles_Create($DeviceAR, true);
        if ($result_VarProfileCreate === false) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 203);
            return false;
        }

        if (@array_key_exists('Presets', $DeviceAR) === true) {
            $this->VariablesProfiles_Update('Presets', $DeviceAR['Presets']);
        }

        $result_VarCreate = $this->Variables_Create($DeviceAR);
        if ($result_VarCreate === false) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 204);
            return false;
        }

        // Create media object (device image)
        if ($this->ReadPropertyBoolean('ShowDeviceImage') === true) {
            if (@array_key_exists('ImageID', $DeviceAR) === true) {
                if ($DeviceAR['ImageID'] > 0) {
                    $Name = $this->Translate('Device-Image');
                    $Cached = true;
                    $Filename = 'MELCloud_Device_' . $buildingid . '_' . $deviceid . '.jpg';
                    $Ident = 'Device_Image';
                    $result_Image_Create = $this->RegisterObjectMedia($Name, $Ident, 1, $this->InstanceID, $Cached, $Filename, 0);
                    if ($result_Image_Create === false) {
                        $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The device image could not be read. Either there is no image or there was a problem.'), 0);
                        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 206);
                        return false;
                    }
                } else {
                    $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The device image could not be read. Either there is no image or there was a problem.'), 0);
                }
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The device image could not be read. Either there is no image or there was a problem.'), 0);
            }
        } else {
            $MediaID = @$this->GetIDForIdent('Device_Image');
            if (@IPS_MediaExists($MediaID) === true) {
                @IPS_DeleteMedia($MediaID, true);
            }
        }

        return true;
    }


    /**
     * Variables_Create (create the required variables and set them to the default values)
     *
     * @param $DeviceAR
     * @return bool
     */
    private function Variables_Create($DeviceAR)
    {
        $DeviceName = $this->ReadPropertyString('DeviceName');

        // get device data
        if ((@array_key_exists('DeviceID', $DeviceAR) === false) || (@array_key_exists('DeviceID', $DeviceAR) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Device variables could not be created. Device could not be found in MELCloud.') . ' // ' . $this->Translate('Device-Name') . ' = ' . $DeviceName, 0);
            return false;
        }

        // check if device type is known
        if ((@array_key_exists('Type', $DeviceAR) === false) || (@array_key_exists('Type', $DeviceAR) === NULL)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Device variables could not be created (Type of device is unknown)') . ' // ' . $this->Translate('Device-Name') . ' = ' . $DeviceName, 0);
            return false;
        }

        // create variables - depending on device type
        if ($DeviceAR['Type'] === 0) {
            $this->Variable_Register('DeviceCloudState', $this->Translate('Device-Cloud-Sate'), 'MEL.DeviceCloudState', '', 0, false);
            $this->Variable_Register('TemperatureSET', $this->Translate('Temperature-SET'), 'MEL.RoomTemperatureSET.' . $this->InstanceID, '', 2, true);
            $this->Variable_Register('OperationMode', $this->Translate('Operation-Mode'), 'MEL.OperationMode.' . $this->InstanceID, '', 1, true);
            $this->Variable_Register('HasError', $this->Translate('Error'), 'MEL.HasError', '', 0, false);
            $this->Variable_Register('ErrorCode', $this->Translate('Error-Code'), 'MEL.ErrorCode.' . $this->InstanceID, '', 1, false);
            $this->Variable_Register('LastCommunicationTimestamp', $this->Translate('Last device communication'), '~UnixTimestamp', 'Calendar', 1, false);

            // room temperature
            if ($DeviceAR['HideRoomTemperature'] === false) {
                $this->Variable_Register('TemperatureRoom', $this->Translate('Temperature-Room'), 'MEL.RoomTemperature', '', 2, false);
            }

            // presets
            if (@array_key_exists('NumberDescription', $DeviceAR['Presets'][0]) === true) {
                $this->Variable_Register('Preset', $this->Translate('Preset'), 'MEL.Presets.' . $this->InstanceID, '', 1, true);
                $this->SetValue_IfDifferent('Preset', 99);
            }

            // power
            $this->Variable_Register('PowerState', $this->Translate('Power-State'), 'MEL.Power', '', 0, true);

            // fan speed
            if ($this->Check_Feature($DeviceAR, 'ModelSupportsFanSpeed') === true) {
                $this->Variable_Register('FanSpeed', $this->Translate('Fan-Speed'), 'MEL.FanSpeed.' . $this->InstanceID, '', 1, true);
            }

            // Vane vertical
            if ($this->Check_Feature($DeviceAR, 'ModelSupportsVaneVertical') === true) {
                $this->Variable_Register('VaneVertical', $this->Translate('Vane-Vertical'), 'MEL.VaneVertical.' . $this->InstanceID, '', 1, true);
            }

            // Vane horizontal
            if ($this->Check_Feature($DeviceAR, 'ModelSupportsVaneHorizontal') === true) {
                $this->Variable_Register('VaneHorizontal', $this->Translate('Vane-Horizontal'), 'MEL.VaneHorizontal.' . $this->InstanceID, '', 1, true);
            }

            // create extra variables - depending on the selection in the module instance
            if ($this->ReadPropertyBoolean('VariablesExtra_System') === true) {
                // device system informations
                if (@array_key_exists('MacAddress', $DeviceAR) === true) {
                    $this->Variable_Register('MacAddress', $this->Translate('MAC-Address'), '', 'Information', 3, false);
                }
                if (@array_key_exists('SerialNumber', $DeviceAR) === true) {
                    $this->Variable_Register('SerialNumber', $this->Translate('Serial-Number'), '', 'Information', 3, false);
                }

                // firmware versions
                if (@array_key_exists('FirmwareAppVersion', $DeviceAR['Device']) === true) {
                    $this->Variable_Register('FirmwareAppVersion', 'Firmware-App-Version', '', 'Information', 3, false);
                }
                if (@array_key_exists('FirmwareWebVersion', $DeviceAR['Device']) === true) {
                    $this->Variable_Register('FirmwareWebVersion', 'Firmware-Web-Version', '', 'Information', 3, false);
                }
                if (@array_key_exists('FirmwareWlanVersion', $DeviceAR['Device']) === true) {
                    $this->Variable_Register('FirmwareWlanVersion', $this->Translate('Firmware-Wifi-Version'), '', 'Information', 3, false);
                }

                // wifi adapter
                if (@array_key_exists('WifiAdapterStatus', $DeviceAR['Device']) === true) {
                    $this->Variable_Register('WifiAdapterStatus', $this->Translate('Wifi-Adapter-Status'), '', 'Information', 3, false);
                }
                if (@array_key_exists('WifiSignalStrength', $DeviceAR['Device']) === true) {
                    $this->Variable_Register('WifiSignalStrength', $this->Translate('Wifi-Signal-Strength'), 'MEL.WifiSignalStrength', '', 1, false);
                }

                // installation und service dates
                if ($DeviceAR['InstallationDate'] !== '') {
                    $this->Variable_Register('InstallationDate', $this->Translate('Installation date'), '', 'Calendar', 3, false);
                }
                if ($DeviceAR['LastServiceDate'] !== '') {
                    $this->Variable_Register('LastServiceDate', $this->Translate('Last service'), '', 'Calendar', 3, false);
                }
                if (@array_key_exists('LastReset', $DeviceAR['Device']) === true) {
                    $this->Variable_Register('LastReset', $this->Translate('Last reset'), '~UnixTimestamp', 'Calendar', 1, false);
                }

                // diagnostic mode
                $this->Variable_Register('DiagnosticMode', $this->Translate('Diagnostic mode'), 'MEL.DiagnosticMode', '', 0, false);
            } else {
                $this->Variable_Unregister('MacAddress');
                $this->Variable_Unregister('SerialNumber');
                $this->Variable_Unregister('FirmwareAppVersion');
                $this->Variable_Unregister('FirmwareWebVersion');
                $this->Variable_Unregister('FirmwareWlanVersion');
                $this->Variable_Unregister('WifiAdapterStatus');
                $this->Variable_Unregister('WifiSignalStrength');
                $this->Variable_Unregister('InstallationDate');
                $this->Variable_Unregister('LastServiceDate');
                $this->Variable_Unregister('LastReset');
                $this->Variable_Unregister('DiagnosticMode');
            }

            $this->FillDeviceVariables($DeviceAR);

        } else {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Device variables could not be created (Type of device is not supported - please contact the module creator!)') . ' // ' . $this->Translate('Device-Name') . ' = ' . $DeviceName, 0);
            return false;
        }

        return true;
    }


    /**
     * VariablesProfiles_Create (create the variables profiles required for the module)
     *
     * @param $DeviceAR
     * @param $overwrite
     * @return bool
     */
    private function VariablesProfiles_Create($DeviceAR, $overwrite = false)
    {
        if ((@array_key_exists('Type', $DeviceAR) === false) || (@array_key_exists('Type', $DeviceAR) === NULL)) {
            if (@array_key_exists('DeviceName', $DeviceAR) === true) {
                if ($DeviceAR['DeviceName'] !== '') {
                    $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The following device could not be created (Type of device is unknown)') . ' - ' . $DeviceAR['DeviceName'] . ' // ' . $this->Translate('Device-Type') . ' = ' . $DeviceAR['DeviceType'] . ' // DeviceAR' . json_encode($DeviceAR), 0);
                }
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The following device could not be created (Type of device is unknown). The name of the device is unknown.') . ' // ' . $this->Translate('Device-ID') . ' = ' . $DeviceAR['DeviceID'] . ' // DeviceAR' . json_encode($DeviceAR), 0);
            }
            return false;
        }

        // create variable profiles for this device type
        if ($DeviceAR['Type'] === 0) {

            // remove "old" variable profiles
            if ($overwrite === true) {
                $VarProfileAR = array('MEL.DeviceCloudState', 'MEL.Power', 'MEL.RoomTemperature', 'MEL.RoomTemperatureSET.' . $this->InstanceID, 'MEL.OperationMode.' . $this->InstanceID, 'MEL.HasError', 'MEL.Presets.' . $this->InstanceID, 'MEL.FanSpeed.' . $this->InstanceID, 'MEL.VaneVertical.' . $this->InstanceID, 'MEL.VaneHorizontal.' . $this->InstanceID, 'MEL.WifiSignalStrength');
                foreach ($VarProfileAR as $VarProfileName) {
                    @IPS_DeleteVariableProfile($VarProfileName);
                }
            }

            $this->RegisterProfileBooleanEx('MEL.Power', 'Power', '', '', Array(
                Array(0, $this->Translate('Off'), '', -1),
                Array(1, $this->Translate('On'), '', 0x00FF00)
            ));

            $this->RegisterProfileBooleanEx('MEL.DeviceCloudState', 'Network', '', '', Array(
                Array(false, $this->Translate('Offline'), '', 0xFF0000),
                Array(true, $this->Translate('Online'), '', 0x00FF00)
            ));

            $minTemp = (int)$this->GetBuffer('MinTemp');
            $maxTemp = (int)$this->GetBuffer('MaxTemp');
            if (($minTemp > 0) && ($maxTemp > 0)) {
                $SetTemperature_min = $minTemp;
                $SetTemperature_max = $maxTemp;
            } else {
                $SetTemperature_min = $DeviceAR['MinTemperature'];
                $SetTemperature_max = $DeviceAR['MaxTemperature'];
            }

            $SetTemperature_step = $DeviceAR['Device']['TemperatureIncrement'];
            if ($SetTemperature_step > 0) {
                $this->RegisterProfileFloat('MEL.RoomTemperatureSET.' . $this->InstanceID, 'Temperature', '', ' °C', $SetTemperature_min, $SetTemperature_max, $SetTemperature_step);
            } else {
                $this->RegisterProfileFloat('MEL.RoomTemperatureSET.' . $this->InstanceID, 'Temperature', '', ' °C', $SetTemperature_min, $SetTemperature_max, 0.5);
            }

            $this->RegisterProfileBooleanEx('MEL.HasError', '', '', '', Array(
                Array(false, 'OK', 'Ok', 0x00FF00),
                Array(true, $this->Translate('ERROR'), 'Cross', 0xFF0000)
            ));

            $this->RegisterProfileIntegerEx('MEL.ErrorCode.' . $this->InstanceID, 'Cross', '', '', Array(
                Array(8000, 'OK', 'Ok', 0x00FF00)
            ));

            // fan speed
            if ($this->Check_Feature($DeviceAR, 'ModelSupportsFanSpeed') === true) {
                if (@array_key_exists('NumberOfFanSpeeds', $DeviceAR['Device']) === true) {
                    $vp_fanspeed_AR = array();
                    $vp_fanspeed_AR[] = array(0, 'Auto', '', -1);
                    for ($dfsc = 1; $dfsc <= $DeviceAR['Device']['NumberOfFanSpeeds']; $dfsc++) {
                        $vp_fanspeed_AR[] = array($dfsc, $dfsc, '', -1);
                    }
                    $this->RegisterProfileIntegerEx('MEL.FanSpeed.' . $this->InstanceID, 'Ventilation', '', '', $vp_fanspeed_AR);
                } else {
                    $this->RegisterProfileIntegerEx('MEL.FanSpeed.' . $this->InstanceID, 'Ventilation', '', '', Array(
                        Array(0, 'Auto', '', -1),
                        Array(1, '1', '', -1),
                        Array(2, '2', '', -1),
                        Array(3, '3', '', -1),
                        Array(4, '4', '', -1),
                        Array(5, '5', '', -1)
                    ));
                }
            }

            $vp_operationmode_AR = array();
            // cooling mode
            if ($this->Check_Feature($DeviceAR, 'CanCool') === true) {
                $vp_operationmode_AR[] = array(3, $this->Translate('Cooling'), 'Snowflake', -1);
            }
            // dry
            if ($this->Check_Feature($DeviceAR, 'CanDry') === true) {
                $vp_operationmode_AR[] = array(7, $this->Translate('Fan'), 'Ventilation', -1);
            }
            // auto mode
            if ($this->Check_Feature($DeviceAR, 'ModelSupportsAuto') === true) {
                $vp_operationmode_AR[] = array(8, $this->Translate('Auto'), 'Climate', -1);
            }
            // heat
            if ($this->Check_Feature($DeviceAR, 'CanHeat') === true) {
                $vp_operationmode_AR[] = array(1, $this->Translate('Heating'), 'Radiator', -1);
            }
            // nothing
            if (count($vp_operationmode_AR) === 0) {
                $vp_operationmode_AR[] = array(0, $this->Translate('ERROR // No operation modes available'), '', 0xFF0000);
            }
            $this->RegisterProfileIntegerEx('MEL.OperationMode.' . $this->InstanceID, 'Climate', '', '', $vp_operationmode_AR);

            if ($DeviceAR['HideVaneControls'] === false) {
                // Vane vertical (direction and swing)
                if ($this->Check_Feature($DeviceAR, 'ModelSupportsVaneVertical') === true) {
                    $vp_vanevertical_AR = array();
                    $vp_vanevertical_AR[] = array(0, 'Auto', '', -1);
                    if ($this->Check_Feature($DeviceAR, 'AirDirectionFunction') === true) {
                        $vp_vanevertical_AR[] = array(1, '1', '', -1);
                        $vp_vanevertical_AR[] = array(2, '2', '', -1);
                        $vp_vanevertical_AR[] = array(3, '3', '', -1);
                        $vp_vanevertical_AR[] = array(4, '4', '', -1);
                        $vp_vanevertical_AR[] = array(5, '5', '', -1);
                    }
                    if ($this->Check_Feature($DeviceAR, 'SwingFunction') === true) {
                        $vp_vanevertical_AR[] = array(7, $this->Translate('Swing'), '', -1);
                    }
                    $this->RegisterProfileIntegerEx('MEL.VaneVertical.' . $this->InstanceID, 'HollowDoubleArrowDown', '', '', $vp_vanevertical_AR);
                }

                // Vane horizontal (direction and swing)
                if ($this->Check_Feature($DeviceAR, 'ModelSupportsVaneHorizontal') === true) {
                    $vp_vanehorizontal_AR = array();
                    $vp_vanehorizontal_AR[] = array(0, 'Auto', '', -1);
                    if ($this->Check_Feature($DeviceAR, 'AirDirectionFunction') === true) {
                        $vp_vanehorizontal_AR[] = array(1, '1', '', -1);
                        $vp_vanehorizontal_AR[] = array(2, '2', '', -1);
                        $vp_vanehorizontal_AR[] = array(3, '3', '', -1);
                        $vp_vanehorizontal_AR[] = array(4, '4', '', -1);
                        $vp_vanehorizontal_AR[] = array(5, '5', '', -1);
                    }
                    if ($this->Check_Feature($DeviceAR, 'SwingFunction') === true) {
                        $vp_vanehorizontal_AR[] = array(12, $this->Translate('Swing'), '', -1);
                    }
                    $this->RegisterProfileIntegerEx('MEL.VaneHorizontal.' . $this->InstanceID, 'HollowDoubleArrowRight', '', '', $vp_vanehorizontal_AR);
                }
            }

            if ($DeviceAR['HideRoomTemperature'] === false) {
                $this->RegisterProfileFloat('MEL.RoomTemperature', 'Temperature', '', ' °C', 0, 50, 0.5);
            }

            if ($this->ReadPropertyBoolean('VariablesExtra_System') === true) {
                $this->RegisterProfileBooleanEx('MEL.DiagnosticMode', 'Gear', '', '', Array(
                    Array(0, $this->Translate('Disabled'), '', -1),
                    Array(1, $this->Translate('Enabled'), '', 0x00FF00)
                ));

                $this->RegisterProfileInteger('MEL.WifiSignalStrength', 'Intensity', '', ' dBm', '-100', '-30', 1);
            }

        } else {
            if (@array_key_exists('DeviceName', $DeviceAR) === true) {
                if ($DeviceAR['DeviceName'] !== '') {
                    $this->SendDebug(__FUNCTION__, $this->Translate('The following device could not be created (Type of device is not supported - please contact the module creator!)') . ' - ' . $DeviceAR['DeviceName'] . ' // ' . $DeviceAR['Type'], 0);
                }
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('The following device could not be created (Type of device is not supported - please contact the module creator!). The name of the device is unknown.') . ' // ' . $DeviceAR['Type'], 0);
            }
            return false;
        }

        $this->SendDebug(__FUNCTION__, $this->Translate('Variable profiles were created'), 0);

        return true;
    }


    /**
     * VariablesProfiles_Update (update variable profiles)
     *
     * @param $Selection
     * @param $DataAR
     * @return bool
     */
    private function VariablesProfiles_Update($Selection, $DataAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($Selection === 'Presets') {
            $create = false;
            $VarProfileDiffAR = array();
            $VarProfileEntrysAR = array();
            $VarProfileName = 'MEL.Presets.' . $this->InstanceID;

            if ($DebugActive === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Variable profile') . " '" . $VarProfileName . "' " . $this->Translate('is created/updated with the following data') . ': ' . json_encode($DataAR), 0);
            }

            $this->SetBufferX('MultiBuffer_PresetsAR', $DataAR);

            if (@array_key_exists('NumberDescription', $DataAR[0]) === true) {
                foreach ($DataAR as $PresetIndex => $PresetsEntry) {
                    if (@array_key_exists('NumberDescription', $PresetsEntry) === true) {
                        $VarProfileEntrysAR[] = array($PresetIndex, $PresetsEntry['NumberDescription'], '', -1);
                        $VarProfileDiffAR[] = array('Value' => $PresetIndex, 'Name' => $PresetsEntry['NumberDescription'], 'Icon' => '', 'Color' => -1);
                    }
                }

                if (count($VarProfileEntrysAR) > 0) {
                    $VarProfileEntrysAR[] = array(99, '   ', '', -1);
                    $VarProfileDiffAR[] = array('Value' => 99, '   ', 'Icon' => '', 'Color' => -1);
                }

                if (IPS_VariableProfileExists($VarProfileName) === true) {
                    $VarProfileX = IPS_GetVariableProfile($VarProfileName);
                    if ($VarProfileX['Associations'] != $VarProfileDiffAR) {
                        $create = true;
                    }
                } else {
                    $create = true;
                }
            } else {
                $VarProfileEntrysAR[] = array(0, $this->Translate('No presets available'), '', -1);
                $create = true;
            }

            if ($create === true) {
                @IPS_DeleteVariableProfile($VarProfileName);
                $this->RegisterProfileIntegerEx($VarProfileName, 'Execute', '', '', $VarProfileEntrysAR);
            }
        }

        return true;
    }
}

?>