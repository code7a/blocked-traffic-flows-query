#!/bin/bash
#update php config
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
if [ ! -f /usr/share/nginx/html/config.php ]; then
    echo -e "\nUpdate config.php..."
    read -p "Enter PCE domain name: " fqdn
    read -p "Enter PCE port: " port
    read -p "Enter PCE org: " org
    read -p "Enter PCE API username: " user
    echo -n "Enter PCE API secret: " && read -s key
    echo "<?php
\$fqdn='$fqdn';
\$port='$port';
\$org='$org';
\$user='$user';
\$key='$key';
?>" > /usr/share/nginx/html/config.php
    echo -e "\n\nUpdated php config with provided values."
fi
exit 0
