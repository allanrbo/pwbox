FROM debian:8
MAINTAINER Allan Boll <allan@acoby.com>

RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 libapache2-mod-php5 gnupg expect haveged rng-tools uuid-runtime at \
        git nano vim htop \
        && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
