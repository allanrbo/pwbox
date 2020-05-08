# pwbox
A password manager in development.


Installation
---
    # before boot, edit files in fat boot partition from host
    Create a blank file called "ssh"

    wpa_supplicant.conf
        ctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev
        update_config=1
        country=US

        network={
            ssid="somewifi1"
            psk="abc"
            key_mgmt=WPA-PSK
        }

        network={
            ssid="somewifi12"
            psk="abc"
            key_mgmt=WPA-PSK
        }



    # Now boot up the raspberry pi and login (ssh should be enabled at this point)
    sudo su

    apt-get update
    apt-get dist-upgrade
    apt-get install --no-install-recommends apache2 libapache2-mod-php gnupg1 expect haveged rng-tools uuid-runtime at
    apt-get install --no-install-recommends git

    # to disable bluetooth
    apt-get purge bluez -y ; apt-get autoremove -y

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


    nano /etc/ssl/private/renewcerts.sh
        #!/bin/bash

        function renewcert() {
            if [ "$#" -ne 3 ]; then
                echo "wrong arg count"
                return 1
            fi

            wwwroot=$1
            certfilebasename=$2
            domains=$3

            # Download acme_tiny.py or abort
            if [ ! -f /etc/ssl/private/acme_tiny.py ]; then
                wget -q -O /tmp/acme_tiny.py https://raw.githubusercontent.com/diafygi/acme-tiny/master/acme_tiny.py
                if [ $? -ne 0 ]; then
                    echo "failed to download acme_tiny.py"
                    return 1
                fi
                mv /tmp/acme_tiny.py /etc/ssl/private/acme_tiny.py
                chmod +x /etc/ssl/private/acme_tiny.py
                echo "created /etc/ssl/private/acme_tiny.py"
            fi

            # Create a Let's Encrypt account key if not already created
            if [ ! -f /etc/ssl/private/letsencryptaccount.key ]; then
                openssl genrsa 4096 > /etc/ssl/private/letsencryptaccount.key
                if [ $? -ne 0 ]; then
                    echo "failed to generate letsencryptaccount.key"
                    return 1
                fi
                echo "created /etc/ssl/private/letsencryptaccount.key"
            fi

            # Create a private key for this cert if not already created
            if [ ! -f /etc/ssl/private/$certfilebasename.key ]; then
                openssl genrsa -out /etc/ssl/private/$certfilebasename.key 2048
                if [ $? -ne 0 ]; then
                    echo "failed to generate $certfilebasename.key"
                    return 1
                fi
                echo "created /etc/ssl/private/$certfilebasename.key"
            fi

            # Create a certificate request file (a .csr file)
            IFS=', ' read -r -a domainsArray <<< "$domains"
            maindomain="${domainsArray[0]}"
            if [[ "${#domainsArray[@]}" == "1" ]]; then
                # Create a simple certificate request for just a single domain
                openssl req -new -key /etc/ssl/private/$certfilebasename.key -subj "/CN=$maindomain" -out /etc/ssl/private/$certfilebasename.csr 2> /tmp/opensslstderr.txt
                if [ $? -ne 0 ]; then
                    >&2 cat /tmp/opensslstderr.txt
                    echo "failed to generate $certfilebasename.csr"
                    rm /tmp/opensslstderr.txt
                    return 1
                fi
                rm /tmp/opensslstderr.txt
            else
                # Create a special certificate request for multiple domains
                cat /etc/ssl/openssl.cnf > /tmp/csrconfig
                echo "[SAN]" >> /tmp/csrconfig
                subjectAltNameLine="subjectAltName=DNS:$maindomain"
                for domain in "${domainsArray[@]:1}"; do
                    subjectAltNameLine="$subjectAltNameLine,DNS:$domain"
                done
                echo $subjectAltNameLine >> /tmp/csrconfig

                openssl req -new -sha256 -key /etc/ssl/private/$certfilebasename.key -subj "/CN=$maindomain" -reqexts SAN -config /tmp/csrconfig -out /etc/ssl/private/$certfilebasename.csr 2> /tmp/opensslstderr.txt
                if [ $? -ne 0 ]; then
                    >&2 cat /tmp/opensslstderr.txt
                    echo "failed to generate $certfilebasename.csr"
                    rm /tmp/opensslstderr.txt
                    rm /tmp/csrconfig
                    return 1
                fi
                rm /tmp/opensslstderr.txt
                rm /tmp/csrconfig
            fi
            echo "created /etc/ssl/private/$certfilebasename.csr"

            # Create a temporary self signed certificate if there doesn't already exist a certificate
            if [ ! -f /etc/ssl/certs/$certfilebasename.crt ]; then
                openssl req -new -key /etc/ssl/private/$certfilebasename.key -subj "/CN=$maindomain" -x509 -days 3650 -out  /etc/ssl/certs/$certfilebasename.crt 2> /tmp/opensslstderr.txt
                if [ $? -ne 0 ]; then
                    >&2 cat /tmp/opensslstderr.txt
                    echo "failed to generate temporary self signed $certfilebasename.crt"
                    rm /tmp/opensslstderr.txt
                    return 1
                fi
                echo "created a temporary self signed /etc/ssl/certs/$certfilebasename.crt"
            fi

            for domain in "${domainsArray[@]}"; do
                wget --max-redirect 0 -q -O - http://$domain > /dev/null
                r=$?
                if [ $r -ne 0 ] && [ $r -ne 8 ]; then
                    echo "skipping actual signing, as there was no http response from http://$domain"
                    # Not considering this an error condition, as this is a useful behaviour for bootstrapping a web server whose config may already be pointing to crt files
                    return 0
                fi
            done

            # Use acme_tiny.py to request the cert from Let's Encrypt
            mkdir -p $wwwroot/.well-known/acme-challenge/
            python /etc/ssl/private/acme_tiny.py \
                --account-key /etc/ssl/private/letsencryptaccount.key \
                --csr /etc/ssl/private/$certfilebasename.csr \
                --acme-dir $wwwroot/.well-known/acme-challenge/ \
                > /tmp/$certfilebasename.crt
            if [ $? -ne 0 ]; then
                echo "acme_tiny.py failed for $certfilebasename"
                echo "did not touch existing /etc/ssl/certs/$certfilebasename.crt (if it exists)"
                rm /tmp/$certfilebasename.crt
                return 1
            fi
            mv /tmp/$certfilebasename.crt /etc/ssl/certs/$certfilebasename.crt
            rm -fr $wwwroot/.well-known/
            echo "created /etc/ssl/certs/$certfilebasename.crt"
        }

        renewcert /var/www/html pwbox pwbox1.example.com
        /etc/init.d/apache2 reload

    chmod +x /etc/ssl/private/renewcerts.sh
    /etc/ssl/private/renewcerts.sh
    crontab -e
      16 05 5 * * /etc/ssl/private/renewcerts.sh

    rm /var/www/html/*

    a2dissite 000-default
    a2enmod ssl rewrite headers
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
            SSLCertificateFile /etc/ssl/certs/pwbox.crt
            SSLCertificateKeyFile /etc/ssl/private/pwbox.key
            Header always set Strict-Transport-Security "max-age=63072000; includeSubdomains;"
        </VirtualHost>

        <VirtualHost _default_:80>
            DocumentRoot /var/www/html/
            RewriteEngine On
            RewriteCond %{THE_REQUEST} !/.well-known/ [NC]
            RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=302]
        </VirtualHost>


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
    curl --insecure -X POST -d '{"name": "Administrators", "members": [ "user123" ] }' https://localhost/api/group

    # revert the changes to api/index.php
    git checkout api/index.php

    # Lock down all ports except 80, 443
    nano /etc/rc.local
        #!/bin/bash
        iptables --flush INPUT
        iptables -A INPUT -p tcp --dport 80 -j ACCEPT
        iptables -A INPUT -p tcp --dport 443 -j ACCEPT
        iptables -A INPUT -p icmp --icmp-type 8 -j ACCEPT
        iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
        iptables -A INPUT -j DROP

    /etc/rc.local


    ssh-keygen -f ~/.ssh/id_rsa_pwboxtunnel -P ""
    cat ~/.ssh/id_rsa_pwboxtunnel.pub

    # test the ssh connection before proceeding
    ssh -i /home/pi/.ssh/id_rsa_pwboxtunnel -p 59563 root@pwbox1.acoby.com


    # ON THE TUNNEL SERVER
        /etc/ssh/sshd_config
            Port 59563
            GatewayPorts yes

        service ssh restart

        # Lock down all ports except 80, 443
        nano /etc/rc.local
            #!/bin/bash
            iptables --flush INPUT
            iptables -A INPUT -p tcp --dport 80 -j ACCEPT
            iptables -A INPUT -p tcp --dport 443 -j ACCEPT
            iptables -A INPUT -p icmp --icmp-type 8 -j ACCEPT
            iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
            iptables -A INPUT -j DROP
        chmod +x /etc/rc.local
        /etc/rc.local

    # BACK ON THE RASPBERRY PI

    nano /home/pi/tunnelout.sh
        #!/bin/bash

        startssh() {

          # Kill existing sessions, as they might be occupying the port
          ssh -i /home/pi/.ssh/id_rsa_pwboxtunnel -p 59563 \
            root@pwbox1.acoby.com \
            "ps aux | grep -v grep | grep 'sshd: root' | awk {'print \$2'} | xargs kill"

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

    /etc/ssl/private/renewcerts.sh

