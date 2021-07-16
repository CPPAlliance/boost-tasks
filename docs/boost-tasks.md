## boost-tasks

This repository contains nine executable scripts for various boost maintenance tasks:

- mirror  
- release-from-bintray  
- update-doc-list  
- update-explicit-failures  
- update-pull-request-report  		
- update-super-project  
- update-website-documentation  
- upload-inspect-report  
- upload-this-to-server  
  
Further information about each one is provided below.

## mirror

"Creates or updates the GitHub mirror".

Use the --help flag to see all options.

```
./mirror --help
```

This script will clone all the boostorg repositories, and place bare repos in var/data/mirror/boostorg.  Approximately 168 repositories, and growing.

It does this by first hitting https://api.github.com/orgs/boostorg/repos to get a list of all repos. A complexity in this process is that the results are paginated. The http headers from api.github.com (which can be viewed using wget -S, or curl) include a "link" header that points to the next page of results. The script iterates through the pages based on the "link" headers and dumps the info into a local file called var/data/cache.db,  an SQLite database.  Next, it clones all the repos. Use --all to re-download everything.

------------------------------------------------------------------------------------------------------------------------------------

## release-from-bintray

"Downloads the release details from bintray."

Use the --help flag to see all options.

```
./release-from-bintray --help
```

This script downloads a release from bintray. Installs it locally. It also updates the local var/ copy of the website in many ways, using the site-tools scripts.
- site-tools/update-doc-list.php
- site-tools/set-release-status.php
- site-tools/update-pages.php

Detailed Steps:
- Clones boostorg/website into var/data/repos/website.  A shallow clone.
- Consults api.bintray.com for some metadata about the releases. Then, downloads the snapshot to a location such as the following:
```
var/data/bintray/release/2017-04-19T17\:57/51421ef259a4530edea0fbfc448460fcc5c64edb/boost_1_64_0.tar.bz2
```

Unzips the requested archive (such as 1.64.0) into the local folder /home/www/shared/archives/test/boost_1_64_0 . The location (which is /home/www/shared/archives/live in production and /home/www/shared/archives/test in the testing dockerfiles) is a configured location in var/config.neon:  

```
website-archives: /home/www/shared/archives/test
```
- Update documentation list. This is a passthru to website/site-tools: php {$website_repo->path}/site-tools/update-doc-list.php --quiet {$install_path} '{$version_object}'\n";

It does not commit or push to github.

- Update release data. This create {$website\_repo->path}/generated/state/release.txt.  Then, it runs the following
```
$releases->addDocumentation('boost', $version_object, "/doc/libs/{$short_doc_dir}/");
```
(which does not literally change /doc/libs/{$short_doc_dir}, so at the moment this is unclear.)

- Set release in website. This is a passthru to website/site-tools: php {$website\_repo->path}/site-tools/set-release-status.php --quiet {$install_path} '{$version_object}'\n";

It does not commit or push to github.

- Rebuild website pages. This is a passthru to website/site-tools: passthru("php {$website_repo->path}/site-tools/update-pages.php", $status);

Note: Consult https://www.boost.org/development/website_updating.html

It appears that release-from-bintray is making all the changes to update the main website for a new release. This is based on the fact that it's running many website/site-tools scripts. These update the copy of the website residing in var/data/repos/website.

However, it doesn't commit, push to github, or otherwise deploy.  The final steps would be manual.

------------------------------------------------------------------------------------------------------------------------------------

## update-doc-list

"Update the documentation list"

Use the --help flag to see all options.

```
./update-doc-list --help
```

This script runs a number of different scripts from website/site-tools to update the website and the superproject, and then check them in. 
- site-tools/update-pages.php
- site-tools/update-doc-list.php
- site-tools/update-repo.php

More analysis and documentation of those scripts can be found in site-tools.md in the current directory.

Detailed Steps:
- Create database at var/data/cache.db
- Download "events" from https://api.github.com/orgs/boostorg/events and store in the database.
- Fetch list of repos and from https://api.github.com/orgs/boostorg/repos and store in the database.
- Mirror all repositories in var/data/mirror/boostorg, as bare repos.
- Clone master branch of website in var/data/repos/website
- Update in progress release notes. This is a passthru to the website's site-tools: "{$website_repo->path}/site-tools/update-pages.php ". "--in-progress-only", $status);   It is uses "attemptAndPush", so it should commit and push the website to github.com if "push-to-repo: true" is set in var/config.neon
- Update documentation list. This is a passthru to the website's site-tools: passthru('php '. "{$website_repo->path}/site-tools/update-doc-list.php ". "--quiet {$mirror->mirror\_root}/boostorg/boost.git {$version}", $status); It is uses "attemptAndPush", so it should commit and push the website to github.com if "push-to-repo: true" is set in var/config.neon
- Clone boost, branch develop, in var/data/super/develop
- Clone boost, branch master, in var/data/super/master
- Update maintainer list for develop. This is a passthru to the website's site-tools: passthru('php '. "{$website_repo->path}/site-tools/update-repo.php ". "{$super->path} {$super->branch}", $status);  It uses "attemptAndPush", so it should commit and push the superproject to github.com if "push-to-repo: true" is set in var/config.neon
- Update maintainer list for master, like previous step.  
- The method "attemptAndPush" will return False if push isn't enabled, causing the script to fail if push isn't enabled. Adjust var/config.neon.  

------------------------------------------------------------------------------------------------------------------------------------

## update-explicit-failures

Use the --help flag to see all options.

```
./update-explicit-failures --help
```

"Update explicit failure markup in the super project"

In the super project there's a file call explicit-failures-markup.xml at:
```
https://github.com/boostorg/boost/blob/master/status/explicit-failures-markup.xml
```
A number of boost submodules also have the file in meta/explicit-failures-markup.xml

This script will update the superproject's version of explicit-failures-markup.xml based on the modules' files.

More details:

- Retrieves "events" from this url:
```
https://api.github.com/orgs/boostorg/events
```
and stores them in the local cache var/data/cache.db

- Downloads and updates the mirror. Refer to the mirror script above.
- Downloads a copy of the super project (https://github.com/boostorg/boost) and places copies in var/data/super/develop and master.
- Parses all the modules, and updates explicit-failures-markup.xml in the superproject.
- If "push-to-repo: true" is set in var/config.neon, then push the changes to github.com

------------------------------------------------------------------------------------------------------------------------------------

## update-pull-request-report

"Update the pull request report from GitHub."

Use the --help flag to see all options.

```
./update-pull-request-report --help
```

This script will consult api.github.com and grab the pull request information from each and every repository. For example:
```
https://api.github.com/repos/boostorg/beast/pulls
```

All the data is stored locally in the cache, which is an SQLite database at var/data/cache.db.

Then, the report is written locally to a json file at /home/www/shared/data/pull-requests.json (or based on the location configured in var/config.neon for website-data).

```
website-data: /home/www/shared/data
```

Because the output is a file in /home/www, it appears the script is typically run on the web server to generate pull-requests.json which is then served by the web server. The script does not upload pull-requests.json elsewhere or check pull-requests.json into a repository.

------------------------------------------------------------------------------------------------------------------------------------

## update-super-project

"Update the submodules in the super project"

Use the --help flag to see all options.

```
./update-super-project --help
```

This script will contact api.github.com and retrieve information about the latest commits from each boost sub-project or sub-module (of which there are more than a hundred).  Here is an example from the "wave" module:

```
https://api.github.com/repos/boostorg/wave/git/refs/heads/develop
https://api.github.com/repos/boostorg/wave/git/refs/heads/master
```

The script also retrieves "events":
```
https://api.github.com/orgs/boostorg/events
```

The latest submodule updates may be determined only from events, or it may download all submodule info if it deems that necessary, or if the --all flag is set.

Copies of the main superproject called boost are cloned locally here:
```
var/data/super/develop
var/data/super/master
```

A new commit is generated in var/data/super/develop containing the new subproject info. Such commits are stored internally in the .git directory, rather than modifying any visible files.

```
Subproject commit a8cf003d
```

Next, if your config.neon has "push-to-repo: true" then the new commit is also pushed to git@github.com:boostorg/boost.git

------------------------------------------------------------------------------------------------------------------------------------

## update-website-documentation

"Update the website development documentation from BinTray."

Use the --help flag to see all options.

```
./update-website-documentation --help
```

This script runs on the boost.org web server to download the .tar.bz2 bundles from BinTray, unzip them locally, and from there they can be served by the web server.

"Website" may refer to different things. In this particular case, the "website documentation" is the documentation of the many boost libraries and all content of the superproject which has previously been bundled into a large bz2 file and stored on bintray. By "development documentation" it refers to the bundles generated from the "develop" and "master" branches rather than from an official release.

The script consults api.bintray.com to finds the master and development snapshots from dl.bintray.com. For example:
http://dl.bintray.com/boostorg/master/boost_1_74_0-snapshot.tar.bz2
and
http://dl.bintray.com/boostorg/develop/boost_1_74_0-snapshot.tar.bz2

Downloads the snapshots to
var/data/bintray/develop/ and master/

Unzips them to:
  
/home/www/shared/archives/live/master  
and  
/home/www/shared/archives/live/develop  
  
Adds .bintray-version # This contains the latest commit hash of master or develop respectively  
Removes doc/html/bbv2.html # Because the file has a redirect to "master", which isn't permitted  
Removes doc/html/signals.html # Because the file has a redirect to "master", which isn't permitted  

On the live site it appears the path to the unzipped files is:  
```
/home/www/shared/archives/live/master/_file_.html
```

In local testing the path has been set in var/config.neon to:
```
website-archives: /home/www/shared/archives/test
```

var/config.neon could be set to "mirror" what is happening on the web server like this:  
```
website-archives: /home/www/shared/archives/live
```

------------------------------------------------------------------------------------------------------------------------------------

## upload-inspect-report

"Run inspect on the latest cached downloads from bintray."

Use the --help flag to see all options.

```
./upload-inspect-report --help
```

Builds and runs the "inspect" tool on the boost documentation bundle from bintray.

A prerequisite of upload-inspect-report is to run update-website-documentation which downloads the snapshot bundles into var/data/bintray/develop/ and master/ where inspect will find them. If those bundles are not there, nothing will happen.

During execution, the script builds and executes the "inspect" executable in a temporary directory such as boost-tasks/var/data/inspect/download0yVtTW/build-inspect/new/boost_1_74_0/dist/bin/inspect, which will not be visible after the script has completed. 

The final output files are:  

var/data/inspect/develop-sha1.txt  
var/data/inspect/docs-inspect-develop.html  
var/data/inspect/docs-inspect-master.html  
var/data/inspect/master-sha1.txt  
var/data/inspect/upload/docs-inspect-develop.html  
var/data/inspect/upload/docs-inspect-master.html  
  
The final step is to upload the files via ftp to boost.cowic.de. This depends on having cowic-username and cowic-password set in the var/config.neon. Otherwise the upload will not proceed.

------------------------------------------------------------------------------------------------------------------------------------

## upload-this-to-server

Use the --help flag to see all options.

```
./upload-this-to-server --help
```

Uploads the latest copy of boost-tasks (the local copy containing these very scripts under discussion) to boost.org. It also makes a git commit to the "upload" branch of this repository, and pushes it to github, as a record of the event.

Note: The upload branch does not appear to deviate from the master branch in terms of content, only in revision history which includes many merges.

Something similar, although not identical, could be done from the boost.org server itself:
```
git clone https://github.com/CPPAlliance/boost-tasks
```

More details:

- Creates a git "archive" of the current working repo, and dumps it locally to var/data/upload.
- It rsyncs that folder to boost.org. So, boost.org will have a copy of boost-tasks.
```
rsync --exclude var --exclude vendor -azv --delete-after {$dst}/ dnljms@boost.org:boost-tasks/")
```
- Commit the latest commit to the upload branch
```
commit-tree -m Upload -p origin/upload -p {$hash} {$tree_hash}"
```
- Push the upload branch to github.com.
```
push origin {$commit_hash}:upload"
```

