#!/bin/bash
#update default php time zone in /etc/php.ini with local date timezone
#
#linux manual
#https://linux.die.net/man/1/date
#
#php manual
#https://www.php.net/manual/en/timezones.php
#
#Licensed under the Apache License, Version 2.0 (the "License"); you may not
#use this file except in compliance with the License. You may obtain a copy of
#the License at
#
#    http://www.apache.org/licenses/LICENSE-2.0
#
#Unless required by applicable law or agreed to in writing, software
#distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
#WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
#License for the specific language governing permissions and limitations under
#the License.
#
timezone=$(date +"%Z")
case $timezone in
    ACDT) php_timezone="Australia/Adelaide";;
    ACST) php_timezone="Australia/Adelaide";;
    ADT) php_timezone="America/Halifax";;
    AEDT) php_timezone="Australia/Sydney";;
    AEST) php_timezone="Australia/Sydney";;
    AKDT) php_timezone="America/Anchorage";;
    AKST) php_timezone="America/Anchorage";;
    AST) php_timezone="America/Santo_Domingo";;
    AWST) php_timezone="Australia/Perth";;
    BST) php_timezone="Europe/London";;
    CAT) php_timezone="Africa/Khartoum";;
    CDT) php_timezone="America/Chicago";;
    CEST) php_timezone="Europe/Paris";;
    CET) php_timezone="Europe/Paris";;
    ChST) php_timezone="Pacific/Guam";;
    CST) php_timezone="America/Chicago";;
    EAT) php_timezone="Africa/Nairobi";;
    EDT) php_timezone="America/New_York";;
    EEST) php_timezone="Europe/Helsinki";;
    EET) php_timezone="Europe/Helsinki";;
    EST) php_timezone="America/New_York";;
    GMT) php_timezone="Europe/London";;
    HKT) php_timezone="Asia/Hong_Kong";;
    HST) php_timezone="Pacific/Honolulu";;
    IDT) php_timezone="Asia/Jerusalem";;
    IST) php_timezone="Europe/Dublin";;
    JST) php_timezone="Asia/Tokyo";;
    KST) php_timezone="Asia/Seoul";;
    MDT) php_timezone="America/Denver";;
    MSK) php_timezone="Europe/Moscow";;
    MST) php_timezone="America/Denver";;
    NDT) php_timezone="America/St_Johns";;
    NST) php_timezone="America/St_Johns";;
    NZDT) php_timezone="Pacific/Auckland";;
    NZST) php_timezone="Pacific/Auckland";;
    PDT) php_timezone="America/Los_Angeles";;
    PKT) php_timezone="Asia/Karachi";;
    PST) php_timezone="America/Los_Angeles";;
    SAST) php_timezone="Africa/Johannesburg";;
    SST) php_timezone="Pacific/Pago_Pago";;
    WAT) php_timezone="Africa/Lagos";;
    WEST) php_timezone="Europe/Lisbon";;
    WET) php_timezone="Europe/Lisbon";;
    WIB) php_timezone="Asia/Jakarta";;
    WIT) php_timezone="Asia/Jayapura";;
    WITA) php_timezone="Asia/Makassar";;
    *)
        echo "ERROR: timezone not found, please update manually"
        exit 1;;
esac
sed -i "/date\.timezone \=/c\date\.timezone \= \"${php_timezone}\"" /etc/php.ini
service php-fpm restart
exit 0
