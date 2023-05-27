#!/bin/bash
#curl node_available api
fqdn=$(cat /usr/share/nginx/html/config.php |grep fqdn|cut -d "'" -f2)
port=$(cat /usr/share/nginx/html/config.php |grep port|cut -d "'" -f2)
curl -v https://$fqdn:$port/api/v2/node_available
