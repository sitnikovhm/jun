#!/bin/bash
echo 'keep calm and drink coffee'
yum install -y git
yum install -y gcc
yum install -y pcre-devel
yum install -y openssl-devel
yum install -y perl-ExtUtils-Embed
yum install -y perl-Digest-MD5


mkdir -p /opt/data1/passport-scans /opt/archive /opt/nginx/php /opt/data1/passport-scans  /opt/data1/photos /opt/data1/signed-docs /opt/data1/nginx/ /opt/data1/nginx/logs  /opt/data1/nginx/php /opt/data1/nginx/src /opt/nginx/sbin /opt/nginx/ /opt/nginx/src /opt/data/passport-scans /opt/data/photos /opt/data/signed-docs

cd /opt/nginx/src
wget https://nginx.org/download/nginx-1.21.6.tar.gz
tar zxvf nginx-1.21.6.tar.gz
cd nginx-1.21.6
./configure --sbin-path=/usr/sbin --conf-path=/etc/nginx/nginx.conf --error-log-path=/opt/nginx/logs/error.log --http-log-path=/opt/nginx/logs/access.log --pid-path=/opt/nginx/nginx.pid --user=nginx --group=nginx --with-http_stub_status_module --with-http_ssl_module --with-http_dav_module --without-mail_pop3_module --without-mail_imap_module --with-http_perl_module --with-cc-opt='-O2 -g -pipe -Wall -Wp,-D_FORTIFY_SOURCE=2 -fexceptions -fstack-protector-strong --param=ssp-buffer-size=4 -grecord-gcc-switches -m64 -mtune=generic -fPIC' --with-ld-opt='-Wl,-z,relro -Wl,-z,now -pie'
if [ $? -eq 0 ]; then
	echo 'OK'
else
	echo 'FALSE'
fi

make && make install
if [ $? -eq 0 ]; then
	echo 'OK'
else
	echo 'FALSE'
fi

cd /tmp
git clone https://github.com/sitnikovhm/jun.git
cd jun
cp clear_old_scans.sh /opt/nginx/php
cp webdav-extensions.php /opt/nginx/sbin
cp -f nginx.conf /etc/nginx/
useradd -p 1 -U nginx
echo "nginx:1" | chpasswd
chown -R nginx:nginx /opt/data1
chown -R nginx:nginx /opt/archive
chown -R nginx:nginx /opt/nginx
chown -R nginx:nginx /etc/nginx/nginx.conf
nginx -c /etc/nginx/nginx.conf

