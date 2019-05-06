# pwbox
A password manager in development.

Installation
---
    sudo raspi-config
    # enable ssh

    sudo su

    apt-get update
    apt-get dist-upgrade
    apt-get install --no-install-recommends apache2 libapache2-mod-php gnupg1 expect haveged rng-tools uuid-runtime at
    apt-get install --no-install-recommends git

    cd /
    git clone https://github.com/allanrbo/pwbox.git
    cd pwbox
    chown www-data:www-data -R data
    cp config.template.json config.json

    mkdir -p /root/pwboxscripts
    cp /pwbox/rootscripts/* /root/pwboxscripts/
    chmod 700 /root/pwboxscripts/
    chmod 700 /root/pwboxscripts/*

    cp /pwbox/etc/sudoers.d/pwbox /etc/sudoers.d/pwbox
    chmod 0440 /etc/sudoers.d/pwbox

    cd /etc/ssl/private
    openssl genrsa -out pwbox.key 2048
    chmod 600 /etc/ssl/private/pwbox.key
    openssl req -new -key pwbox.key -subj "/CN=pwbox" -x509 -days 36500 -out pwbox.crt
    mv pwbox.crt /etc/ssl/certs

    a2dissite 000-default
    a2enmod ssl rewrite headers
    ln -s /pwbox/etc/apache2/sites-enabled/pwbox.conf /etc/apache2/sites-enabled/pwbox.conf
    service apache2 restart

    # Creating initial user and Administrators group (this will be less hacky in the future)
    cd /pwbox
    nano api/index.php
        if ($method == "POST" && $uri == "/user") {
            ######## COMMENT OUT THE FOLLOWING TWO LINES ########
            ...
            //$authInfo = extractTokenFromHeader();
            //requireAdminGroup($authInfo);
            ...
            //$data["modifiedBy"] = $authInfo["username"];

        if ($method == "POST" && $uri == "/group") {
            ######## COMMENT OUT THE FOLLOWING TWO LINES ########
            ...
            //$authInfo = extractTokenFromHeader();
            //requireAdminGroup($authInfo);
            ...
            //$data["modifiedBy"] = $authInfo["username"];

    curl --insecure -X POST -d '{"username": "user123", "password": "pass123"}' https://localhost/api/user   ; history -d $(history 1)
    curl --insecure -X POST -d '{"username": "user2", "password": "pass123"}' https://localhost/api/user   ; history -d $(history 1)
    curl --insecure -X POST -d '{"name": "Administrators", "members": [ "user123" ] }' https://localhost/api/group

    # revert the changes to api/index.php
    git checkout api/index.php

    # Lock down all ports except 80, 443
    nano /etc/rc.local
        #!/bin/bash
        iptables --flush INPUT
        iptables -A INPUT -p tcp --dport 443 -j ACCEPT
        iptables -A INPUT -p icmp --icmp-type 8 -j ACCEPT
        iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
        iptables -A INPUT -j DROP

    /etc/rc.local




    # Set up Let's Encrypt HTTPS
    cd /etc/ssl/private
    wget https://raw.githubusercontent.com/diafygi/acme-tiny/master/acme_tiny.py
    chmod +x acme_tiny.py
    openssl genrsa 4096 > letsencryptaccount.key

    openssl genrsa -out pwbox.key 2048
    openssl req -new -key pwbox.key -subj "/CN=pwbox1.acoby.com" -out pwbox.csr

    ssh-keygen -f ~/.ssh/id_rsa_pwboxtunnel -P ""
    cat ~/.ssh/id_rsa_pwboxtunnel.pub

    # test the ssh connection before proceeding
    ssh -i /home/pi/.ssh/id_rsa_pwboxtunnel -p 59563 root@pwbox1.acoby.com

    nano /home/pi/tunnelout.sh
        #!/bin/bash

        startssh() {
          ssh -i /home/pi/.ssh/id_rsa_pwboxtunnel -p 59563 -N -f \
            -o ServerAliveInterval=30 \
            -R 0.0.0.0:80:127.0.0.1:80 \
            -R 0.0.0.0:443:127.0.0.1:443 \
            root@pwbox1.acoby.com &
        }

        response=$(curl --connect-timeout 10 --max-time 15 --write-out %{http_code} --silent --output /dev/null https://pwbox1.acoby.com/)
        if [ $response -eq 200 ]
        then
          echo Site via tunnel reachable
        else
          echo Site via tunnel not reachable
          r=$(pgrep -f "ssh.*pwbox1\.acoby\.com" 2>&1)
          if [ $? -eq 0 ]
          then
            kill $r
          fi
          startssh
        fi


    chmod +x /home/pi/tunnelout.sh
    /home/pi/tunnelout.sh


    crontab -e
        */2 * * * * /home/pi/tunnelout.sh > /dev/null 2>&1

    rm /var/www/html/*

    nano /etc/apache2/sites-enabled/pwbox.conf
        ServerTokens ProductOnly
        ServerSignature Off

        ServerName pwbox
        <Directory /pwbox/www/>
        AllowOverride all
        Require all granted
        </Directory>

        <Directory /pwbox/api/>
        AllowOverride all
        Require all granted
        </Directory>

        <VirtualHost _default_:443>
        DocumentRoot /pwbox/www/
        Alias /api/ /pwbox/api/
        SSLEngine on
        SSLCertificateFile /etc/ssl/certs/pwbox+intermediate+root.pem
        SSLCertificateKeyFile /etc/ssl/private/pwbox.key
        Header always set Strict-Transport-Security "max-age=63072000; includeSubdomains;"
        </VirtualHost>

        <VirtualHost _default_:80>
        DocumentRoot /var/www/html/
        RewriteEngine On
        RewriteCond %{THE_REQUEST} !/.well-known/ [NC]
        RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=302]
        </VirtualHost>


    /etc/init.d/apache2 restart

    nano /etc/ssl/private/renewcerts.sh
      wget -q -O - https://letsencrypt.org/certs/lets-encrypt-x3-cross-signed.pem > /etc/ssl/certs/lets-encrypt-x3-cross-signed.pem

      function renewcert () {
        wwwroot=$1
        certfilebasename=$2

        mkdir -p $wwwroot/.well-known/acme-challenge/
        python /etc/ssl/private/acme_tiny.py \
          --account-key /etc/ssl/private/letsencryptaccount.key \
          --csr /etc/ssl/private/$certfilebasename.csr \
          --acme-dir $wwwroot/.well-known/acme-challenge/ \
          > /etc/ssl/certs/$certfilebasename.crt
        cat /etc/ssl/certs/$certfilebasename.crt /etc/ssl/certs/lets-encrypt-x3-cross-signed.pem > /etc/ssl/certs/$certfilebasename+intermediate.pem
        cat /etc/ssl/certs/$certfilebasename.crt /etc/ssl/certs/lets-encrypt-x3-cross-signed.pem > /etc/ssl/certs/$certfilebasename+intermediate+root.pem
        rm -fr $wwwroot/.well-known/
      }

      renewcert /var/www/html pwbox
      /etc/init.d/apache2 reload

    chmod +x /etc/ssl/private/renewcerts.sh
    /etc/ssl/private/renewcerts.sh

    crontab -e
      17 03 12 * * /etc/ssl/private/renewcerts.sh


    Set up of tunnel servers
        /etc/ssh/sshd_config
            Port 59563
            GatewayPorts yes

        service ssh restart

        # Lock down all ports except 80, 443
        nano /etc/rc.local
            #!/bin/bash
            iptables --flush INPUT
            iptables -A INPUT -p tcp --dport 443 -j ACCEPT
            iptables -A INPUT -p icmp --icmp-type 8 -j ACCEPT
            iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
            iptables -A INPUT -j DROP


