# pwbox
A password manager in development.

Installation
---

    apt-get install gpgv haveged git apache2 libapache2-mod-php5 rng-tools
    apt-get install --no-install-recommends expect

    cd /
    git clone https://github.com/allanrbo/pwbox.git
    cd pwbox
    chown www-data -R data
    cp config.template.json config.json
