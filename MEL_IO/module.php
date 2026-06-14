<?php /** @noinspection CallableParameterUseCaseInTypeContextInspection */
/** @noinspection DegradedSwitchInspection */
/** @noinspection SlowArrayOperationsInLoopInspection */
/** @noinspection SenselessProxyMethodInspection */
/** @noinspection PhpRedundantClosingTagInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection NestedPositiveIfStatementsInspection */
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection CurlSslServerSpoofingInspection */
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection SpellCheckingInspection */

require_once __DIR__ . '/../libs/helper_buffer.php';
require_once __DIR__ . '/../libs/helper_constants.php';
require_once __DIR__ . '/../libs/helper_debug.php';


class MELCloudIO extends IPSModule
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

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString('AccountEmail', '');
        $this->RegisterPropertyString('AccountPassword', '');
        $this->RegisterPropertyString('AppVersion', '1.17.3.1');
        $this->RegisterPropertyString('cURL_Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/16C50');
        $this->RegisterPropertyInteger('TimerIntervalUpdateDeviceData', 31);
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('debug', false);

        // Register attributes
        if (IPS_GetKernelVersion() >= 5.1) {
            $this->RegisterAttributeString('TOKEN', '');
        } else {
            $this->SetBufferX('TOKEN', '');
        }

        // Register timer
        $this->RegisterTimer('Update_GetList', 0, 'MELIO_Timer_Control($_IPS[\'TARGET\'], "Update_GetList", 2);');
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

        // remove variable profiles and configurator instance - if no device instance is left
        $DeviceInstancesAR = IPS_GetInstanceListByModuleID('{E69F33DB-96B6-4C88-B446-14BCEDA86511}');
        if ((@array_key_exists('0', $DeviceInstancesAR) === false) || (@array_key_exists('0', $DeviceInstancesAR) === NULL)) {
            $VarProfileAR = array('MEL.DeviceCloudState', 'MEL.Power', 'MEL.RoomTemperature', 'MEL.HasError', 'MEL.WifiSignalStrength');
            foreach ($VarProfileAR as $VarProfileName) {
                @IPS_DeleteVariableProfile($VarProfileName);
            }

            $ConfiguratorInstanceAR = IPS_GetInstanceListByModuleID('{E68EFE77-E878-468C-8AD6-28952A2C4715}');
            if (@array_key_exists('0', $ConfiguratorInstanceAR) === true) {
                if (@IPS_InstanceExists($ConfiguratorInstanceAR[0]) === true) {
                    @IPS_DeleteInstance($ConfiguratorInstanceAR[0]);
                }
            }
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

        // E-Mail or password empty?
        if (($this->ReadPropertyString('AccountEmail') === '') || ($this->ReadPropertyString('AccountPassword') === '')) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 202);
            $this->Timer_Control('Update_GetList', 0);
            return false;
        }

        // Maintenance mode active?
        if ($this->ReadPropertyBoolean('MaintenanceMode') === true) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 205);
            return false;
        }

        // Data query successful?
        if ($this->Devices_GetList() === false) {
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 201);
            $this->Timer_Control('Update_GetList', 0);
            return false;
        }

        // Everything ok!
        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);

        // Timer control
        $this->Timer_Control('Update_GetList', 1);

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

        $FormData_Main1 = '{
    "elements":
    [
        { "type": "ValidationTextBox", "name": "AccountEmail", "caption": "E-Mail" },
        { "type": "PasswordTextBox", "name": "AccountPassword", "caption": "Password" },
        { "type": "Label", "label": "----------------------------------------------------------------------------------------------------------------------------" },
        { "type": "Label", "label": "Interval for updating device data (0 = timer inactive):" },
        { "type": "IntervalBox", "name": "TimerIntervalUpdateDeviceData", "caption": "Minutes" },
        { "type": "Label", "label": "-----------------------------------------------------------------------------------------------------------------------------------------------" },
        { "type": "CheckBox", "name": "MaintenanceMode", "caption": "Block connection to MELCloud completely (maintenance mode)" },
        { "type": "CheckBox", "name": "debug", "caption": "Debug" }
    ],
    "actions":
    [
        { "type": "Button", "label": "Update all device data", "onClick": "MELIO_Devices_GetList($id);" }
    ],
    "status":
    [
        { "code": 101, "icon": "active", "caption": "Creating instance" },
        { "code": 102, "icon": "active", "caption": "OK" },
        { "code": 201, "icon": "error", "caption": "ERROR // Login at MELCloud not possible" },
        { "code": 202, "icon": "error", "caption": "ERROR // Account e-mail and password must not be empty" },
        { "code": 203, "icon": "error", "caption": "ERROR // Connection to MELCloud failed - check debug output" },
        { "code": 204, "icon": "inactive", "caption": "ERROR // Login has been blocked by MELCloud - Period of lock is in IPS log" },
        { "code": 205, "icon": "inactive", "caption": "Maintenance mode is active" }
    ]
}';

        $FormData = $FormData_Main1;

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, 'FormData = ' . $FormData, 0);
            $this->SendDebug(__FUNCTION__, 'FormData (json last error msg) = ' . json_last_error_msg(), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, 'FormData = ' . $FormData);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, 'FormData (json last error msg) = ' . json_last_error_msg());
        }
        return $FormData;
    }



    /********** PUBLIC FUNCTIONS **********/

    /**
     * Device_GetDataRAW (get complete list with all data from every building, floor, area, device, ...)
     *
     * @param string $buildingid
     * @param string $deviceid
     * @return false|array
     */
    public function Device_GetDataRAW(string $buildingid, string $deviceid)
    {
        $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $deviceid . '&buildingID=' . $buildingid;

        return $this->Data_Get($url);
    }


    /**
     * Device_GetImage (get the image of the device from the cloud/app)
     *
     * @param string $buildingid
     * @param string $deviceid
     * @return false|array
     */
    public function Device_GetImage(string $buildingid, string $deviceid)
    {
        $DevicesAR = $this->Devices_GetList();

        if ($DevicesAR !== false) {
            $DeviceAR = array();
            foreach ($DevicesAR as $DeviceEntryAR) {
                if (($DeviceEntryAR['BuildingID'] == $buildingid) && ($DeviceEntryAR['DeviceID'] == $deviceid)) {
                    $DeviceAR = $DeviceEntryAR;
                    break;
                }
            }

            if (@array_key_exists('ImageID', $DeviceAR) === true) {
                if ($DeviceAR['ImageID'] > 0) {
                    $POSTfieldsAR['deviceId'] = $deviceid;
                    $POSTfieldsAR['id'] = $DeviceAR['ImageID'];
                    $dataAR = $this->Data_Set($buildingid, $deviceid, 'GetDeviceImage', $POSTfieldsAR);

                    if (@array_key_exists('Image', $dataAR) === true) {
                        if (strlen($dataAR['Image']) > 50) {
                            preg_match('|(.*,)?(.*)|', $dataAR['Image'], $matchImage);

                            if (@array_key_exists('2', $matchImage) === true) {
                                $Image_base64_AR['header'] = substr($matchImage[1], 0, -1);
                                $Image_base64_AR['image'] = $matchImage[2];
                                return $Image_base64_AR;
                            }
                        }
                    }
                }
            }
        }

        $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // The device image could not be read. Either there is no image or there was a problem.') . ' // ' . $this->Translate('Received data') . ': ' . json_encode($DevicesAR), 0);
        return false;
    }


    /**
     * Devices_GetList (get list with all devices and all device-information)
     *
     * @return false|array
     */
    public function Devices_GetList()
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $dataARx = $this->Devices_GetListRAW();

        if ($dataARx === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        $deviceAR = array();
        $bfas_AR = array();
        $dcount = 0;

        foreach ($dataARx as $dataAR) {
            if ((@array_key_exists('ID', $dataAR) === true) && (@array_key_exists('Name', $dataAR) === true)) {
                if ($dataAR['Name'] !== '') {
                    $AreaID = $dataAR['ID'];
                    $bfas_AR[$AreaID] = $dataAR['Name'];
                }
            }

            if (@array_key_exists('0', $dataAR['Structure']['Areas']) === true) {
                foreach ($dataAR['Structure']['Areas'] as $StructureAreaEntry) {
                    if ((@array_key_exists('ID', $StructureAreaEntry) === true) && (@array_key_exists('Name', $StructureAreaEntry) === true)) {
                        if ($StructureAreaEntry['Name'] !== '') {
                            $AreaID = $StructureAreaEntry['ID'];
                            $bfas_AR[$AreaID] = $StructureAreaEntry['Name'];
                        }
                    }

                    if (@array_key_exists('0', $StructureAreaEntry['Devices']) === true) {
                        foreach ($StructureAreaEntry['Devices'] as $StructureAreaDeviceEntry) {
                            $deviceAR[$dcount] = $StructureAreaDeviceEntry;
                            if (@array_key_exists('ListHistory24Formatters', $deviceAR[$dcount]['Device']) === true) {
                                unset($deviceAR[$dcount]['Device']['ListHistory24Formatters']);
                            }
                            $dcount++;
                        }
                    }
                }
            }

            if (@array_key_exists('0', $dataAR['Structure']['Devices']) === true) {
                foreach ($dataAR['Structure']['Devices'] as $StructureDeviceEntry) {
                    $deviceAR[$dcount] = $StructureDeviceEntry;
                    if (@array_key_exists('ListHistory24Formatters', $deviceAR[$dcount]['Device']) === true) {
                        unset($deviceAR[$dcount]['Device']['ListHistory24Formatters']);
                    }
                    $dcount++;
                }
            }

            if (@array_key_exists('0', $dataAR['Structure']['Floors']) === true) {
                foreach ($dataAR['Structure']['Floors'] as $floorEntry) {
                    if ((@array_key_exists('ID', $floorEntry) === true) && (@array_key_exists('Name', $floorEntry) === true)) {
                        if ($floorEntry['Name'] !== '') {
                            $AreaID = $floorEntry['ID'];
                            $bfas_AR[$AreaID] = $floorEntry['Name'];
                        }
                    }

                    if (@array_key_exists('0', $floorEntry['Devices']) === true) {
                        foreach ($floorEntry['Devices'] as $floorDeviceEntry) {
                            $deviceAR[$dcount] = $floorDeviceEntry;
                            if (@array_key_exists('ListHistory24Formatters', $deviceAR[$dcount]['Device']) === true) {
                                unset($deviceAR[$dcount]['Device']['ListHistory24Formatters']);
                            }
                            $dcount++;
                        }
                    }

                    if (@array_key_exists('0', $floorEntry['Areas']) === true) {
                        foreach ($floorEntry['Areas'] as $floorAreaEntry) {
                            if ((@array_key_exists('ID', $floorAreaEntry) === true) && (@array_key_exists('Name', $floorAreaEntry) === true)) {
                                if ($floorAreaEntry['Name'] !== '') {
                                    $AreaID = $floorAreaEntry['ID'];
                                    $bfas_AR[$AreaID] = $floorAreaEntry['Name'];
                                }
                            }

                            if (@array_key_exists('0', $floorAreaEntry['Devices']) === true) {
                                foreach ($floorAreaEntry['Devices'] as $floorAreaDeviceEntry) {
                                    $deviceAR[$dcount] = $floorAreaDeviceEntry;
                                    if (@array_key_exists('ListHistory24Formatters', $deviceAR[$dcount]['Device']) === true) {
                                        unset($deviceAR[$dcount]['Device']['ListHistory24Formatters']);
                                    }
                                    $dcount++;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (@array_key_exists('0', $deviceAR) === true) {
            $newDeviceAR = array();

            foreach ($deviceAR as $deviceEntryAR) {
                if (@array_key_exists('DeviceID', $deviceEntryAR) === true) {
                    if ($deviceEntryAR['DeviceID'] !== '') {
                        $ID = $deviceEntryAR['DeviceID'];
                        if (@array_key_exists($ID, $bfas_AR) === true) {
                            $deviceEntryAR['DeviceName'] = $bfas_AR[$ID];
                        }
                    }
                }

                if (@array_key_exists('BuildingID', $deviceEntryAR) === true) {
                    if ($deviceEntryAR['BuildingID'] !== '') {
                        $ID = $deviceEntryAR['BuildingID'];
                        if (@array_key_exists($ID, $bfas_AR) === true) {
                            $deviceEntryAR['BuildingName'] = $bfas_AR[$ID];
                        }
                    }
                }

                if (@array_key_exists('FloorID', $deviceEntryAR) === true) {
                    if ($deviceEntryAR['FloorID'] !== '') {
                        $ID = $deviceEntryAR['FloorID'];
                        if (@array_key_exists($ID, $bfas_AR) === true) {
                            $deviceEntryAR['FloorName'] = $bfas_AR[$ID];
                        }
                    }
                }

                if (@array_key_exists('AreaID', $deviceEntryAR) === true) {
                    if ($deviceEntryAR['AreaID'] !== '') {
                        $ID = $deviceEntryAR['AreaID'];
                        if (@array_key_exists($ID, $bfas_AR) === true) {
                            $deviceEntryAR['AreaName'] = $bfas_AR[$ID];
                        }
                    }
                }

                $newDeviceAR[] = $deviceEntryAR;
            }


            $Flag_FirstRunDone = $this->GetBufferX('FirstRunDone');
            if ($Flag_FirstRunDone !== '1') {
                $this->SetBufferX('FirstRunDone', '1');
            }
            $force = $this->GetBufferX('Force_SendListToChilds');
            $Time_LastRun_GetList = $this->GetBufferX('LastRun_GetList');
            if ($Time_LastRun_GetList === NULL) {
                $this->SetBufferX('LastRun_GetList', time());
                $Run_TimeDiffToLast = 0;
                //$force = '1';  //////////////
            } else {
                $Run_TimeDiffToLast = time() - (int)$Time_LastRun_GetList;
            }
            if ((($Run_TimeDiffToLast > 51) && ($Flag_FirstRunDone === '1')) || ($force === '1')) {
                $this->SetBufferX('LastRun_GetList', time());
                $this->SetBuffer('Force_SendListToChilds', '0');
                $this->SendData_DeviceInfoToDeviceInstances($newDeviceAR);
            } else {
                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Data is not forwarded to all device instances because the last execution was less than 60 seconds ago'), 0);
                }
            }

            return $newDeviceAR;
        }

        $this->SendDebug(__FUNCTION__, $this->Translate('No devices found'), 0);
        return false;
    }


    /**
     * Devices_GetListRAW (get complete list with all devices and all information)
     *
     * @return false|array
     */
    public function Devices_GetListRAW()
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $Run_TimeDiffToLast = time() - (int)$this->GetBuffer('LastRun_GetListRAW');
        if ($Run_TimeDiffToLast < 58) {
            $dataAR = $this->GetBufferX('MultiBuffer_GetListRAW');
            if (@array_key_exists('Name', $dataAR[0]) === true) {
                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Request was served from the cache'), 0);
                }
                return $dataAR;
            }
        }

        $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/User/ListDevices/';

        $dataAR = $this->Data_Get($url);
        if ($dataAR === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED') . ' // ' . $this->Translate('Processing is terminated'), 0);
            return false;
        }

        $this->SetBufferX('LastRun_GetListRAW', time());
        $this->SetBufferX('MultiBuffer_GetListRAW', $dataAR);

        $this->AppVersion_Update($dataAR);
        return $dataAR;
    }



    /********** INTERNAL FUNCTIONS **********/

    /**
     * AppVersion_Update (determine the current MELCloud app version)
     *
     * @param $deviceListRAWdataAR
     * @return bool
     */
    private function AppVersion_Update($deviceListRAWdataAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $searchResult = $this->Array_Search_VAL($deviceListRAWdataAR, 'AssemblyName', 'Mitsubishi.Wifi.Data');
        if (@array_key_exists('0', $searchResult) === true) {
            preg_match('|.*Version=(.*?),.*|', $searchResult[0], $matchAppVersion);
            if (@array_key_exists('1', $matchAppVersion) === true) {
                $appVersion = trim($matchAppVersion[1]);
                if ($this->ReadPropertyString('AppVersion') !== $appVersion) {
                    $appVersionsAR = array();
                    $appVersionsAR[] = $appVersion;
                    $appVersionsAR[] = $this->ReadPropertyString('AppVersion');
                    sort($appVersionsAR);
                    $appVersionNEW = array_pop($appVersionsAR);

                    if ($appVersionNEW === $appVersion) {
                        IPS_SetProperty($this->InstanceID, 'AppVersion', $appVersion);
                        @IPS_ApplyChanges($this->InstanceID);

                        if ($DebugActive === true) {
                            $this->SendDebug(__FUNCTION__, $this->Translate('INFO // AppVersion has been updated. New Version = ') . $appVersion, 0);
                        }
                    }
                }

                return true;
            }
        }

        return false;
    }


    /**
     * Array_Search_VAL (search an array for a value or a part of it and return the complete value)
     *
     * @param $array
     * @param $key
     * @param $value
     * @return array
     */
    private function Array_Search_VAL($array, $key, $value)
    {
        $results = array();

        if (is_array($array)) {
            if ((@array_key_exists($key, $array) === true) && (strpos($array[$key], $value) === 0)) {
                $results[] = $array[$key];
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, $this->Array_Search_VAL($subarray, $key, $value));
            }
        }

        return $results;
    }


    /**
     * Is_Unauthorized (prüft, ob der cURL-Fehler bzw. HTTP-Code ein 401 ist)
     *
     * Bei abgelaufenem ContextKey antwortet MELCloud mit HTTP 401. Wegen
     * CURLOPT_FAILONERROR landet das als cURL-Fehler ("returned error: 401"),
     * deshalb wird sowohl der Fehlertext als auch der HTTP-Code geprüft.
     *
     * @param $cURL_Error
     * @param $http_code
     * @return bool
     */
    private function Is_Unauthorized($cURL_Error, $http_code)
    {
        if ((int) $http_code === 401) {
            return true;
        }
        if (is_string($cURL_Error) && strpos($cURL_Error, '401') !== false) {
            return true;
        }
        return false;
    }


    /**
     * Token_Reset (verwirft den gespeicherten ContextKey, erzwingt Neu-Login)
     *
     * @return void
     */
    private function Token_Reset()
    {
        if (IPS_GetKernelVersion() >= 5.1) {
            $this->WriteAttributeString('TOKEN', '');
        } else {
            $this->SetBufferX('TOKEN', '');
        }
    }


    /**
     * Data_Get (http-get with curl)
     *
     * @param $url
     * @param bool $retry
     * @return false|array
     */
    private function Data_Get($url, $retry = false)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($this->ReadPropertyBoolean('MaintenanceMode') === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Maintenance mode is activated in the I/O instance! The connection to the MELCloud is therefore interrupted!'), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('INFO // Maintenance mode is activated in the I/O instance! The connection to the MELCloud is therefore interrupted!'));
            return false;
        }

        if (IPS_GetKernelVersion() >= 5.1) {
            $token = $this->ReadAttributeString('TOKEN');
        } else {
            $token = $this->GetBufferX('TOKEN');
        }

        if ($token === '') {
            $token = $this->Login();
        }

        if ($token === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data'), 0);
            return false;
        }

        $agent = $this->ReadPropertyString('cURL_Agent');

        $headers = array();
        $headers[] = 'Host: app.melcloud.com';
        $headers[] = 'X-MitsContextKey: ' . $token;
        $headers[] = 'Accept: application/json, text/javascript, */*; q=0.01';
        $headers[] = 'Accept-Encoding: br, gzip, deflate';
        $headers[] = 'Connection: keep-alive';

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Data is retrieved'), 0);
        }

        if (IPS_SemaphoreEnter('MELCloud', 2 * 9000)) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_USERAGENT, $agent);
            curl_setopt($curl, CURLOPT_FAILONERROR, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 5000);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 20000);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($curl);

            IPS_SemaphoreLeave('MELCloud');
        } else {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // No connection to MELCloud possible! Connection to MELCloud is busy (Semaphore)'), 0);
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $cURL_Error = curl_error($curl);
        curl_close($curl);

        if ($cURL_Error !== '') {
            // HTTP 401 = ContextKey abgelaufen. Einmalig Token verwerfen, neu
            // einloggen und den Abruf wiederholen, statt nur den Fehler zu loggen.
            if ($this->Is_Unauthorized($cURL_Error, $http_code) === true && $retry === false) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ContextKey expired (HTTP 401) - re-login is performed'), 0);
                $this->Token_Reset();

                $token = $this->Login();
                if ($token === false) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED'), 0);
                    IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR // Re-login after HTTP 401 failed'));
                    return false;
                }

                return $this->Data_Get($url, true);
            }

            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Connection to the MELCloud failed // cURL Error = ' . $cURL_Error), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR // Connection to the MELCloud failed // cURL Error = ') . $cURL_Error);
            return false;
        }

        if ($retry === false) {
            if ($data === false) {
                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Login required'), 0);
                }
                $token = $this->Login($token);
                if ($token === false) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED'), 0);
                    return false;
                }

                $data = $this->Data_Get($url, true);
            }
        }

        if ($data === false) {
            $this->Debug_OutputGenerate($this->Translate('ERROR retrieving data'), $http_code);
            return false;
        }

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Data received successfully'), 0);
        }

        if (is_array($data) === true) {
            return $data;
        }

        return json_decode($data, true);
    }


    /**
     * Data_Set (send command to control a device or change a setting, http-post with curl)
     *
     * @param $buildingid
     * @param $deviceid
     * @param $action
     * @param $parameter
     * @param bool $retry
     * @return false|array
     */
    private function Data_Set($buildingid, $deviceid, $action, $parameter, $retry = false)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $maintenanceModeActive = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($maintenanceModeActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Maintenance mode is activated in the I/O instance! The connection to the MELCloud is therefore interrupted!'), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('INFO // Maintenance mode is activated in the I/O instance! The connection to the MELCloud is therefore interrupted!'));
            return false;
        }

        if (IPS_GetKernelVersion() >= 5.1) {
            $token = $this->ReadAttributeString('TOKEN');
        } else {
            $token = $this->GetBufferX('TOKEN');
        }

        if ($token === '') {
            $token = $this->Login();
        }

        if ($token === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data'), 0);
            return false;
        }

        if ($action !== 'Preset') {
            $POSTfieldsTMP = $this->Device_GetDataRAW($buildingid, $deviceid);
        }

        $url = '';

        if ($action === 'Power') {
            if ($parameter == true) {
                $parameter = true;
            } else {
                $parameter = false;
            }
            $POSTfieldsTMP[$action] = $parameter;
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 1;

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                }
            }
        } elseif ($action === 'OperationMode') {
            $POSTfieldsTMP[$action] = $parameter;
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 2;

            if (($parameter === 1) || ($parameter === 3)) {
                $POSTfieldsTMP['EffectiveFlags'] = 6;
            }

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                }
            }
        } elseif ($action === 'SetTemperature') {
            $POSTfieldsTMP[$action] = $parameter;
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 4;

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                }
            }
        } elseif ($action === 'SetFanSpeed') {
            $POSTfieldsTMP[$action] = $parameter;
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 8;

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                }
            }
        } elseif ($action === 'VaneVertical') {
            $POSTfieldsTMP[$action] = $parameter;
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 16;

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                }
            }
        } elseif ($action === 'VaneHorizontal') {
            $POSTfieldsTMP[$action] = $parameter;
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 256;

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                }
            }
        } elseif ($action === 'Preset') {
            $POSTfieldsTMP = json_decode($parameter, true);
            $POSTfieldsTMP['HasPendingCommand'] = true;
            $POSTfieldsTMP['EffectiveFlags'] = 287;

            if (@array_key_exists('DeviceType', $POSTfieldsTMP) === true) {
                if ($POSTfieldsTMP['DeviceType'] === 0) {
                    $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta';
                } else {
                    $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . ' // ' . $this->Translate('The device type') . ' "' . $POSTfieldsTMP['DeviceType'] . '" ' . $this->Translate('is currently not supported! Please contact the module creator!'), 0);
                    return false;
                }
            }
        } elseif ($action === 'GetDeviceImage') {
            $url = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/GetDeviceImage';
            $POSTfieldsTMP = $parameter;
        }

        if ($url === '') {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Unknown action - data cannot be sent') . ' // action = ' . $action . ' // parameter = ' . json_encode($parameter), 0);
            return false;
        }

        $POSTfields = json_encode($POSTfieldsTMP);

        $agent = $this->ReadPropertyString('cURL_Agent');

        $headers = array();
        $headers[] = 'Host: app.melcloud.com';
        $headers[] = 'X-MitsContextKey: ' . $token;
        $headers[] = 'Content-Type: application/json; charset=UTF-8';
        $headers[] = 'Origin: file://';
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'Accept: application/json, text/javascript, */*; q=0.01';
        $headers[] = 'Accept-Encoding: br, gzip, deflate';
        $headers[] = 'Content-Length: ' . strlen($POSTfields);

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Data will be sent') . ' // POSTfields = ' . $POSTfields, 0);
        }

        if ($url === '') {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Device type could not be determined. The command was not sent!'), 0);
            return false;
        }

        if (IPS_SemaphoreEnter('MELCloud', 2 * 9000)) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_USERAGENT, $agent);
            curl_setopt($curl, CURLOPT_FAILONERROR, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $POSTfields);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 5000);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 20000);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($curl);

            IPS_SemaphoreLeave('MELCloud');
        } else {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // No connection to MELCloud possible! Connection to MELCloud is busy (Semaphore)'), 0);
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $cURL_Error = curl_error($curl);
        curl_close($curl);

        if ($cURL_Error !== '') {
            // HTTP 401 = ContextKey abgelaufen. Einmalig Token verwerfen, neu
            // einloggen und den Sendebefehl mit gleichen Parametern wiederholen.
            if ($this->Is_Unauthorized($cURL_Error, $http_code) === true && $retry === false) {
                $this->SendDebug(__FUNCTION__, $this->Translate('ContextKey expired (HTTP 401) - re-login is performed'), 0);
                $this->Token_Reset();

                $token = $this->Login();
                if ($token === false) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Login FAILED'), 0);
                    IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR // Re-login after HTTP 401 failed'));
                    return false;
                }

                return $this->Data_Set($buildingid, $deviceid, $action, $parameter, true);
            }

            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Connection to the MELCloud failed // cURL Error = ' . $cURL_Error), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR // Connection to the MELCloud failed // cURL Error = ') . $cURL_Error);
            return false;
        }

        if ($data === false) {
            $this->Debug_OutputGenerate($this->Translate('ERROR when sending data'), $http_code);
            return false;
        }

        $dataAR = json_decode($data, true);

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Data was sent successfully'), 0);
        }

        return $dataAR;
    }


    /**
     * Debug_OutputGenerate (generate debug output with http-code)
     *
     * @param $pretext
     * @param $http_code
     * @return string
     */
    private
    function Debug_OutputGenerate($pretext, $http_code)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $ErrorMessage = $pretext;
        if ($http_code === 403) {
            $ErrorMessage .= ' // HTTP-Code = 403 FORBIDDEN';
        } else {
            $ErrorMessage .= ' // HTTP-Code = ' . $http_code;
        }

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $ErrorMessage, 0);
        }

        return $ErrorMessage;
    }


    /**
     * ForwardData (internal SDK function to receive data from a children instance)
     *
     * @param $json
     * @return false|array
     */
    public function ForwardData($json)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $dataAR = json_decode($json, true);
        unset($dataAR['DataID']);

        if ($DebugActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Received data') . ' = ' . $json, 0);
        }

        return $this->ForwardData_Processing($dataAR);
    }


    /**
     * ForwardData_Processing (further processing of the data received from a children instance)
     *
     * @param $dataAR
     * @return false|array
     */
    private function ForwardData_Processing($dataAR)
    {
        if (@array_key_exists('action', $dataAR) === true) {
            if ($dataAR['action'] === 'call_func') {
                if (@array_key_exists('func', $dataAR) === true) {
                    $function = $dataAR['func'];
                    if (($function === 'Device_GetDataRAW') || ($function === 'Device_GetImage')) {
                        if (@array_key_exists('func_param', $dataAR) === true) {
                            if ($dataAR['func_param'] !== '') {
                                if (strpos($dataAR['func_param'], ';;') !== false) {
                                    $paramAR = @explode(';;', $dataAR['func_param']);
                                    if (@array_key_exists('1', $paramAR) === true) {
                                        if (method_exists($this, $function)) {
                                            $resultAR = $this->$function($paramAR[0], $paramAR[1]);
                                            return json_encode($resultAR);
                                        }
                                    }
                                }
                            }
                        }
                        $error = $this->Translate('Parameter(s) missing // Functions needs -buildingid- and -deviceid-');
                    } elseif (($function === 'Devices_GetList') || ($function === 'Devices_GetListRAW')) {
                        if (method_exists($this, $function)) {
                            if (@array_key_exists('func_param', $dataAR) === true) {
                                if ($dataAR['func_param'] === 'force') {
                                    $this->SetBuffer('Force_SendListToChilds', '1');
                                }
                            }
                            $resultAR = $this->$function();
                            return json_encode($resultAR);
                        }
                    } elseif ($function === 'Data_Set') {
                        if (@array_key_exists('func_param', $dataAR) === true) {
                            if ($dataAR['func_param'] !== '') {
                                if (strpos($dataAR['func_param'], ';;') !== false) {
                                    $paramAR = @explode(';;', $dataAR['func_param']);
                                    if (@array_key_exists('3', $paramAR) === true) {
                                        if (method_exists($this, $function)) {
                                            $resultAR = $this->$function($paramAR[0], $paramAR[1], $paramAR[2], $paramAR[3]);
                                            return json_encode($resultAR);
                                        }
                                    }
                                }
                            }
                        }
                        $error = $this->Translate('Parameter(s) missing // Function needs -buildingid-, -deviceid-, -action- and -parameters-');
                    } else {
                        $error = $this->Translate('Calling an invalid function');
                    }
                } else {
                    $error = $this->Translate('Function to be called is missing');
                }
            } else {
                $error = $this->Translate('Unknown action');
            }
        } else {
            $error = $this->Translate('Unknown command');
        }

        $errormsg = $this->Translate('ERROR') . ' // ' . $error . ' // dataAR = ' . json_encode($dataAR);
        $this->SendDebug(__FUNCTION__, $errormsg, 0);
        return json_encode(array('error' => $errormsg));
    }


    /**
     * Login (login to MELCloud - with token or email/password)
     *
     * @param string $token
     * @return false|string
     */
    private function Login($token = '')
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $maintenanceModeActive = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($maintenanceModeActive === true) {
            $this->SendDebug(__FUNCTION__, $this->Translate('INFO // Maintenance mode is activated in the I/O instance! The connection to the MELCloud is therefore interrupted!'), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('INFO // Maintenance mode is activated in the I/O instance! The connection to the MELCloud is therefore interrupted!'));
            return false;
        }

        $email = $this->ReadPropertyString('AccountEmail');
        $password = $this->ReadPropertyString('AccountPassword');
        $appversion = $this->ReadPropertyString('AppVersion');
        $agent = $this->ReadPropertyString('cURL_Agent');

        $CONNECTTIMEOUT_MS = 5000;
        $TIMEOUT_MS = 20000;

        $curl = curl_init();

        if ($token === '') {
            if ($DebugActive === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Login is performed (with logon data)'), 0);
            }
            $loginurl = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientLogin';
            //$POSTfields = 'Email='.$email.'&Language=4&Password='.$password.'&AppVersion='.$appversion.'&Persist=true&CaptchaResponse=null';
            $POSTfieldsTMP = array();
            $POSTfieldsTMP['Email'] = $email;
            $POSTfieldsTMP['Password'] = $password;
            $POSTfieldsTMP['AppVersion'] = $appversion;
            $POSTfieldsTMP['Persist'] = true;
            $POSTfieldsTMP['CaptchaResponse'] = null;
            $POSTfields = json_encode($POSTfieldsTMP);

            $headers = array();
            $headers[] = 'Host: app.melcloud.com';
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
            $headers[] = 'Origin: file://';
            $headers[] = 'Connection: keep-alive';
            $headers[] = 'Accept: application/json, text/javascript, */*; q=0.01';
            $headers[] = 'Accept-Encoding: br, gzip, deflate';
            $headers[] = 'Content-Length: ' . strlen($POSTfields);

            curl_setopt($curl, CURLOPT_URL, $loginurl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_USERAGENT, $agent);
            curl_setopt($curl, CURLOPT_FAILONERROR, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $POSTfields);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $CONNECTTIMEOUT_MS);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, $TIMEOUT_MS);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        } else {
            if ($DebugActive === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Login is performed (with token)'), 0);
            }
            $loginurl = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientSavedLogin/?key=' . $token . '&appVersion=' . $appversion;

            $headers = array();
            $headers[] = 'Host: app.melcloud.com';
            $headers[] = 'Accept: application/json, text/javascript, */*; q=0.01';
            $headers[] = 'Connection: keep-alive';
            $headers[] = 'Accept-Encoding: br, gzip, deflate';

            curl_setopt($curl, CURLOPT_URL, $loginurl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_USERAGENT, $agent);
            curl_setopt($curl, CURLOPT_FAILONERROR, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $CONNECTTIMEOUT_MS);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, $TIMEOUT_MS);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        }

        if (IPS_SemaphoreEnter('MELCloud', 2 * 9000)) {
            $login = curl_exec($curl);
            IPS_SemaphoreLeave('MELCloud');
        } else {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // No connection to MELCloud possible! Connection to MELCloud is busy (Semaphore)'), 0);
            curl_close($curl);
            return false;
        }

        if (curl_error($curl) !== '') {
            $cURL_Error = curl_error($curl);
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 203);
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Connection to the MELCloud failed // cURL Error = ' . $cURL_Error), 0);
            IPS_LogMessage('MELCloud-' . __FUNCTION__, $this->Translate('ERROR // Connection to the MELCloud failed // cURL Error = ') . $cURL_Error);
            curl_close($curl);
            return false;
        }

        curl_close($curl);

        $data = json_decode($login, true);

        if ($token === '') {
            if (@array_key_exists('ContextKey', $data['LoginData']) === true) {
                $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);
                $token = $data['LoginData']['ContextKey'];

                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Login successful') . ' (1) // TOKEN = ' . $token, 0);
                }

                if (IPS_GetKernelVersion() >= 5.1) {
                    $this->WriteAttributeString('TOKEN', $token);
                } else {
                    $this->SetBufferX('TOKEN', $token);
                }

                return $token;
            }
            $error = 1;
        } else {
            if (@array_key_exists('ContextKey', $data['LoginData']) === true) {
                $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);
                $token = $data['LoginData']['ContextKey'];

                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Login successful') . ' (2) // TOKEN = ' . $token, 0);
                }

                if (IPS_GetKernelVersion() >= 5.1) {
                    $this->WriteAttributeString('TOKEN', $token);
                } else {
                    $this->SetBufferX('TOKEN', $token);
                }

                return $token;
            }

            $token = $this->Login();
            if ($token !== false) {
                $this->SetInstanceStatus_IfDifferent($this->InstanceID, 102);

                if (IPS_GetKernelVersion() >= 5.1) {
                    $this->WriteAttributeString('TOKEN', $token);
                } else {
                    $this->SetBufferX('TOKEN', $token);
                }

                return $token;
            }
            $error = 2;
        }

        if ($data['ErrorId'] === 1) {
            $data['ErrorMessage'] = $this->Translate('Invalid login data');
        } elseif ($data['ErrorId'] === 6) {
            $data['ErrorMessage'] = $this->Translate('Login blocked - too many failed login attempts');
        }

        $this->SendDebug(__FUNCTION__, $this->Translate('LOGIN ERROR') . ' (' . $error . ') // ' . $this->Translate('Error-ID') . ' = ' . $data['ErrorId'] . ' // ' . $this->Translate('Error-Message') . ' = ' . $data['ErrorMessage'], 0);
        $this->SendDebug(__FUNCTION__, $this->Translate('Login is blocked by MELCloud for ') . $data['LoginMinutes'] . $this->Translate(' minutes'), 0);

        $this->SetInstanceStatus_IfDifferent($this->InstanceID, 201);

        $LoginBlockedSeconds = $data['LoginMinutes'];
        if ($LoginBlockedSeconds >= $this->ReadPropertyInteger('TimerIntervalUpdateDeviceData')) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Timer is stopped - Login lock time is equal to or greater than the timer interval'), 0);
            $BlockedUntil = date('d.m.Y H:i', time() + $LoginBlockedSeconds + 60);
            IPS_LogMessage('MELCloud-Login', $this->Translate('Login to MELCloud is blocked until') . ' ' . $BlockedUntil);
            $this->Timer_Control('Update_GetList', 0);
            $this->SetInstanceStatus_IfDifferent($this->InstanceID, 204);
        }

        return false;
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

                    case KR_UNINIT:
                        $this->Timer_Control('Update_GetList', 0);
                        break;
                }
                break;

            case IPS_KERNELSTARTED:
                $this->ApplyChanges();
                break;
        }

        return true;
    }


    /**
     * SendData (Sending data to the children module instances)
     *
     * @param $dataAR
     * @return false|array
     */
    private function SendData($dataAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $dataAR['DataID'] = '{F559250D-D19E-4C5E-9CC3-73747C0A76E6}';

        if ($DebugActive === true) {
            if (@array_key_exists('action', $dataAR) === true) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Send command') . '" ' . $dataAR['action'] . '" ' . $this->Translate('with the following data') . ': ' . json_encode($dataAR), 0);
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('Send command with following data') . ' = ' . json_encode($dataAR), 0);
            }
        }

        $resultJson = @$this->SendDataToChildren(json_encode($dataAR));
        if ($resultJson === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data'), 0);
        }

        // Unter PHP 8 wirft json_decode() einen TypeError, wenn kein String
        // übergeben wird. SendDataToChildren liefert je nach IPS-Version/Antwort
        // auch bool oder array zurück - das hier abfangen, sonst Fatal Error.
        if (is_string($resultJson) === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('No valid response from child instance'), 0);
            return false;
        }

        $result = json_decode($resultJson, true);
        if (is_array($result) === false) {
            $this->SendDebug(__FUNCTION__, $this->Translate('No valid response from child instance'), 0);
            return false;
        }
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
     * SendData_DeviceInfoToDeviceInstances (Sending data from 'GetListRAW' to the children module instances)
     *
     * @param $DevicesAR
     * @return bool
     */
    private function SendData_DeviceInfoToDeviceInstances($DevicesAR)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        $resultX = true;

        // Hinweis: ini_set('max_execution_time', ...) ist in IPS aus
        // Sicherheitsgründen deaktiviert und würde nur eine Warnung erzeugen.
        // IPS verwaltet die Ausführungszeit selbst, daher hier kein ini_set mehr.

        foreach ($DevicesAR as $DeviceEntryAR) {
            $dataAR['action'] = 'GetList_ByIOfunction';
            $dataAR['data'] = $DeviceEntryAR;
            $dataAR['buildingid'] = (string)$DeviceEntryAR['BuildingID'];
            $dataAR['deviceid'] = (string)$DeviceEntryAR['DeviceID'];
            $result = $this->SendData($dataAR);
            if ($result === false) {
                $resultX = false;
                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('ERROR when sending data to child instance') . ' // ' . $this->Translate('Building-ID') . ' = ' . $dataAR['buildingid'] . ' // ' . $this->Translate('Device-ID') . ' = ' . $dataAR['deviceid'] . ' // ' . $this->Translate('Data') . ' = ' . json_encode($dataAR), 0);
                }
            } else {
                if ($DebugActive === true) {
                    $this->SendDebug(__FUNCTION__, $this->Translate('Data was sent successfully to child instance') . ' // ' . $this->Translate('Data') . ' = ' . json_encode($dataAR), 0);
                }
            }
        }

        return $resultX;
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


    /**
     * Timer_Control (stop timer, start timer in the set interval, handle timer call and call corresponding function)
     *
     * @param string $TimerName
     * @param int $option
     * @return bool
     */
    public function Timer_Control(string $TimerName, int $option)
    {
        $DebugActive = $this->ReadPropertyBoolean('debug');

        if ($option === 0) {
            $result = $this->SetTimerInterval($TimerName, 0);
        } elseif (($option === 1) && (IPS_GetKernelRunlevel() === KR_READY)) {
            $IntervalMilliSeconds = $this->ReadPropertyInteger('TimerIntervalUpdateDeviceData') * 60 * 1000;
            if ($IntervalMilliSeconds === 0) {
                $option = 0;
            }
            $result = $this->SetTimerInterval($TimerName, $IntervalMilliSeconds);
        } elseif ($option === 2) {
            if ($TimerName === 'Update_GetList') {
                $result = $this->Devices_GetList();
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR // Unknown timer') . ' // Name = ' . $TimerName, 0);
                $result = false;
            }
        } else {
            $result = $this->SetTimerInterval($TimerName, 0);
        }

        if ($DebugActive === true) {
            if ($option === 0) {
                $this->SendDebug(__FUNCTION__, "Timer '" . $TimerName . "' Option '" . $option . "' // " . $this->Translate('Timer has been stopped'), 0);
            } elseif ($option === 1) {
                $this->SendDebug(__FUNCTION__, "Timer '" . $TimerName . "' Option '" . $option . "' // " . $this->Translate('Timer has been started') . ' // ' . $this->Translate('Interval') . ': ' . ($IntervalMilliSeconds / 1000) . ' ' . $this->Translate('seconds'), 0);
            } elseif ($option === 2) {
                $this->SendDebug(__FUNCTION__, "Timer '" . $TimerName . "' Option '" . $option . "' // " . $this->Translate('Functions were called'), 0);
            } else {
                $this->SendDebug(__FUNCTION__, $this->Translate('ERROR') . " // Option '" . $option . "' " . $this->Translate('is invalid') . " // Timer '" . $TimerName . "' // " . $this->Translate('Timer has been stopped'), 0);
            }
        }

        return $result;
    }
}

?>