# Terminus (Pantheon CLI) in Docker, because PHP isn't installed on the host.
#   docker build -t mittipure-terminus scripts/pantheon
FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends git openssh-client rsync default-mysql-client unzip curl ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && curl -fsSL -o /usr/local/bin/terminus https://github.com/pantheon-systems/terminus/releases/download/4.0.1/terminus.phar \
 && chmod +x /usr/local/bin/terminus
WORKDIR /work
ENTRYPOINT ["terminus"]
