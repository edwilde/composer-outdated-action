FROM composer

LABEL "repository"="http://github.com/edwilde/composer-outdated-action"
LABEL "homepage"="http://github.com/edwilde"
LABEL "maintainer"="Ed Wilde <github.action@edwilde.com>"
LABEL "description"="Looks for outdated composer dependencies and generates a markdown report for use in a pull request."

COPY composer.json composer.lock /action/
RUN composer install --no-dev --no-interaction --no-progress --working-dir=/action

COPY src /action/src
COPY bin /action/bin
RUN composer dump-autoload --no-dev --optimize --working-dir=/action

COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
