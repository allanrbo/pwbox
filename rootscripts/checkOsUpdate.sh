#!/bin/bash

echo
echo Running apt-get update.
/usr/bin/apt-get update 2>&1
echo
echo Running a simulated apt-get dist-upgrade to see if upgrades are available.
/usr/bin/apt-get --show-upgraded dist-upgrade --assume-no 2>&1
