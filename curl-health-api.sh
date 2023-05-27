#!/bin/bash
#curl health api
user=$(cat /usr/share/nginx/html/config.php|grep user|cut -d "'" -f2)
key=$(cat /usr/share/nginx/html/config.php|grep key|cut -d "'" -f2)
curl https://$user:$key@$fqdn:$port/api/v2/health
