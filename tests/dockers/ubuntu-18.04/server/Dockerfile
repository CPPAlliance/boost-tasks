FROM ubuntu:18.04

RUN apt-get update
RUN DEBIAN_FRONTEND="noninteractive" apt-get -y install tzdata
RUN apt-get install -y php7.2 php-xml php7.2-mysql php7.2-sqlite php7.2-curl rsync net-tools htop libxml2-utils apache2 curl wget \
    iputils-ping jq vim tree ssh unzip git mlocate nload python3 build-essential

COPY boost.org.ssl.conf /etc/apache2/sites-available/
COPY beta.boost.org.ssl.conf /etc/apache2/sites-available/
RUN a2ensite boost.org.ssl.conf && \
    a2ensite beta.boost.org.ssl.conf && \
    a2dissite 000-default.conf && \
    a2dissite default-ssl.conf && \
    a2enmod headers env cgi alias rewrite ssl include && \
    openssl rand -out /root/.rnd -hex 256 && \
    mkdir -p /root/certs
# RUN a2dismod deflate
WORKDIR /root/certs
RUN openssl req -nodes -new -x509 -keyout myCA.key -out myCA.crt -subj "/C=US/ST=New York/L=Brooklyn/O=Example Company/CN=myCA.com" -days 3650 && \
    cp myCA.crt /usr/local/share/ca-certificates/ && \
    update-ca-certificates

ARG DOMAIN=boost.org
RUN openssl req -newkey rsa:2048 -nodes -keyout $DOMAIN.key -out $DOMAIN.csr -subj "/C=US/ST=New York/L=Brooklyn/O=Example Company/CN=$DOMAIN" && \
    openssl x509 -req -days 3650 -in $DOMAIN.csr -CA myCA.crt -CAkey myCA.key -CAcreateserial -out $DOMAIN.pem -sha256 && \
    cp $DOMAIN.pem /etc/ssl/certs/ && \
    cp $DOMAIN.key /etc/ssl/private/ 

ARG DOMAIN=beta.boost.org
RUN openssl req -newkey rsa:2048 -nodes -keyout $DOMAIN.key -out $DOMAIN.csr -subj "/C=US/ST=New York/L=Brooklyn/O=Example Company/CN=$DOMAIN" && \
    openssl x509 -req -days 3650 -in $DOMAIN.csr -CA myCA.crt -CAkey myCA.key -CAcreateserial -out $DOMAIN.pem -sha256 && \
    cp $DOMAIN.pem /etc/ssl/certs/ && \
    cp $DOMAIN.key /etc/ssl/private/ 

RUN useradd -m dnljms
RUN useradd -m www
RUN mkdir /root/.ssh
WORKDIR /root/.ssh
RUN ssh-keygen -t rsa -N "" -f id_rsa && \
    mkdir /home/dnljms/.ssh && \
    cp id_rsa /home/dnljms/.ssh/ && \
    cp id_rsa.pub /home/dnljms/.ssh/ && \
    cp id_rsa.pub /home/dnljms/.ssh/authorized_keys && \
    chown -R dnljms:dnljms /home/dnljms && \
    mkdir /home/www/.ssh && \
    cp id_rsa /home/www/.ssh/ && \
    cp id_rsa.pub /home/www/.ssh/ && \
    cp id_rsa.pub /home/www/.ssh/authorized_keys && \
    mkdir -p /home/www/shared/data && \
    mkdir -p /home/www/shared/repos && \
    mkdir -p /home/www/shared/archives/live && \
    chown -R www:www /home/www 

# Website clone had been here, let's move it lower to reduce docker rebuilds.

# The website branch is no longer present in boostorg/build
# RUN cd /home/www/live.boost.org/ && git clone -b website https://github.com/boostorg/build && \
#     cd /home/www/beta.boost.org/ && git clone -b website https://github.com/boostorg/build

RUN cd /home/www/shared && echo "<?php\ndefine('BOOST_WEBSITE_SHARED_DIR', '/home/www/shared');\n?>" > config.php
# define('STATIC_DIR', '/home/www/shared/archives/live'); is already a default found in common/code/boost_config.php
# and therefore not required.

# Get boost-tasks
RUN cd /home/www/shared/ && \
    git clone https://github.com/CPPAlliance/boost-tasks tasks

# Download archives
RUN cd /home/www/shared/archives/live/ && \
    wget http://dl.bintray.com/boostorg/release/1.75.0/source/boost_1_75_0.tar.gz && \
    tar -xvf boost_1_75_0.tar.gz

COPY prepare.sh /tmp/

# composer
RUN apt-get install -y composer
WORKDIR /home/www/shared/tasks
RUN composer install

# download main website code
RUN cd /home/www/ && git clone https://github.com/samd2/website live.boost.org && \
    git clone -b beta https://github.com/samd2/website beta.boost.org

EXPOSE 80
EXPOSE 443

# LAST SECTION --------------------
#WORKDIR /opt/github/CPPAlliance/boost-tasks
CMD service apache2 start && sleep 1000000000
