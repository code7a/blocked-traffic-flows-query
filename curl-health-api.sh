#!/bin/bash
#curl health api
fqdn=$(cat /usr/share/nginx/html/config.php|grep fqdn|cut -d "'" -f2)
port=$(cat /usr/share/nginx/html/config.php|grep port|cut -d "'" -f2)
user=$(cat /usr/share/nginx/html/config.php|grep user|cut -d "'" -f2)
key=$(cat /usr/share/nginx/html/config.php|grep key|cut -d "'" -f2)
curl https://$user:$key@$fqdn:$port/api/v2/health
