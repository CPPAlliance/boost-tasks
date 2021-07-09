This page will discuss the sqlite database used with the boost-tasks scripts.

The SQLite3 schema is in [sqlite3-schema.txt](sqlite3-schema.txt). There are 9 tables:

- event 
- eventstate 
- githubcache 
- history 
- mirror 
- pull_request 
- pull_request_event
- queue 
- variable 

event - A list of github events, mainly push events. They are in standard db format, unlike the bulk json in the githubcache table downloaded from api.github.com. 

eventstate - An additional table keeping track of events. In brief testing, it is a single row:  
```
id|name|start_id|last_id  
1|github-state|16951133316|17017172424  
```
githubcache - Contains the following types of data, retrieved from api.github.com:  

Events. A list of github "events" from the organization. Example urls: https://api.github.com/orgs/boostorg/events, https://api.github.com/organizations/3170529/events?page=2, https://api.github.com/organizations/3170529/events?page=3 .  The "next" urls to follow are derived from the "links" http header.  
Repos. A list of github "repos" in the organization. Example urls: https://api.github.com/orgs/boostorg/repos, https://api.github.com/organizations/3170529/repos?page=2  
Pulls. A list of github pull requests for each repository. Example urls: https://api.github.com/repos/boostorg/type_erasure/pulls  
Commit information. The "develop" and "master" commit data on each repository. Example urls:  https://api.github.com/repos/boostorg/math/git/refs/heads/develop, https://api.github.com/repos/boostorg/math/git/refs/heads/master  

history - The "history" and "variable" tables are used by "update-super-project" for bookkeeping.  

mirror - A simple list of all repositories and their url's. Corresponds to the mirror script which syncs all boost repos.  

pull_request - A list of open pull requests. The scripts sync this from api.github.com. Closed PR's are deleted from the sqlite db.  

pull_request_event - A collection of pull request events from webhook.php, that appears to be run at another location (the webserver instead of the workstation). Further usage of table data not apparent.  

queue - A table to keep track of queue status. Example during testing:  
```
id|name|last_github_id|type
1|mirror|17017172424|
2|develop|0|PushEvent
3|master|17017172424|PushEvent
```
variable - The "history" and "variable" tables are used by "update-super-project" for bookkeeping.  
