### IP-Symcon Module // MELCloud
---

## Documentation

**Table of contents**

1. [Features](#1-features) 
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Function reference](#4-functionreference)
5. [Changelog](#5-changelog) 


## 1. Features
This module can be used to read out and control Mitsubishi devices that are available via the "MELCloud" online service.
In addition to reading and visualising device information, all functions of the devices can also be controlled, including the operating mode (cooling, heating, ...) or also the activation of presets defined in MELCloud.<br>
_Currently, only air conditioning systems are supported, but the module can be extended at any time for heat pumps or other devices on request._

If presets (maximum cooling, night operation, ...) have been made in MELCloud, these are automatically read out and can be activated via WebFront/App, or a function in a separate script.

Depending on which functions are available on a device, the displayed variables and control options can vary.
When creating the device instance, all necessary information is read out for each device and the appropriate
variables and variable profiles are created automatically.

When using almost all functions in own scripts, the variables of one or even all device instances are directly updated in the background.

A timer can be set in the I/O instance, which takes care of updating all device data and information.


#### Device instance:
- Device Name, Device ID, Device Type, Building Name, Floor Name, Area Name
- Activation of additional variables with system information (diagnostic mode, firmware versions, MAC address, serial number, Wifi information, ...)
- Activation of the device image (only if you have uploaded your own photo in MELCloud)
- Block operation (Maintenance mode)

#### I/O instance:
- Email
- Password
- Interval for updating device data
- Block connection to MELCloud (maintenance mode)

#### Configurator instance:
- Category for creating the device instance(s)
- List of all devices available in the MELCloud
- Buttons for creating non-existent device instances (if not already created)

## 2. Requirements
- IP-Symcon Version 4.3 or newer


## 3. Installation
Using the core instance "Module Control", add the following URL:

`https://GITLAB-USERNAME:GITLAB-PASSWORD@gitlab.com/BY-IPS-Module/MELCloud.git`

After adding the URL - the instance "MELCloud Configurator" can be created. This instance automatically creates a
I/O instance in which the access data to the MELCloud must be configured. If the I/O instance has been successfully configured,
the configurator instance must be reopened so that the available devices can be read from the MELCloud and will be displayed.
Alternatively, of course, the I/O instance can first be created and configured - and then create the configurator instance and open it.

Any device in the Configurator that has not yet been created as a device instance can simply be created via the corresponding button in the
configurator instance (below the device list).
NOTE: After pressing the button to create a device instance, it may take a few seconds until a popup window is displayed, which confirms the successful creation and configuration of the device instance (please be patient here if necessary).

All devices found in the MELCloud and already created as device instance are highlighted in green in the list. Devices
which are created as device instances but are not found in the MELCloud are highlighted in red in the list.
NOTE: After "changes/actions" in the configurator instance, the instance must first be closed and reopened so that the
"changes/actions" will be visible in the list.


## 4. Function reference

#### Device instances:
```php
  MEL_Update(int $InstanceID);
```
Reads all data and updates the corresponding variables directly. TRUE or FALSE is returned.

```php
  MEL_Device_GetData(int $InstanceID);
```
Reads device data and updates the corresponding variables directly. FALSE or an array with the device data is returned.

```php
  MEL_Device_GetDataRAW(int $InstanceID);
```
Reads all device data and updates the corresponding variables directly. FALSE or an array with the device data is returned.

```php
  MEL_Device_GetListInfo(int $InstanceID);
```
Reads device information and directly updates the corresponding variables. FALSE or an array with information is returned.
Like the function "MEL_Devices_GetList", but here only the information for the respective device is returned.

```php
  MEL_Device_GetImage(int $InstanceID);
```
Reads the image from the device (only possible if an own image was uploaded in the MELCloud). Returns FALSE or an array with the header of the image and the image itself (base64 encoded).

```php
  MEL_Device_GetPresets(int $InstanceID);
```
Reads all presets defined in the MELCloud and updates the corresponding variable profile (also automatically
and does not have to be performed manually). The return value is FALSE or an array with the default settings.

```php
  MEL_Devices_GetList(int $InstanceID);
```
Reads information from devices available in the MELCloud and updates the corresponding variables directly with the returned data of all devices. FALSE or an array with information is returned.

```php
  MEL_Devices_GetListRAW(int $InstanceID);
```
Reads all information from devices available in the MELCloud. Returns FALSE or an array of information.

```php
  MEL_DeviceInstance_Configuration(int $InstanceID, string $BuildingID, string $DeviceID, string $DeviceType);
```
With this function you can configure a device instance. If you create a device instance independently without the configurator, then this instance can be configured with this function.
The function is used by the configurator instance to automatically be able to configure the device instance. FALSE or TRUE is returned.

```php
  MEL_FanSpeed_Set(int $InstanceID, int $FanSpeed);
```
Function to set the desired fan speed from level 1 to 5 (may vary depending on model). FALSE or TRUE is returned.

```php
  MEL_OperationMode_Set(int $InstanceID, int $OperationMode);
```
Function for setting the desired operating mode. FALSE or TRUE is returned.<br>
Operating modes for air conditioners:
- 1 = Heating mode
- 3 = Cooling mode
- 7 = Ventilation mode
- 8 = Automatic

```php
  MEL_PowerState_Set(int $InstanceID, bool $PowerState);
```
Function for switching off (false) and switching on (true) the device. FALSE or TRUE is returned.

```php
  MEL_Preset_Set(int $InstanceID, int $Preset);
```
Function to set a preset defined in the MELCloud. Returns FALSE or TRUE.<br>
The function "MEL_GetPresets" can be used to determine the number/ID of a certain preset.

```php
  MEL_Temperature_Set(int $InstanceID, float $Temperature);
```
Function for setting the desired SET temperature (min. and max. temperature vary depending on operating mode). FALSE or TRUE is returned.

```php
  MEL_VaneHorizontal_Set(int $InstanceID, int $value);
```
Function to adjust the horizontal vane. Returns FALSE or TRUE.
- 0 = Auto
- 1 to max. 5 = Vane position (depending on device type/model)
- 12 = Swing (must be supported by the device)

```php
  MEL_VaneVertical_Set(int $InstanceID, int $value);
```
Function to adjust the vertical vane. Returns FALSE or TRUE.
- 0 = Auto
- 1 to max. 5 = Vane position (depending on device type/model)
- 7 = Swing (must be supported by the device)

```php
  MEL_Weather_Get(int $InstanceID);
```
Reads the weather data available in the MELCloud (current data and forecast). Returns FALSE or an array of weather data.


#### I/O instance:
```php
  MELIO_Device_GetDataRAW(int $InstanceID, string $BuildingID, string $DeviceID);
```
Reads all device data and updates the corresponding variables directly. FALSE or an array with the device data is returned.

```php
  MELIO_Device_GetImage(int $InstanceID, string $BuildingID, string $DeviceID);
```
Reads the image from the device (only possible if an own image was uploaded in the MELCloud). Returns FALSE or an array with the header of the image and the image itself (base64 encoded). 

```php
  MELIO_Devices_GetList(int $InstanceID);
```
Reads information from devices available in the MELCloud and updates the corresponding variables directly with the returned data of all devices. FALSE or an array with information is returned.

```php
  MELIO_Devices_GetListRAW(int $InstanceID);
```
Reads all information from devices available in the MELCloud. Returns FALSE or an array of information.

```php
  MELIO_Timer_Control(int $InstanceID, 'Update_GetList', int $TimerOption);
```
Function to control the module timer.
- 0 = Stop timer
- 1 = Start timer (with the interval set in the instance)


#### Configurator instance:
```php
  MELC_Devices_GetList(int $InstanceID);
```
Reads information from devices available in the MELCloud and updates the corresponding variables directly with the returned data of all devices. FALSE or an array with information is returned.

```php
  MELC_Devices_GetListRAW(int $InstanceID);
```
Reads all information from devices available in the MELCloud. Returns FALSE or an array of information.

> GENERAL NOTE TO FUNCTIONS: If a feature or a value is not supported by a device, a corresponding output is in the debug.


## 5. Changelog
Version 1.0:
  - First release