
## Work In Progress

Notes about scripts which should be included in the discussion below:

boost-tasks				X=addressed in this doc
- mirror				so far this looks like a peripheral admin task
- release-from-bintray			X (manual)
- update-doc-list		
- update-explicit-failures		X (cron)
- update-pull-request-report		X (cron)
- update-super-project			X (cron)
- update-website-documentation		X (cron)
- upload-inspect-report
- upload-this-to-server

site-tools
- git-prep-beta.sh
- list-test-compilers.php
- load-release.data.php
- new-libraries.php
- refresh-pages.php
- scan-documentation.php
- set-release-status.php
- update-doc-list.php
- update-pages.php
- update-repo.php

re-review each item when ready.

## Installation, Site Maintenance, and Releases 

The steps to install a copy of the boost.org website are covered in:
- https://boost.org/development/website_updating.html 
- https://github.com/CPPAlliance/boost-tasks/blob/master/tests/dockers/ubuntu-18.04/server/Dockerfile

Conceptually there are two machines involved in managing the site, which are the "workstation", and the "web server". 

When making updates to the site it's possible to make the changes on the developer's workstation, check them in, and then deploy the changes into production on the web server. 

However, the current status quo for many actions may be that they occur entirely on the web server rather than on the workstation. The split of responsibilities should be documented.

### crontabs

A listing of cron jobs that should be set up.
These are only examples, the time and file path have not yet been correlated with production.

Either Workstation or Server crontabs:
0 1 * * * /opt/github/boost-tasks/update-explicit-failures --cron
15 * * * * /opt/github/boost-tasks/update-super-project --cron

Server crontabs:
0 2 * * * /opt/github/boost-tasks/update-pull-request-report --cron
0 3 * * * /opt/github/boost-tasks/update-website-documentation --cron

Workstation crontabs:

Questions:
- what are the actual times these crons are run?
- on the server itself or elsewhere?
- is there an "official" workstation that runs boost-tasks or is everything run on the webserver itself?

### Release a new version of boost

- Run release-from-bintray
- manually check it in.
- if release-from-bintray had been run from a workstation, make sure the new bintray has also been downloaded to the server as well.
- check out the website to /home/www/live.boost.org




