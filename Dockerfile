FROM composer

LABEL "repository"="http://github.com/edwilde/composer-outdated-action"
LABEL "homepage"="http://github.com/edwilde"
LABEL "maintainer"="Ed Wilde <github.action@edwilde.com>"
LABEL "description"="Looks for outdated composer dependencies and generates a markdown report for use in a pull request."

# Add jq for JSON parsing
RUN apk add --no-cache jq

COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
