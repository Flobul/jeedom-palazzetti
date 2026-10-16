/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

(function() {

var palaParamComment = {
    0: 'Ignition Timeout (in minutes): <br/>Maximum wait time for successful firing in the Ignition phase.',
    1: 'Test Fire Timeout(in minutes): Maximum wait time for flame detection with igniter off in the Test<br/>Fire sequence.',
    2: 'Select Fuel Mode:<br/>1=Auto – default pellets related feeder and fan parameters are used<br/>2=User – Pellets quality can be selected with PAR92. Based on this selection different feeder and/or fan 1 settings are calculated relatively to the default parameters, set with hidden parameters 53-58<br/>3=Wood – switch to wood mode.<br/>Based on PAR93 one of the three preselected wood quality fan settings (set with hidden parameters 59-61) is loaded.',
    3: 'Heat Up sequence feeder OFF time:<br/>Defines the feeder pause (no dosing) during the Heat Up sequence.',
    4: 'Heat Up sequence feeder ON time:<br/>Defines the feeder dosing time during the Heat Up sequence.',
    5: 'Fuel Ignition sequence feeder OFF time:<br/>Defines the feeder pause during the Fuel Ignition sequence.',
    6: 'Fuel Ignition sequence feeder ON time:<br/>Defines the feeder dosing time during the Fuel Ignition sequence.',
    7: 'Ignition Test sequence feeder OFF time:<br/>Defines the feeder pause during the Ignition Test sequence.',
    8: 'Ignition Test sequence feeder ON time:<br/>Defines the feeder dosing time during the Ignition Test sequence.',
    9: 'Power 1 feeder OFF time:<br/>Defines the feeder pause at power level 1 in the Burning phase.',
    10: 'Power 1 feeder ON time:<br/>Normal regulation - defines the feeder dosing time at power level 1.<br/>Advanced regulation - defines the MINIMUM dosing time for the PID regulator.<br/>Controller calculates the feeder dosing time for each power PID regulator output. Maximum number of steps is 1024.',
    11: 'Power 2 feeder OFF time:<br/>Defines the feeder pause at power level 2 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    12: 'Power 2 feeder ON time:<br/>Defines the feeder dosing time at power level 2 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    13: 'Power 3 feeder OFF time:<br/>Defines the feeder pause at power level 3 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    14: 'Power 3 feeder ON time:<br/>Defines the feeder dosing time at power level 3 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    15: 'Power 4 feeder OFF time:<br/>Defines the feeder pause at power level 4 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    16: 'Power 4 feeder ON time:<br/>Defines the feeder dosing time at power level 4 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    17: 'Power 5 feeder OFF time:<br/>Defines the feeder pause at power level 5 in the Burning phase.<br/>NOTE: Not used when advanced regulation is selected.',
    18: 'Power 5 feeder ON time:<br/>Normal regulation - defines the feeder dosing time at power level 5.<br/>Advanced regulation - defines the MAXIMUM dosing time for the PID regulator.<br/>Controller calculates the feeder dosing time for each power PID regulator output. Maximum number of steps is 1024.',
    19: 'FAN1 speed in Fire Stop sequence:<br/>Defines the fan 1 speed during the Stop Fire sequence.<br/>Stop Fire is active when the heating device is turned OFF and flue gases are not cooled below PAR56.<br/>In BURNER configuration the Stop Fire runs until the Flame Sensor gives the NO FLAME signal (set with PAR79 and PAR80).<br/>Options:<br/>OPEN LOOP mode (settings 0-255 are 0-100% applied voltage to the motor. Exact speed of the motor is not measured)<br/>CLOSED SPEED LOOP mode (settings 0-255 are 0-2900 rpm of the motor.<br/>The motor speed is parameter value * 11.4)<br/>CLOSED PRESSURE LOOP mode (parameters settings 0-255 are 0-16Pa differential pressure).<br/>This scaling can be customized according to air flow tube characteristics)',
    20: 'FAN1 speed in Test Fire sequence:<br/>Test Fire is executed when the heating device is switched ON and 30 seconds after the temperature of flue gases is higher than PAR54 or the Flame Sensor reports the FLAME condition (in Burner mode).<br/>In this sequence, the controller starts the fan with the set speed.<br/>At the end of the sequence it checks if flue gases temperature rose for more than 3°C/min or if Flame Sensor gave the FLAME signal.<br/>If so, the Burning phase is started, if not, the test is repeated until timeout (set with PAR1).',
    21: 'FAN1 speed in Heat Up sequence:<br/>Defines the fan speed in the Heat Up sequence.',
    22: 'FAN1 speed in Fuel Ignition sequence:<br/>Defines the fan speed in the Fuel Ignition sequence.',
    23: 'FAN1 speed in Ignition Test sequence:<br/>Defines the fan speed in the Ignition Test sequence.',
    24: 'FAN1 speed at power 1:<br/>Normal regulation - defines the FAN1 speed at power 1 (depends on the selected fan mode - open loop, closed speed loop or closed pressure loop).<br/>Advanced regulation – defines the MINIMUM allowed FAN1 speed for the PID regulator.<br/>Controller calculates the FAN1 speed for each power PID regulator output.<br/>Maximum number of steps is 1024.',
    25: 'FAN1 speed at power 2:<br/>Defines the FAN1 speed at power 2 (depends on the selected fan mode - open loop, closed speed loop or closed pressure loop).<br/>NOTE: Not used when advanced regulation is selected.',
    26: 'FAN1 speed at power 3:<br/>Defines the FAN1 speed at power 3 (depends on the selected fan mode - open loop, closed speed loop or closed pressure loop).',
    27: 'FAN1 speed at power 4:<br/>Defines the FAN1 speed at power 4 (depends on the selected fan mode - open loop, closed speed loop or closed pressure loop).<br/>NOTE: Not used when advanced regulation is selected.',
    28: 'FAN1 speed at power 5:<br/>Normal regulation – defines the FAN1 speed at power 5 (depends on the selected fan mode – open loop, closed speed loop or closed pressure loop).<br/>Advanced regulation – defines the MAXIMUM allowed FAN1 speed for the PID regulator.<br/>Controller calculates the FAN1 speed for each power PID regulator output. Maximum number of steps is 1024.',
    29: 'FAN2 speed in Test Fire sequence<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    30: 'FAN2 speed in Stop Fire sequence:<br/>NOTE: If the flue gases temperature is below PAR58 in FAN2AsAmbient mode, FAN2 is switched off.',
    31: 'FAN2 speed in Heat Up sequence<br/>NOTE: If the flue gases temperature is below PAR58 in FAN2 for Room Heating mode, FAN2 is switched off.',
    32: 'FAN2 speed in Fuel Ignition sequence<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    33: 'FAN2 speed in Ignition Test sequence<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    34: 'FAN2 speed at power 1:<br/>Normal regulation – defines the FAN2 speed at power 1 (depends on the selected fan mode – Fan 2 for Room Heating, Fan 2 As Chimney, or Fan 2 As Chimney used in closed pressure loop).<br/>Advanced regulation – defines the MINIMUM allowed FAN2 speed for the PID regulator.<br/>Controller calculates the FAN2 speed for each power PID regulator output. Maximum number of steps is 1024.<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    35: 'FAN2 speed at power 2:<br/>NOTE: Not used when advanced regulation is selected.<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    36: 'FAN2 speed at power 3:<br/>NOTE: Not used when advanced regulation is selected.<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    37: 'FAN2 speed at power 4:<br/>NOTE: Not used when advanced regulation is selected.<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    38: 'FAN2 speed at power 5:<br/>Normal regulation – defines the FAN2 speed at power 5(depends on the selected fan mode – Fan 2 for Room Heating, Fan 2 As Chimney, or Fan 2 As Chimney used in closed pressure loop).<br/>Advanced regulation – defines the MAXIMUM allowed FAN2 speed for the PID regulator.<br/>Controller calculates the FAN2 speed for each power PID regulator output. Maximum number of steps is 1024.<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    39: 'FAN2 speed at Quickheat power:<br/>Defines the FAN2 speed at Quickheat power in FAN2 for Room Heating mode.<br/>NOTE: If the flue gases temperature is below PAR58 [⁰C] in FAN2 for Room Heating mode, FAN2 is switched off.',
    40: 'FAN3 speed in Stop Fire sequence<br/>NOTE: FAN3 operates in ON/OFF mode only. The valid settings for this parameter are 0 (FAN3 OFF) or 255 (FAN3 ON at maximum speed).<br/>You must also set the PAR77 and an air temperature sensor (NTC) must be connected to I/O T02.<br/>FAN3 only operates when FAN2 is working.',
    41: 'FAN3 speed in Test Fire sequence<br/>NOTE: FAN3 does not operate in this sequence.',
    42: 'FAN3 speed in Heat Up sequence<br/>NOTE: FAN3 does not operate in this sequence.',
    43: 'FAN3 speed in Fuel Ignition sequence<br/>NOTE: FAN3 does not operate in this sequence.',
    44: 'FAN3 speed in Ignition Test sequence<br/>NOTE: FAN3 does not operate in this sequence.',
    45: 'FAN3 speed at power 1<br/>NOTE: FAN3 operates in ON/OFF mode only. The valid settings for this parameter are 0 (FAN3 OFF) or 255 (FAN3 ON at maximum speed).<br/>You must also set the PAR77 and an air temperature sensor (NTC) must be connected to I/O T02.',
    46: 'FAN3 speed at power 2<br/>NOTE: FAN3 operates in ON/OFF mode only. The valid settings for this parameter are 0 (FAN3 OFF) or 255 (FAN3 ON at maximum speed).<br/>You must also set the PAR77 and an air temperature sensor (NTC) must be connected to I/O T02.<br/>FAN3 only operates when FAN2 is working.',
    47: 'FAN3 speed at power 3<br/>NOTE: FAN3 operates in ON/OFF mode only. The valid settings for this parameter are 0 (FAN3 OFF) or 255 (FAN3 ON at maximum speed).<br/>You must also set the PAR77 and an air temperature sensor (NTC) must be connected to I/O T02.<br/>FAN3 only operates when FAN2 is working.',
    48: 'FAN3 speed at power 4<br/>NOTE: FAN3 operates in ON/OFF mode only. The valid settings for this parameter are 0 (FAN3 OFF) or 255 (FAN3 ON at maximum speed).<br/>You must also set the PAR77 and an air temperature sensor (NTC) must be connected to I/O T02.</brFAN3 only operates when FAN2 is working.',
    49: 'FAN3 speed at power 5<br/>NOTE: FAN3 operates in ON/OFF mode only. The valid settings for this parameter are 0 (FAN3 OFF) or 255 (FAN3 ON at maximum speed).<br/>You must also set the PAR77 and an air temperature sensor (NTC) must be connected to I/O T02.<br/>FAN3 only operates when FAN2 is working.',
    50: 'Cool Fluid exit temperature difference(in °C):<br/>In Stove mode: the heating device restarts after room temperature drops below [PAR51-PAR50]. PAR50 is set in 0.1 degrees (10 equals 1.0°C), PAR51 is set in degrees (20 equals 20°C).<br/>In Stove mode with water pump: the heating device restarts after air temperature drops below [PAR51-PAR50] in 0.1 degree resolution and water temperature drops below [PAR52-PAR96].<br/>Note that PAR51 defines desired air temperature and PAR52defines desired water temperature (for Stove mode with water pump only!).<br/>In Boiler/Burner mode: the heating device restarts after air/water temperature drops below [PAR51-PAR50] in one degree resolution (value 60 equals 60°C).<br/>When accumulator is enabled in the Boiler/Burner mode, the accumulator restart temperature is [PAR84-PAR50] in one degree resolution (value 60 equals 60°C).',
    51: 'Desired Water/Air temperature (in °C):<br/>In Stove mode: defines the desired air temperature in xx.x°C (maximum setting value is 51.0).<br/>In Stove mode with water pump: defines the desired air (room) temperature in xx.x°C (maximum setting value is 51.0).<br/>In Boiler/Burner mode: defines the desired water temperature in xx°C.<br/>In Boiler mode with accumulator, this parameter is calculated as [PAR84 +PAR86] in °C and cannot be modified manually.',
    52: 'In Stove mode with water pump: this parameter defines the desired outbound water temperature in °C.<br/>Feeder slows dosing when water temperature nears PAR52 (in °C).<br/>Not used in Boiler/Burner mode.',
    53: 'Cool Fluid Entry temperature difference (in °C):<br/>In Stove mode: if set to 0, the stove does not stop.<br/>In this case you can use the external thermostat (connected to I/O I03) for switching the stove ON/OFF.<br/>If set to <>0, the stove enters Cool Fluid when air temperature exceeds [PAR51 (in °C) +PAR53 (in 0.1°C)].<br/>In Stove mode with water pump:<br/>The stop temperature based on the air temperature measurement is the same as above. The water stop temperature is defined with PAR95.<br/>In Boiler/Burner mode: this parameter defines the stop level in xx°C.',
    54: 'Ignition Test gases temperature (in °C):<br/>Defines the temperature threshold in the Ignition Test sequence when the controller starts checking the flue gases temperature slope.<br/>If the temperature rises for at least 3 °C/min and the flue gases temperature exceeds PAR56, the device enters the Burning phase after 45 seconds.',
    55: 'Gases modulation start temperature (in °C):<br/>Flue gases temperature threshold, when the controller begins to decrease the burning power.<br/>IMPORTANT: When setting this parameter the actual value is double the value, entered in PC-PRO or UI, except for stoves, where value in PC-PRO is the actual value, and value in UI is doubled.',
    56: 'Stop Fire exit temperature (in °C):<br/>In the Stop Fire sequence the FAN1 operates until the flue gases temperature falls below PAR56.',
    57: 'Alarm triggering gases temperature (in °C):<br/>If the flue gases temperature exceeds PAR57, the Alarm Gases is reported and the controller switches off the system.<br/>IMPORTANT: When setting this parameter the actual value is double the value, entered in PC-PRO or UI, except for stoves, where value in PC-PRO is the actual value, and value in UI is doubled.',
    58: 'FAN2 stop gases temperature (in °C):<br/>If Fan 2 As Chimney is enabled, this parameter is ignored.<br/>If Fan 2 for Room Heating is enabled, this PAR defines the threshold for FAN2 operation, regardless of the operating sequence.<br/>FAN2 starts when the gases temperature rises above PAR58, and stops when it drops below PAR58.',
    59: 'Temperature for No Pellets Alarm (in °C):<br/>Defines the flue gases temperature threshold for the No Pellets alarm in the Burning state.',
    60: 'Time between two blow cleanings (in minutes):<br/>During Burning the burning chamber is cleaned periodically.<br/>Every PAR60 minutes the FAN1 blows for PAR61 seconds at PAR62 speed.<br/>During blow cleaning the feeder stops dosing fuel.',
    64: 'Defines the number of blow cleanings before the heating device goes to FIRE STOP and performs the "Erato cleaning".',
    65: 'Ash extraction auger activation duration (in seconds):<br/>Defines the duration of the ash extraction auger cleaning period.<br/>The Ash extraction auger (connected to I/O O03) is active for PAR65 seconds every PAR66 minutes.<br/>The Ash and Chamber Cleaning configuration option must be set.<br/>NOTE: The ash extraction auger does not operate while the heating device is turned off.<br/>It operates only during normal operation of the heating device.',
    66: 'Ash extraction auger activation period (in minutes):<br/>Defines the period for activating the ash extraction auger.<br/>NOTE: See also the description for PAR65.',
    67: 'Water pump turn on temperature (in °C):<br/>Defines the water temperature threshold when the water pump turns ON.',
    68: 'Water pump turn off temperature (in °C):<br/>Defines the water pump turn off temperature threshold (MUST BE lower than PAR67 value).',
    69: 'Backwater temperature (in °C):<br/>Defines the backwater temperature threshold if a backwater temperature bypass pump is used.',
    70: 'Heat Up sequence duration (in seconds)',
    71: 'Not user definable',
    72: 'Not user definable',
    73: 'User fuel mode feeder speed:<br/>Defines the relative feeder speed compared to the default feeder speed in pellets powered systems:<br/>50 – the feeder in user fuel mode is 50% slower than the default feeder setting<br/>100 - the feeder in user fuel mode has the same speed as the default feeder setting<br/>150 - the feeder is 50 % faster<br/>This parameter is set according to the selected pellets type factory settings. It can be modified.',
    74: 'User fuel mode FAN1 speed:<br/>Defines the relative FAN1 speed in user fuel mode (see PAR73).<br/>This parameter is set according to the selected pellets type factory settings. It can be modified.',
    75: 'Wood fuel mode FAN1 speed:<br/>Defines the relative FAN1 speed in wood fuel mode.<br/>This parameter is set to 100 if the default wood type is used, or to different values if wood type other than default is used.<br/>It can be modified to further adjust the burning parameters.',
    76: 'Controller configuration set:<br/>Selects one of 12 predefined controller configurations.<br/>Configuration 13 is custom. We recommend locking this parameter in the Fumis PC-PRO application to prevent editing.',
    77: 'External Room Temperature (in °C):<br/>Defines the external room temperature, regulated with FAN3.',
    78: 'Flame present detection level:<br/>The flame sensor operates in range from 0 (no fire) to 255 (full fire).<br/>Defines the sensor input for valid flame detection.',
    79: 'No flame detection level:<br/> Defines the lower sensor input level for no flame detection.',
    80: 'Time delay before OFF detected (in seconds):<br/>Sensor signal is valid after certain time period expires.<br/>Defines the time threshold for OFF flame detection.<br/>The recommended value is 45-100 seconds, depending on combustion system size and construction.',
    81: 'Desired constant underpressure:<br/>Defines the pressure level if FAN2 is used as the chimney fan in the Fan2 Pressure Close Loop mode.<br/>Both Fan 2 as Chimney Fan and Fan 2 Pressure Close Loop options must be enabled',
    82: 'Pressure error threshold value:<br/>If this parameter is <>0, the underpressure/airflow error is enabled.<br/>If the underpressure in the burning chamber is lower than PAR82, the alarm is triggered after PAR83 seconds.',
    83: 'Time delay before triggering underpressure/airflow error (in seconds):<br/>Defines the time delay before the underpressure error is triggered.',
    84: 'Desired accumulator temperature (in °C):<br/>Defines the desired water temperature in heat accumulator.<br/>NOTE: Heat Accumulator configuration option must be set.',
    85: 'Pump minimum difference (in °C):<br/>The minimum difference between the boiler temperature and the backwater temperature to stop the water pump.',
    86: 'Minimum difference between T Boiler and T Acc (in °C):<br/>Sets the amount of heat loss that occurs between the boiler and heat accumulator.<br/>EXAMPLE: If PAR 84=60 (desired temperature in the heat accumulator) and PAR 86=5, water in the boiler is heated to 65°C (PAR51 will automatically be set to this value).<br/>If we assume that the heat loss is 5°C, we achieve 60°C in heat accumulator, which equals the desired temperature (PAR84).<br/>NOTE: The PAR51 value (desired  Temperature in the boiler) is automatically set as PAR84 + PAR86.',
    87: 'Keep Fire FAN1 speed',
    88: 'Keep Fire feeder dosing time (in milliseconds):<br/>Feeder is activated for [PAR88 *100msec]',
    89: 'FAN1 duration during Keep Fire (in seconds):<br/>Defines the time period for FAN1 operation during Keep Fire.',
    90: 'Keep Fire repetition period (in minutes):<br/>If the boiler is in stand-by mode, Keep Fire is repeated every PAR90 minutes.',
    91: 'Feeder2 dosing delay / activation time factor:<br/> Defines the feeder 2 operation time (in seconds) if Fuel Level Sensor configuration option is set and Feeder2 acts as pellets transporter from the main fuel storage to the stove pellets buffer.<br/>Defines the relative dosing to the main feeder if Pellets Level Sensor configuration option is not selected:<br/>50 – feeder 2 is 50% slower than the main feeder<br/>150 - feeder 2 is 50% faster than the main feeder<br/>NOTE: if PAR is set to 0, feeder 2 operates constantly (except when combustion system is turned off)',
    92: 'Defines one of the three pellets fuel quality types:<br/>1 - feeder and fan 1 operate according to the hidden parameters 53 (FeederFactorPelletsType1) and 54 (FAN1FactorPelletsType1) settings in %.*<br/>2 - feeder and fan 1 operate according to the hidden parameters 55 (FeederFactorPelletsType2) and 56 (FAN1FactorPelletsType2) settings in %.*<br/>3 - feeder and fan 1 operate according to the hidden parameters 57 (FeederFactorPelletsType3) and 58 (FAN1FactorPelletsType3) settings in %.*<br/>NOTE: Pellets quality (1, 2 or 3) can also be selected by end user through user interface.<br/>* compared to ONORM M7135 standard pellets',
    93: 'Defines one of the three wood fuel quality types:<br/>1 - fan 1 operates according to the hidden parameter 59 (FAN1WoodType1) settings in %.<br/>2 - fan 1 operates according to the hidden parameter 60 (FAN1WoodType2) settings in %.<br/>3 - fan 1 operates according to the hidden parameter 61 (FAN1WoodType3) settings in %.<br/>NOTE: Wood quality (1, 2 or 3) can also be selected by end user through user interface.',
    94: 'Time to service warning level (in days):Defines the service due time.<br/>This parameter is used in connection with the Service time counter. PAR94 defines the service time period in days, but the Service time counter counts the hours.<br/>This means that if you set the PAR94=1 (day), the Service time counter displays 24 (hours). Maximum value of the PAR94 is 255, which means 6120 hours (255x24=6120).<br/>The display can show up to 999 hours, value over 999 hours is displayed as Hi.<br/>To reset the Service time counter, navigate to the Reset service timer entry and press the Enter button to enter the edit mode.<br/>The display shows Off. Press the right arrow menu button to display On and then press the Enter button to confirm.',
    95: 'In Stove mode with water pump only: defines the temperature threshold to switch off the stove (entering Cool fluid mode).<br/>Other modes: PAR95 and PAR96 are not used.',
    96: 'In Stove mode with water pump only: defines the restart level from Cool Fluid.<br/>Other modes: PAR95 and PAR96 are not used.',
    97: 'Minimum temperature difference for modulation (in °C):<br/>When normal regulation is used, defines how many degrees before the desired temperature is reached the controller starts to decrease the burning power.<br/>NOTE: When modulation water pump is used.',
    98: 'Full magazine sensor signal (in cm):<br/>Defines the full magazine level.',
    99: 'Warning level sensor signal (in cm):<br/>Defines the warning magazine level.',
    100: 'Empty Magazine sensor signal (in cm):<br/>Defines the empty magazine level.<br/>At this level the combustion device is turned off to prevent feeder emptying and thus enables faster restart (no need to wait for the feeder to refill).',
    101: 'Time for ash blow out at Stop Fire (in seconds):<br/>After the Stop Fire and Cool Fluid, the FAN1 will operate at maximum speed for PAR101 period.',
    102: 'Minimum air temperature to keep (in °C):<br/>Only in stove mode and activated timer.<br/>OPERATION: The stove turns on when Tair (PAR 51 [⁰C]) is for 0,1⁰C lower than PAR 102 [0,1 x ⁰C].<br/>The stove turns off when Tair is at least for 0,1⁰C higher than PAR102 + PAR53 [⁰C].',
    103: 'Water pump minimum speed (when modulation water pump is used)',
    104: 'Water pump maximum speed (when modulation water pump is used)'
};
var palaParamUnit = {
    0: '{{minutes}}',
    1: '{{minutes}}',
    2: '*100{{ms}}',
    3: '*100{{ms}}',
    4: '*100{{ms}}',
    5: '*100{{ms}}',
    6: '*100{{ms}}',
    7: '*100{{ms}}',
    8: '*100{{ms}}',
    9: '*100{{ms}}',
    10: '*100{{ms}}',
    11: '*100{{ms}}',
    12: '*100{{ms}}',
    13: '*100{{ms}}',
    14: '*100{{ms}}',
    15: '*100{{ms}}',
    16: '*100{{ms}}',
    17: '*100{{ms}}',
    18: '*100{{ms}}',
    50: '°C',
    51: '°C',
    52: '°C',
    53: '°C',
    54: '°C',
    55: '°C',
    56: '°C',
    57: '°C',
    58: '°C',
    59: '°C',
    60: '{{minutes}}',
    65: '{{secondes}}',
    66: '{{minutes}}',
    67: '°C',
    68: '°C',
    69: '°C',
    73: '%',
    74: '%',
    75: '%',
    77: '°C',
    80: '{{secondes}}',
    83: '{{secondes}}',
    84: '°C',
    85: '°C',
    86: '°C',
    88: '*100{{ms}}',
    89: '{{secondes}}',
    90: '{{minutes}}',
    91: '%',
    94: '{{jours}}',
    95: '°C',
    96: '°C',
    97: '°C',
    98: 'cm',
    99: 'cm',
    100: 'cm',
    101: '{{secondes}}',
    102: 'cm'
}
var palaParam = {
    0: '{{Durée phase d\'allumage (Fuel Ignition)}}',
    1: '{{Durée phase FireCheck}}',
    2: 'Fuel Type',
    3: '{{Pause du moteur d\'alimentation en phase HeatUp}}',
    4: '{{Durée travail de fonctionnement de l\'écluse en phase HeatUp}}',
    5: '{{Pause du moteur d\'alimentation en phase Fuel Ignition}}',
    6: '{{Durée travail de fonctionnement de l\'écluse en phase Fuel Ignition}}',
    7: '{{Pause de l\'écluse en phase Fuel Ignition}}',
    8: '{{Durée fonctionnement de l\'écluse en phase Fire Check}}',
    9: 'Power 1 Feeder 1 OFF Time',
    10: '{{Durée fonctionnement de l\'écluse en puissance 1}}',
    11: 'Power 2 Feeder 1 OFF Time',
    12: 'Power 2 Feeder 1 ON Time',
    13: 'Power 3 Feeder 1 OFF Time',
    14: 'Power 3 Feeder 1 ON Time',
    15: 'Power 4 Feeder 1 OFF Time',
    16: 'Power 4 Feeder 1 ON Time',
    17: 'Power 5 Feeder 1 OFF Time',
    18: '{{Durée fonctionnement de l\'écluse en puissance 5}}',
    19: '{{Débit du ventilateur d\'extraction de fumées en StopFire}}',
    20: '{{Débit du ventilateur d\'extraction de fumées en TestFire}}',
    21: '{{Débit du ventilateur d\'extraction de fumées en HeatUp}}',
    22: '{{Débit du ventilateur d\'extraction de fumées en Fuel Ignition}}',
    23: '{{Débit du ventilateur d\'extraction de fumées en Fire Check}}',
    24: '{{Débit du ventilateur d\'extraction de fumées en puissance 1}}',
    25: '{{Débit du ventilateur d\'extraction de fumées en puissance 2}}',
    26: '{{Débit du ventilateur d\'extraction de fumées en puissance 3}}',
    27: '{{Débit du ventilateur d\'extraction de fumées en puissance 4}}',
    28: '{{Débit du ventilateur d\'extraction de fumées en puissance 5}}',
    29: '{{Valeur ventilateur en phase TestFire}}',
    30: '{{Valeur ventilateur en phase StopFire}}',
    31: '{{Valeur ventilateur en phase HeatUp}}',
    32: '{{Valeur ventilateur en phase Fuel Ignition}}',
    33: '{{Valeur ventilateur en phase FireCheck}}',
    34: '{{Valeur ventilateur en puissance 1}}',
    35: '{{Valeur ventilateur en puissance 2}}',
    36: '{{Valeur ventilateur en puissance 3}}',
    37: '{{Valeur ventilateur en puissance 4}}',
    38: '{{Valeur ventilateur en puissance 5}}',
    39: '{{Valeur ventilateur en Over Boost}}',
    40: '{{Vitesse de l\'extracteur des fumées en StopFire (x11,74)}}',
    41: '{{Vitesse de l\'extracteur des fumées en TestFire (x11,74)}}',
    42: '{{Vitesse de l\'extracteur des fumées en HeatUp (x11,74)}}',
    43: '{{Vitesse de l\'extracteur des fumées en Fuel Ignition (x11,74)}}',
    44: '{{Vitesse de l\'extracteur des fumées en Fire Check (x11,74)}}',
    45: '{{Vitesse de l\'extracteur des fumées en puissance 1 (x11,74)}}',
    46: '{{Vitesse de l\'extracteur des fumées en puissance 2 (x11,74)}}',
    47: '{{Vitesse de l\'extracteur des fumées en puissance 3 (x11,74)}}',
    48: '{{Vitesse de l\'extracteur des fumées en puissance 4 (x11,74)}}',
    49: '{{Vitesse de l\'extracteur des fumées en puissance 5 (x11,74)}}',
    50: '{{Delta T pour sortir du CoolFluid}}',
    51: 'Water storage set point temperature',
    52: 'Water Modulation Temp setup menu [6]',
    53: 'Delta T Cool Fluid',
    54: '{{Température des fumées pour la sortie de la phase FireCheck}}',
    55: '{{MAX température des fumées pour la modulation}}',
    56: '{{Température des fumées pour la sortie de la phase StopFire}}',
    57: '{{Température des fumées MAX}}',
    58: '{{Température pour l\'arrêt du ventilateur d\'air}}',
    59: '{{MIN température des fumées pendant la phase de travail}}',
    60: '{{Temps entre 2 cycles de nettoyage}}',
    61: '{{Temps total pour la cycle de nettoyage}}',
    62: '{{Débit du ventilateur des fumées en phase de nettoyage 1/%}}',
    /*63: '',*/
    64: '{{Valeur MIN de pression}}',
    65: '{{Retard valeur MIN de pression}}',
    66: '{{Type de pellet par défaut}}',
    67: 'Average Temp water/bwater for anti-cond/work pump phase',
    68: '{{Température moyenne de l\'eau de la pompe en fonctionnement}}',
    69: 'delta Temp water/bwater MAX for pump modulation',
    70: '{{Durée phase de HeatUp (*5)}}',
    71: '{{Durée test (gradient) (*5)}}',
    72: 'Delta T (gradient)',
    73: 'User Fuel Feeder 1 ON Time Factor',
    74: 'User Fuel Fan 1 Power Factor',
    75: 'Wood Fuel Fan 1 Power Factor',
    76: 'Installation configuration',
    77: '2nd Room Temperature',
    78: 'Flame ON Level',
    79: '{{Variation (%) sur la pression souhaitée}}',
    80: '{{Période de vérification obtention pression souhaitée}}',
    81: 'Underpressure Setpoint',
    82: '{{Pression MIN au démarrage}}',
    83: '{{Délai avant la vérification du PAR82}}',
    84: 'Water storage set point temperature',
    85: '{{Delta T MIN entre départ et retour d\'eau pour démarrage de la pompe}}',
    86: 'Boiler to Accumulator Temperature Drop',
    87: 'Keep Fire Fan 1 Power',
    88: 'Keep Fire Feeder 1 ON Time',
    89: 'Keep Fire Fan 1 Duration',
    90: 'Keep Fire Period',
    91: 'Feeder 2 Delay / ON Time Factor',
    92: '{{Type de pellets}}',
    93: '{{Qualité de pellets}}',
    94: '{{Jours avant avis de faire la manutention}}',
    95: 'Stove Cool Fluid Entry Temp. Diff.',
    96: 'Stove Cool Fluid Exit Temp. Diff.',
    97: 'T1-T2 for Min. Modulation Speed',
    98: '{{Leveltronic niveau PLEIN}}',
    99: '{{Leveltronic niveau BAS}}',
    100: '{{Leveltronic niveau VIDE}}',
    101: 'Blow out time',
    102: 'Frost protection temperature',
    103: 'Water Pump Minimum Speed',
    104: 'Water Pump Maximum Speed'
};

function addParamToTable(_param) {
    var tr = '<tr class="param" data-param_id="' + init(_param.id) + '">'
    tr += '<td>'
    tr += '    <input class="paramAttr form-control input-sm roundedLeft" disabled data-l1key="id" placeholder="{{Numéro du paramètre}}">'
    tr += '</td>'

    tr += '<td>'
    tr += '    <span class="paramAttr" disabled data-l1key="description"></span>'
    tr += '</td>'

    tr += '<td>'
    tr += '    <input class="eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="commentaire" data-l3key=' + init(_param.id) + '>'
    tr += '</td>'

    tr += '<td>'
    tr += '    <input type="number" min="0" max="255" step="1" inputmode="numeric" class="paramAttr form-control input-sm" data-l1key="value" title="{{Écriture autorisée de 0 à 255}}" placeholder="{{Valeur du paramètre}}">'
    tr += '</td>'

    tr += '<td>'
    tr += '    <span class="paramAttr" data-l1key="unit" title="{{Unité du paramètre}}" placeholder="{{Unité}}"></span>'
    tr += '</td>'

    tr += '<td>'
    tr += '    <a class="btn btn-success btn-xs paramAction" data-action="update" title="{{Rafraîchir le paramètre}}"><i class="fas fa-sync"></i> </a>';
    if (typeof palazzettiParameterWritesEnabled !== 'undefined' && palazzettiParameterWritesEnabled) {
        tr += '    <a class="btn btn-warning btn-xs paramAction" data-action="modify" title="{{Modifier le paramètre}}"><i class="fas fa-rss"></i> <span class="hideMe">{{Modifier}}</span></a>';
    }
    tr += '</td>'
    tr += '</tr>';

	    const body = document.querySelector('#table_param tbody');
	    body.insertAdjacentHTML('beforeend', tr);
	    body.lastElementChild.setJeeValues(_param, '.paramAttr');
}

Object.keys(palaParam).forEach(function(id) { addParamToTable({id: id, description: palaParam[id]}); });

const parameterTable = document.getElementById('table_param');
const refreshButton = document.querySelector('.paramAction[data-action="refresh"]');
const saveCommentsButton = document.querySelector('.paramAction[data-action="saveComments"]');

function setParameterButtonState(button, busy, busyLabel, idleLabel) {
    if (!button) return;
    button.classList.toggle('btn-warning', busy);
    button.classList.toggle('btn-success', !busy);
    button.classList.toggle('disabled', busy);
	    const icon = button === saveCommentsButton ? 'save' : 'sync';
	    button.innerHTML = '<i class="fas fa-' + icon + (busy ? ' fa-spin' : '') + '"></i> ' + (busy ? busyLabel : idleLabel);
}

function getParamValue(id, paramId) {
    const isFullRefresh = paramId === undefined;
    const requestData = {action: 'getParam', id: id};
    if (!isFullRefresh) requestData.param_id = paramId;

    if (isFullRefresh) setParameterButtonState(refreshButton, true, '{{Rafraîchissement en cours}}', '{{Rafraîchir les paramètres}}');
    domUtils.ajax({
        type: 'POST',
        url: 'plugins/Palazzetti/core/ajax/Palazzetti.ajax.php',
        data: requestData,
        dataType: 'json',
        error: function(request, status, error) {
            if (isFullRefresh) setParameterButtonState(refreshButton, false, '', '{{Rafraîchir les paramètres}}');
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            if (isFullRefresh) setParameterButtonState(refreshButton, false, '', '{{Rafraîchir les paramètres}}');
            if (data.state !== 'ok' || !data.result || (!data.result.PARM && !data.result.DATA)) {
                jeedomUtils.showAlert({message: '{{Réponse de la passerelle invalide ou incomplète.}}', level: 'danger'});
                return;
            }
            if (Array.isArray(data.result.PARM)) {
                data.result.PARM.forEach(function(value, index) {
                    const input = parameterTable.querySelector('tr[data-param_id="' + index + '"] .paramAttr[data-l1key="value"]');
                    if (input) { input.value = value; input.dataset.value = value; }
                });
                return;
            }
            const key = 'PAR' + paramId;
            const input = parameterTable.querySelector('tr[data-param_id="' + paramId + '"] .paramAttr[data-l1key="value"]');
            if (input && Object.prototype.hasOwnProperty.call(data.result.DATA || {}, key)) {
                input.value = data.result.DATA[key];
                input.dataset.value = data.result.DATA[key];
                input.style.fontWeight = 'bold';
                input.style.fontStyle = 'oblique';
            }
        }
    });
}

refreshButton?.addEventListener('click', function() { getParamValue(eqPalaId); });
parameterTable?.addEventListener('click', function(event) {
    const button = event.target.closest('.paramAction[data-action]');
    if (!button) return;
    const row = button.closest('tr');
    const id = row.querySelector('.paramAttr[data-l1key="id"]').value;
    const input = row.querySelector('.paramAttr[data-l1key="value"]');
    if (button.dataset.action === 'update') {
        getParamValue(eqPalaId, id);
        return;
    }
    if (button.dataset.action !== 'modify') return;
    if (input.value === '') {
        jeedomUtils.showAlert({message: '{{Veuillez entrer une valeur}}', level: 'danger'});
        return;
    }
    const description = row.querySelector('.paramAttr[data-l1key="description"]').textContent;
    const oldValue = input.dataset.value || '';
    jeeDialog.confirm('{{Êtes-vous sûr de vouloir modifier le paramètre}} ' + id + ' : ' + description + ' ? {{De}} ' + oldValue + ' {{à}} ' + input.value, function(confirmed) {
        if (!confirmed) return;
        domUtils.ajax({
            type: 'POST',
            url: 'plugins/Palazzetti/core/ajax/Palazzetti.ajax.php', dataType: 'json',
            data: {action: 'setParam', id: eqPalaId, param_id: id, param_value: input.value},
            error: function(request, status, error) { handleAjaxError(request, status, error); },
            success: function(data) {
                if (data.state === 'ok' && data.result?.INFO?.RSP === 'OK') {
                    input.dataset.value = input.value;
                    jeedomUtils.showAlert({message: '{{Valeur}} ' + input.value + ' {{envoyée avec succès dans le paramètre}} ' + id, level: 'success'});
                } else {
                    jeedomUtils.showAlert({message: '{{La modification du paramètre a échoué.}}', level: 'danger'});
                }
            }
        });
    });
});

if (eqLogic.configuration.commentaire && Array.isArray(eqLogic.configuration.commentaire)) {
    const comments = eqLogic.configuration.commentaire[0] || {};
    Object.keys(comments).forEach(function(id) {
        const input = parameterTable.querySelector('.eqLogicAttr[data-l2key="commentaire"][data-l3key="' + id + '"]');
        if (input) input.value = comments[id];
    });
}

parameterTable.querySelectorAll('tbody tr').forEach(function(row) {
    const id = row.querySelector('.paramAttr[data-l1key="id"]').value;
    const description = row.querySelector('.paramAttr[data-l1key="description"]');
    if (palaParamComment[id]) {
        const tooltip = document.createElement('sup');
        tooltip.innerHTML = ' <i class="fas fa-question-circle tooltipstered"></i>';
        tooltip.firstElementChild.title = palaParamComment[id];
        description.appendChild(tooltip);
    }
    const unit = row.querySelector('.paramAttr[data-l1key="unit"]');
    if (unit && palaParamUnit[id]) unit.textContent = palaParamUnit[id];
});

saveCommentsButton?.addEventListener('click', function() {
    setParameterButtonState(saveCommentsButton, true, '{{Sauvegarde en cours}}', '{{Sauvegarder les commentaires}}');
    eqLogic.configuration = eqLogic.configuration || {};
    const values = document.getElementById('paramtab').getJeeValues('.eqLogicAttr')[0];
    eqLogic.configuration.commentaire = [values.configuration.commentaire || {}];
    jeedom.eqLogic.save({
        type: 'Palazzetti',
        eqLogics: [eqLogic],
        error: function(error) {
            setParameterButtonState(saveCommentsButton, false, '', '{{Sauvegarder les commentaires}}');
            jeedomUtils.showAlert({message: '{{Impossible de sauvegarder les commentaires.}}', level: 'danger'});
        },
        success: function() {
            setParameterButtonState(saveCommentsButton, false, '', '{{Sauvegarder les commentaires}}');
            jeedomUtils.showAlert({message: '{{Commentaires sauvegardés avec succès}}', level: 'success'});
        }
    });
});

jeeFrontEnd.modifyWithoutSave = false;
getParamValue(eqPalaId);
})();
