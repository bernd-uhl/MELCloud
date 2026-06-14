### IP-Symcon Modul // MELCloud
---

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Befehlsreferenz](#4-befehlsreferenz)
5. [Changelog](#5-changelog) 



## 1. Funktionsumfang
Mit diesem Modul lassen sich Geräte von Mitsubishi, die über den Online-Dienst „MELCloud“ verfügbar sind, auslesen und steuern.
Neben dem Auslesen und Visualisieren von Geräte-Informationen können auch alle Funktionen der Geräte gesteuert werden, dazu zählen unter anderem die Betriebsart (Kühlen, Heizen, …) oder auch das Aktivieren von in der MELCloud festgelegten Voreinstellungen.<br>
_Aktuell werden nur Klimaanlagen unterstützt, das Modul kann aber auf Wunsch jederzeit für Wärmepumpen oder andere Geräte erweitert werden._

Wenn in der MELCloud Voreinstellungen (maximales Kühlen, Nachtbetrieb, ...) vorgenommen wurden, werden diese automatisch ausgelesen
und können über WebFront/App, oder auch eine Funktion in einem eigenen Skript, aktiviert werden.

Je nachdem welche Funktionen bei einem Gerät zur Verfügung stehen, können die angezeigten Variablen und Steuerungsmöglichkeiten variieren.
Für jedes Geräte werden bei der Erstellung der Geräte Instanz alle notwendigen Informationen ausgelesen und die für diese Gerät passenden
Variablen und Variablenprofile automatisch erstellt.

Bei der Verwendung fast aller Funktionen in eigenen Skripten werden im Hintergrund direkt die Variablen von einer oder sogar allen
Geräte Instanzen aktualisiert.

In der I/O Instanz ist ein Timer einstellbar, welcher sich um die Aktualisierung aller Gerätedaten und -informationen kümmert.


#### Geräte Instanz:
- Geräte-Name, Geräte-ID, Geräte-Typ, Gebäude-Name, Stockwerks-Name, Bereichs-Name
- Aktivierung zusätzlicher Variablen mit System-Informationen (Diagnosemodus, Firmware-Versionen, MAC-Adresse, Seriennummer, WLAN-Informationen, ...)
- Aktivierung des Geräte-Bildes (nur wenn man in der MELCloud ein eigenes Foto hochgeladen hat)
- Bedienung sperren (Wartungsmodus)

#### I/O Instanz:
- E-Mail
- Passwort
- Intervall für die Aktualisierung der Gerätedaten
- Verbindung zur MELCloud sperren (Wartungsmodus)

#### Konfigurator Instanz:
- Kategorie zur Erstellung der Geräte-Instanz(en)
- Liste aller in der MELCloud verfügbaren Geräte
- Knöpfe zum Erstellen von nicht vorhandenen Geräte-Instanzen (wenn noch nicht erstellt)



## 2. Systemanforderungen
- IP-Symcon ab Version 4.3



## 3. Installation
Über die Kern-Instanz "Module Control" folgende URL hinzufügen:

`https://GITLAB-BENUTZERNAME:GITLAB-PASSWORT@gitlab.com/BY-IPS-Module/MELCloud.git`

Nach dem Hinzufügen der URL kann die Instanz "MELCloud Konfigurator" erstellt werden. Diese Instanz erstellt automatisch eine
I/O Instanz, in der die Zugangsdaten zur MELCloud konfiguriert werden müssen. Ist die I/O Instanz erfolgreich konfiguriert und
verbunden, muss die Konfigurator Instanz neu geöffnet werden, damit die verfügbaren Geräte aus der MELCloud ausgelesen und angezeigt
werden. Alternativ kann natürlich auch erst die I/O Instanz erstellt und konfiguriert - und danach die Konfigurator Instanz
erstellt und geöffnet werden.

Jedes Gerät im Konfigurator, dass noch nicht als Geräte-Instanz angelegt wurde, kann einfach über den entsprechenden Knopf im
Konfigurator (unterhalb der Geräte-Liste) als Instanz erstellt werden.<br>
HINWEIS: Nach dem Betätigen des Knopfes, zum Erstellen einer Geräte Instanz, können einige Sekunden vergehen, bis ein Popup-Fenster
die erfolgreiche Erstellung und Konfiguration der Geräte Instanz bestätigt (hier bitte ggf. etwas Geduld haben).

Alle Geräte die in der MELCloud gefunden werden und bereits als Geräte Instanz erstellt wurden, sind in der Liste grün hinterlegt. Geräte
die als Geräte Instanz angelegt sind, aber in der MELCloud nicht gefunden werden, sind in der Liste rot hinterlegt.<br>
HINWEIS: Nach "Änderungen/Aktionen" in der Konfigurator Instanz, muss diese erst geschlossen und neu geöffnet werden, damit die getätigten
"Änderungen/Aktionen" in der Liste sichtbar werden.



## 4. Befehlsreferenz

#### Geräte Instanzen:
```php
  MEL_Update(int $InstanzID);
```
Liest alle Daten aus und aktualisiert direkt die entsprechenden Variablen. Zurückgegeben wird TRUE oder FALSE.

```php
  MEL_Device_GetData(int $InstanzID);
```
Liest Gerätedaten aus und aktualisiert direkt die entsprechenden Variablen. Zurückgegeben wird FALSE oder ein Array mit den Gerätedaten.

```php
  MEL_Device_GetDataRAW(int $InstanzID);
```
Liest alle Gerätedaten aus und aktualisiert direkt die entsprechenden Variablen. Zurückgegeben wird FALSE oder ein Array mit den Gerätedaten.

```php
  MEL_Device_GetListInfo(int $InstanzID);
```
Liest Geräteinformationen aus und aktualisiert direkt die entsprechenden Variablen. Zurückgegeben wird FALSE oder ein Array mit Informationen.
Wie die Funktion "MEL_Devices_GetList", nur werden hier nur die Informationen zum jeweiligen Gerät zurückgegeben.

```php
  MEL_Device_GetImage(int $InstanzID);
```
Liest das Bild vom Gerät aus (nur möglich, wenn ein eigenes Bild in der MELCloud hochgeladen wurde). Zurückgegeben wird FALSE oder ein Array
mit dem Header vom Bild und dem Bild selbst (base64 kodiert).

```php
  MEL_Device_GetPresets(int $InstanzID);
```
Liest alle in der MELCloud festgelegten Voreinstellungen aus und aktualisiert das zugehörige Variablenprofil (wird im Hintergrund auch automatisch
erledigt und muss nicht zusätzlich manuell durchgeführt werden). Zurückgegeben wird FALSE oder ein Array mit den Voreinstellungen.

```php
  MEL_Devices_GetList(int $InstanzID);
```
Liest Informationen von in der MELCloud verfügbaren Geräte aus und aktualisiert mit den zurückgegeben Daten auch direkt die entsprechenden Variablen
aller Geräte. Zurückgegeben wird FALSE oder ein Array mit Informationen.

```php
  MEL_Devices_GetListRAW(int $InstanzID);
```
Liest alle Informationen von in der MELCloud verfügbaren Geräte aus. Zurückgegeben wird FALSE oder ein Array mit Informationen.

```php
  MEL_DeviceInstance_Configuration(int $InstanzID, string $BuildingID, string $DeviceID, string $DeviceType);
```
Mit dieser Funktion kann man eine Geräte Instanz konfigurieren. Legt man eine Geräte Instanz selbstständig ohne Konfigurator an, dann muss
diese Instanz mit dieser Funktion konfiguriert werden. Die Funktion wird vom Konfigurator verwendet, um eine erstellte Instanz automatisch
konfigurieren zu können. Zurückgegeben wird FALSE oder TRUE.

```php
  MEL_FanSpeed_Set(int $InstanzID, int $FanSpeed);
```
Funktion zum Setzen der gewünschten Lüftergeschwindigkeit von Stufe 1 bis 5 (kann je nach Modell variieren). Zurückgegeben wird FALSE oder TRUE.

```php
  MEL_OperationMode_Set(int $InstanzID, int $OperationMode);
```
Funktion zum Setzen der gewünschten Betriebsart. Zurückgegeben wird FALSE oder TRUE.
Betriebsarten für Klimaanlagen:
- 1 = Heizbetrieb
- 3 = Kühlbetrieb
- 7 = Gebläselüfter
- 8 = Automatik

```php
  MEL_PowerState_Set(int $InstanzID, bool $PowerState);
```
Funktion zum Ausschalten (false) und Einschalten (true) des Gerätes. Zurückgegeben wird FALSE oder TRUE.

```php
  MEL_Preset_Set(int $InstanzID, int $Preset);
```
Funktion zum Setzen einer in der MELCloud festgelegten Voreinstellung. Zurückgegeben wird FALSE oder TRUE.
Zum Ermitteln der Nummer/ID einer bestimmten Voreinstellungen kann die Funktion "MEL_GetPresets" verwendet werden.

```php
  MEL_Temperature_Set(int $InstanzID, float $Temperature);
```
Funktion zum Setzen der gewünschten SOLL-Temperatur (min. und max. Temperatur variieren je nach Betriebsart). Zurückgegeben wird FALSE oder TRUE.

```php
  MEL_VaneHorizontal_Set(int $InstanzID, int $value);
```
Funktion zum Einstellen der horizontalen Schaufel. Zurückgegeben wird FALSE oder TRUE.
- 0 = Auto
- 1 bis max. 5 = Schaufelstellung (abhängig vom Geräte-Typ/Modell)
- 12 = Schwingen (muss vom Gerät unterstützt werden)

```php
  MEL_VaneVertical_Set(int $InstanzID, int $value);
```
Funktion zum Einstellen der vertikalen Schaufel. Zurückgegeben wird FALSE oder TRUE.
- 0 = Auto
- 1 bis max. 5 = Schaufelstellung (abhängig vom Geräte-Typ/Modell)
- 7 = Schwingen (muss vom Gerät unterstützt werden)

```php
  MEL_Weather_Get(int $InstanzID);
```
Liest die in der MELCloud verfügbaren Wetterdaten aus (aktuelle Daten und Vorhersage). Zurückgegeben wird FALSE oder ein Array mit Wetterdaten.


#### I/O Instanz:
```php
  MELIO_Device_GetDataRAW(int $InstanzID, string $BuildingID, string $DeviceID);
```
Liest alle Gerätedaten aus und aktualisiert direkt die entsprechenden Variablen. Zurückgegeben wird FALSE oder ein Array mit den Gerätedaten.

```php
  MELIO_Device_GetImage(int $InstanzID, string $BuildingID, string $DeviceID);
```
Liest das Bild vom Gerät aus (nur möglich, wenn ein eigenes Bild in der MELCloud hochgeladen wurde). Zurückgegeben wird FALSE oder ein Array
mit dem Header vom Bild und dem Bild selbst (base64 kodiert). 

```php
  MELIO_Devices_GetList(int $InstanzID);
```
Liest Informationen von in der MELCloud verfügbaren Geräte aus und aktualisiert mit den zurückgegeben Daten auch direkt die entsprechenden Variablen
aller Geräte. Zurückgegeben wird FALSE oder ein Array mit Informationen.

```php
  MELIO_Devices_GetListRAW(int $InstanzID);
```
Liest alle Informationen von in der MELCloud verfügbaren Geräte aus. Zurückgegeben wird FALSE oder ein Array mit Informationen.

```php
  MELIO_Timer_Control(int $InstanzID, 'Update_GetList', int $TimerOption);
```
Funktion zur Steuerung des Modul-Timers.
- 0 = Timer anhalten
- 1 = Timer starten (mit dem in der Instanz eingestellten Intervall)


#### Konfigurator Instanz:
```php
  MELC_Devices_GetList(int $InstanzID);
```
Liest Informationen von in der MELCloud verfügbaren Geräte aus und aktualisiert mit den zurückgegeben Daten auch direkt die entsprechenden Variablen
aller Geräte. Zurückgegeben wird FALSE oder ein Array mit Informationen.

```php
  MELC_Devices_GetListRAW(int $InstanzID);
```
Liest alle Informationen von in der MELCloud verfügbaren Geräte aus. Zurückgegeben wird FALSE oder ein Array mit Informationen.

> ALLGEMEINER HINWEIS ZU DEN BEFEHLEN: Wird eine Funktion oder ein Wert von einem Gerät nicht unterstützt, dann kommt im Debug eine entsprechende Ausgabe.


## 5. Changelog
Version 1.0:
  - Erster Release