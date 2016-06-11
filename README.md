# pwbox
A password manager in development.

Installation
---

    apt-get install gpgv haveged git apache2 php5
    apt-get install --no-install-recommends expect

    cd /
    git clone https://github.com/allanrbo/pwbox.git
    cd pwbox
    chown www-data -R data
    cp config.template.json config.json
