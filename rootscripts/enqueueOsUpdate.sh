#!/bin/bash


logfile=$(date '+%Y-%m-%dT%H%M%S-')
logfile+=$(uuidgen | cut -d - -f 1)
logfile+=.log
touch /pwbox/data/upgradeoutput/$logfile

# Spin this off to a separate process using the at command, in order to keep running even if parent Apache goes down for upgrade
echo "
    (
        echo Running apt-get dist-upgrade.
        DEBIAN_FRONTEND=noninteractive /usr/bin/apt-get --option Dpkg::Options::=--force-confnew --force-yes --fix-broken --show-upgraded --assume-yes dist-upgrade 2>&1
        echo
        echo Running apt-get autoremove.
        DEBIAN_FRONTEND=noninteractive /usr/bin/apt-get --assume-yes autoremove 2>&1
        echo
        echo OS upgrade complete.
    ) > /pwbox/data/upgradeoutput/$logfile
" | at now

echo $logfile
