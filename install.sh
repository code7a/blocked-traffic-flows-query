#!/bin/bash
#nginx php install script
#
#linux manual - date command
#https://linux.die.net/man/1/date
#
#php manual - supported timezones
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
#stop disable firewalld
service firewalld stop
systemctl disable firewalld

#update curl
echo '[CityFan]
name=City Fan Repo
baseurl=http://www.city-fan.org/ftp/contrib/yum-repo/rhel$releasever/$basearch/
enabled=1
gpgcheck=0' > /etc/yum.repos.d/city-fan.repo
yum update -y curl

#install and start services
yum install -y wget nginx php policycoreutils-devel

#increase default nginx timeout
sed -i 's/keepalive_timeout   65;/keepalive_timeout   1200;/g' /etc/nginx/nginx.conf

#update php.ini, display_errors = on
sed -i 's/display_errors = Off/display_errors = on/g' /etc/php.ini

#enable and start nginx
systemctl enable nginx
systemctl start nginx

BASEDIR=$(dirname $0)

#copy index.php to nginx directory
cp $BASEDIR/index.php /usr/share/nginx/html/
cp $BASEDIR/verbose.php /usr/share/nginx/html/
cp $BASEDIR/info.html /usr/share/nginx/html/
cp $BASEDIR/port_exclusions.csv /usr/share/nginx/html/
cp $BASEDIR/protocol_exclusions.csv /usr/share/nginx/html/

#configure config.php if does not exist
sudo $BASEDIR/update-php-config.sh

#curl to trigger selinux failure
curl --connect-timeout 5 --max-time 10 --silent localhost --data-raw 'form_ip=&form_port_number=&submit=Submit+Query' > /dev/null

#selinux, allow httpd
audit2allow --all
sleep 3
audit2allow --all --module-package=httpd-policy
sleep 3
semodule -i httpd-policy.pp

exit 0
