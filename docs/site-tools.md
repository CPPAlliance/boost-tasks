
## site-tools

This documentation contains further details about  https://github.com/boostorg/website/site-tools

The following scripts are available:

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

---

## git-prep-beta.sh

Merges master branch into beta branch. Conducts checks during the process to ensure the process goes correctly.

Steps:
- check the tree is clean. No changes, otherwise exit.
- fetch from origin.
- fast-forward merge the local beta branch. Exit if there are errors.
- check if beta has any changes not already in master. Exit if so.
- merge master into beta.
- push beta to origin.
- checkout beta.

---

## list-test-compilers.php

"List the test compilers for a new release."

Processes the test result matrices from https://www.boost.org/development/tests/master/developer/summary.html which are generated externally from the main website.

Downloads a few hundred small files to site-tools/cache/

Outputs text information:
```
[section Compilers Tested]

Boost's primary test compilers are:
...
Boost's additional test compilers include:
...
```

This can then be copy and pasted into the release notes:  
feed/history/boost_1_72_0.qbk

and the information appears in many other files:  
users/history/version_1_72_0.html  
generated/dev.rss  
generated/state/page-cache.txt  
generated/state/beta_release_notes.txt  
generated/state/rss-items.txt  
generated/history.rss  
generated/news.rss  
generated/downloads.rss  

PHP7: yes

---

## load-release.data.php

"After uploading a release to sourceforge, add it to the release data using this script."

The "release data" being referred to, which is the target of this script, is a large json file containing information about many releases: generated/state/release.txt

generated/state/release.txt is currently being kept up-to-date and stored in the git repo.

The script may be run as follows:
```
php load-release.data.php releases/boost_1_64_0.txt
```

Where the format of the file boost_1_64_0.txt is  
```
https://dl.bintray.com/boostorg/release/1.64.0/source/
  
49c6abfeb5b480f6a86119c0d57235966b4690ee6ff9e6401ee868244808d155 boost_1_64_0.7z
7bcc5caace97baa948931d712ea5f37038dbb1c5d89b43ad4def4ed7cb683332 boost_1_64_0.tar.bz2
0445c22a5ef3bd69f5dfb48354978421a85ab395254a26b1ffb0aa1bfd63a108 boost_1_64_0.tar.gz
b99973c805f38b549dbeaf88701c0abeff8b0e8eaa4066df47cac10a32097523 boost_1_64_0.zip
```

This format is simpler than the destination generated/state/release.txt so the script facilitates data entry. A question remains as to how the source file itself such as boost_1_64_0.txt is generated. If the source file is retrieved from api.bintray.com, then there wll be no manual step to create a file such as boost_1_64_0.txt.

PHP7: yes

---

## new-libraries.php

"New library text for the release notes."

Discovers any new libraries and prints out text such as the following:

```
For release notes:

[section New Libraries]

* [phrase library..[@/libs/nowide/ Nowide]:]
  Standard library functions with UTF-8 API on Windows, from Artyom Beilis.
```

When creating release notes run this script to find out about new libraries, and then place the information in the release notes. No other files in the website repo are referencing new-libraries.php.

PHP7: yes

---

## refresh-pages.php

"Reconvert all the quickbook files and regenerate the html pages. Does not update the rss feeds or add new pages. Useful for when quickbook, the scripts or the templates have been updated."

This script at first appears to be quite minimal. It runs:

$site_tools->update_quickbook(true);

However, update_quickbook() is rather complex.  

See section below "Difference between refresh-pages.php and update-pages.php"

PHP7: yes

---

## scan-documentation.php

"After adding documentation to the server, run this script which should detect it and add the documentation to release data."

"This scans the documentation directory and add any documentation it finds to the release data. Only ever commit updates from running this on the server. Should do more here. This doesn't detect deleted directories, it also could be used in other parts of the site. Maybe this data should be stored with library list, or perhaps in a separate file somewhere. I could possibly also automatically update the documentation list from newly installed documentation."

So, what does this script do? It looks at the archives directory (such as /home/www/archives/live/) containing:  
/home/www/archives/live/boost_1_72_0   
/home/www/archives/live/boost_1_73_0  
etc.  

And from the directories contained there, it updates the "release data", which is a json file containing info about all releases at "website/generated/state/release.txt". It sets a documentation path in the json. Actually, a very small change.

```
    "boost-1.78.0": { 
        "documentation":
            "\/doc\/libs\/1_78_0\/",
```

Notice that path takes the format doc/libs/_version_.

PHP7: yes

---

## set-release-status.php

"Use this script to mark a version as released (including beta versions)."

In the json file website/generated/state/release.txt, this script changes the entry for a version of boost from "release_status":"dev" to a specific release date, as follows:

```
<         "release_status":
<             "dev"
---
>         "release_date":
>             "Tue, 27 Oct 2020 19:02:46 +0000"
```

PHP7: yes

---

## update-doc-list.php

"Updates the documentation list from `doc/libraries.xml`, or from a boost release/repo. Run from a cron job for the git development branches, but will need to be run manually for an actual release."

```
Usage: {} [path] [version]

Options:

    --quiet

Updates the library metadata in the documentation list.

The path argument can be either a boost release, or the path of the
boost super project in a full mirror of the git repositories.

The version argument is the version of boost to update for. If missing
will update master and develop from a git mirror.

When called with no arguments, just updates the serialized cache.
Used for manual updates.

Example
=======

To update from a beta release:

    {} boost_1_62_0_b1 1.62.0.beta1
");
```

The "documentation list" is website/doc/libraries.xml. The "serialized cache" is website/generated/libraries.txt.

The script first ingests all the library data from website/doc/libraries.xml.

If provided at the command-line with the full file path to a boost release such as /path/to/boost_1_62_0_b1, or alternatively a /path/to/superproject, it will parse/ingest those files and update it's internal view of the library data.

Finally, the script will output the latest data:  
1. In an XML file: website/doc/libraries.xml  
2. In a serialized data representation: website/generated/libraries.txt  
Both of the outputs contain the same information, just in a different format.  

PHP7: yes

---

## update-pages.php

"Update the html pages and rss feeds for new or updated quickbook files."

Similar to refresh-pages.php, it is calling update_quickbook().  

```
if ($options->flags['in-progress-only']) {
    $site_tools->update_in_progress_pages();
} else {
    $site_tools->update_quickbook();
}
```
See section below "Difference between refresh-pages.php and update-pages.php"

PHP7: yes

---

## update-repo.php

"Updates the boost super project from the metadata stored for the website. Almost always run from a cron job."

"Usage: {} location version"

location should be a fully qualified file path /path/to/boost  
version should be in the format "master" or "develop"  

While this script may be intended to "update the repo (the boost superproject)" in multiple ways, at the moment it only does one task.
It reads the library metadata from the website project, which resides in /../doc/libraries.xml
And then, writes an updated /libs/maintainers.txt to the boost project, updating the maintainers based on the website's data.

PHP7: yes

---

## Difference between refresh-pages.php and update-pages.php:

What is the difference between -  
refresh-pages.php  
update-pages.php  
update-pages.php --in-progress-only  

refresh-pages.php will generate the quickbook pages for nearly all pages. It refreshes almost everything. However, it seems to miss a few pages that update-pages.php does process. refresh-pages.php does not update the rss feeds or add new pages.  

update-pages.php updates new pages, instead of everything. During a typical deployment cycle run update-pages.php. It processes a few pages in generated/, generated/state/ and users/ that refresh-pages.php misses. So, a very complete refresh would require running both the refresh script and the update script.

In a test, refresh-pages.php created around 100 pages:
- 1 page - doc/.htaccess
- 6 pages in generated/
- 3 pages in generated/state
- 1 page - users/download/boost_jam_3_1_18.html
- almost 100 pages in users/history
- 5 pages in users/news

update-pages.php created around 20 pages:

- 1 page - doc/.htaccess
- 10 pages in generated/
- 4 pages in generated/state
- 1 page - users/history/in_progress.html

./update-pages.php --in-progress-only created 4 pages:
```
-rw-r--r-- 1 root root    351329 Oct 28 19:08 ./generated/state/beta_release_notes.txt
-rw-r--r-- 1 root root     57541 Oct 28 19:08 ./generated/state/feed-pages.txt
-rw-r--r-- 1 root root    894013 Oct 28 19:08 ./generated/state/page-cache.txt
-rw-r--r-- 1 root root     40774 Oct 28 19:08 ./users/history/in_progress.html
```

- 3 pages in generated/state
- 1 page - users/history/in_progress.html

