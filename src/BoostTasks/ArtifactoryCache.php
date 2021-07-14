<?php

// Downloads releases from artifactory.
//
// "ArtifactoryCache" is based on the earlier "BinTrayCache".

// A difference is that BinTrayCache discovered metadata about the files at api.bintray.com
// https://api.bintray.com/packages/boostorg/{$bintray_path}/files"
//
// With artifactory, the metadata is being found in the corresponding json file for each archive.

namespace BoostTasks;

use BoostTasks\Settings;
use BoostTasks\Log;
use BoostTasks\Process;
use BoostTasks\BoostRepo;
use RuntimeException;

class ArtifactoryCache {
    var $path;
    var $stream_context;

    function __construct() {
        $this->path = Settings::dataPath('artifactory');
        // Get a copy of boost
        $this->boost_repo = new BoostRepo();
        $this->boost_repo->setupFullCheckout();

        // 'bindto' is used to force file_get_contents to use IPv4,
        // because the IPv6 address isn't working at the time of writing.
        $this->stream_context = stream_context_create(
            array('socket' =>
                array('bindto' => '0:0')
            ));
    }

    static function parseVersion($version) {
        if (preg_match('@^(master|develop)$@i', $version, $match)) {
            return strtolower($match[0]);
        } else if (preg_match(
            '@^
            (?:boost_)?
                        (\d+)
            [._ ]+      (\d+)
            (?:[._ ]+   (\d+))?
            (?:[._ ]*   be?t?a? [._ ]*  (\d+|))?
            (?:[._ ]*   rc      [._ ]*  (\d+))?
            (?:[.][.a-z0-9]+)?
            ()
            $@xi', $version, $match, PREG_OFFSET_CAPTURE)) {
            return array(
                intval($match[1][0]),
                intval($match[2][0]),
                intval($match[3][0]),
                $match[4][1] != -1 ? "beta". ($match[4][0] ?: 1) : '',
                $match[5][1] != -1 ? "rc". ($match[5][0] ?: 1) :'',
            );
        } else {
            throw new RuntimeException("Unable to get version from {$version}");
        }
    }
    static function parseVersionFilename($version) {
        $parsed_version = self::parseVersion($version);
        if (is_array($parsed_version)) {
            if ($parsed_version[3] and $parsed_version[4]) {
              $parsed_version[3] = str_replace('beta', 'b', $parsed_version[3]);
              $filename_version = $parsed_version[0] . "_" . $parsed_version[1] . "_" . $parsed_version[2] . "_" . $parsed_version[3] . "_" . $parsed_version[4] ;
            } else if ($parsed_version[3]) {
            $parsed_version[3] = str_replace('beta', 'b', $parsed_version[3]);
              $filename_version = $parsed_version[0] . "_" . $parsed_version[1] . "_" . $parsed_version[2] . "_" . $parsed_version[3] ;
            } else if ($parsed_version[4]) {
              $filename_version = $parsed_version[0] . "_" . $parsed_version[1] . "_" . $parsed_version[2] . "_" . $parsed_version[4] ;
            } else {
              $filename_version = $parsed_version[0] . "_" . $parsed_version[1] . "_" . $parsed_version[2] ;
            }
        } else {
            Log::info("Version is a string, such as master or develop.");
              $discovered_version = self::discoverVersion($parsed_version);
              $filename_version = $discovered_version[0] . "_" . $discovered_version[1] . "_" . $discovered_version[2] ;
             }
       return $filename_version ;
    }

    static function parseVersionDirectory($version) {
        $parsed_version = self::parseVersion($version);
        if (is_array($parsed_version)) {
            if ($parsed_version[3] and $parsed_version[4]) {
              $directory_version = $parsed_version[0] . "." . $parsed_version[1] . "." . $parsed_version[2] . "." . $parsed_version[3];
            } else if ($parsed_version[3]) {
              $directory_version = $parsed_version[0] . "." . $parsed_version[1] . "." . $parsed_version[2] . "." . $parsed_version[3] ;
            } else if ($parsed_version[4]) {
              $directory_version = $parsed_version[0] . "." . $parsed_version[1] . "." . $parsed_version[2] ;
            } else {
              $directory_version = $parsed_version[0] . "." . $parsed_version[1] . "." . $parsed_version[2] ;
            }
        } else {
            Log::info("Version is a string, such as master or develop.");
              $discovered_version = self::discoverVersion($parsed_version);
              $directory_version = $discovered_version[0] . "." . $discovered_version[1] . "." . $discovered_version[2] ;
             }
       return $directory_version ;
    }

    static function discoverVersion($version) {
        // discover version information specifically about "develop" or "master".
        $url = "https://boostorg.jfrog.io/artifactory/api/storage/main/$version";
        $discovered_data=json_decode(file_get_contents($url));
        $children_list = $discovered_data->children;
        $saved_uri="";
        // discover the largest uri (i.e. version number)
        foreach($children_list as $child) {
            $uri=$child->uri;
            if( strcmp( strtolower($saved_uri), strtolower($uri) ) < 0 ) {
                $saved_uri = $uri;
                }
             }
        preg_match('/boost_([0-9]+)_([0-9]+)_([0-9]+)-snapshot.*/', $saved_uri, $matches, PREG_OFFSET_CAPTURE);
        // returns the version numbers in an array, because the values need to be joined with underscores or dashes in different situations.
        return array($matches[1][0],$matches[2][0],$matches[3][0]);
        }

    function fetchDetails($bintray_version, $bintray_path = null) {
        $parsed_version = self::parseVersion($bintray_version);
        $filename_version = self::parseVersionFilename($bintray_version);
        $directory_version = self::parseVersionDirectory($bintray_version);
        // set a default
        $filename_modifier="";

        $filter_by_version = false;

        if ($bintray_path) {
            $filter_by_version = true;
        } else if ($bintray_version == 'master' || $bintray_version == 'develop') {
            $bintray_path = "{$bintray_version}/snapshot";
            $artifactory_repo = $bintray_version;
            // note that the filename is different, includes "-snapshot":
            $filename_modifier="-snapshot";
            $artifactory_file_path = "";
        //} else if (preg_match('@.*(beta|rc)\.?\d*$@', $bintray_version)) {
        //    $bintray_path = "beta/boost";
        //    $filter_by_version = true;
        } else if (preg_match('@.*(beta)\.?\d*.*$@', $bintray_version)) {
            $bintray_path = "beta/boost";
            $filter_by_version = true;
            $artifactory_repo = "beta";
            $artifactory_file_path = "$directory_version/source";
        } else if (preg_match('@.*(rc)\.?\d*$@', $bintray_version)) {
            $bintray_path = "release/boost";
            $filter_by_version = true;
            $artifactory_repo = "release";
            $artifactory_file_path = "$directory_version/source";
        } else {
            $bintray_path = "release/boost";
            $filter_by_version = true;
            $artifactory_repo = "release";
            $artifactory_file_path = "$directory_version/source";
        }
        // $url = "https://api.bintray.com/packages/boostorg/{$bintray_path}/files";

        $files = array() ;
        $extensions = array('tar.bz2','tar.gz','7z', 'zip');
        foreach ($extensions as $extension) {
            $filename = "boost_" . $filename_version . $filename_modifier . "." . $extension ;
            $url = "https://boostorg.jfrog.io/artifactory/main/$artifactory_repo/$artifactory_file_path/$filename.json";

            $file_info = file_get_contents($url, false, $this->stream_context);
            if (!$file_info) {
                throw new RuntimeException("Error downloading file details from artifactory.");
            }

            $file_info = json_decode($file_info);
            $file_info->name = $file_info->file;
            // $file_info->path = $artifactory_file_path . "/" . $filename;
            $file_info->path = join("/", array_filter(array($artifactory_file_path, $filename), 'strlen'));
            $file_info->repo = $artifactory_repo;
            // Note 2021-07: the develop snapshots should update every time the commit changes.
            // Looking at the way the website code operates, it's expecting a commit hash.
            // The field in the .bintray-version file is called "hash".
            if (($artifactory_repo == "develop") || ($artifactory_repo == "master")) {
                $file_info->version = $file_info->commit;
            }
            else {
                // Note 2021-07: When it comes to "releases" rather than development snapshots,
                // api.bintray.com had set "[version] => 1.75.0". Not a commit sha, but a version number.
                // This analysis might not be correct and the following line could be changed to a commit sha.
                $file_info->version = $directory_version;
            }
            if (isset($file_info->created) and $file_info->created) {
                // no actions needed
                Log::debug("created field found.");
            } 
            else {
                $commit_date = $this->boost_repo->getCommitDate($file_info->commit);
                if ($commit_date) {
                    Log::debug("retrieving created field from git.");
                    $file_info->created = $commit_date;
                }
                else {
                    Log::warning("Unable to retrieve commit date. Check directories in var/data/artifactory/_repo_\n");
                }
            }
            $files[] = $file_info;

            if (!is_array($files)) {
              throw new RuntimeException("Error parsing latest details.");
            }

            // if ($filter_by_version) {
            //     $parsed_version = self::parseVersion($bintray_version);
            //     $files2 = array();
            //     foreach ($files as $x) {
            //         if (self::parseVersion(basename($x->path)) == $parsed_version) {
            //             $files2[] = $x;
            //         }
            //     }
            //     $files = $files2;
            // }
        }
            return new ArtifactoryCache_FileDetails($this, $bintray_version, $bintray_path, $files);
    }

    // TODO: Would be nice if the returned data was in the same format as
    //       fetchDetails/getFileDetails.
    function latestDownload($branch) {
        $children = $this->scanBranch($branch);
        arsort($children);
        foreach ($children as $child_dir => $timestamp) {
            foreach(glob("{$child_dir}/*/*.meta") as $meta_path) {
                if (preg_match('@^(.*[.](?:tar.bz2|tar.gz|zip))[.]meta$@', $meta_path, $matches)) {
                    $file_path = $matches[1];

                    $meta = json_decode(file_get_contents($meta_path), true);
                    if (!$meta) {
                        Log::warning("Unable to decode meta file at {$meta_path}");
                        continue;
                    }

                    if (hash_file('sha256', $file_path) != $meta['sha256']) {
                        Log::warning("Hash doesn't match meta file at {$file_path}");
                        continue;
                    }

                    $meta['path'] = $file_path;
                    return $meta;
                }
            }
        }
    }

    function cleanup($file = null) {
        $branches = array();
        if (is_null($file)) {
            foreach(scandir($this->path) as $dir) {
                if ($dir[0] === '.') { continue; }
                $branches[] = $dir;
            }
        }
        else {
            $branches = array($file->repo);
        }

        foreach($branches as $x) {
            $children = $this->scanBranch($x);
            $delete_before = max($children) - 5*60*60;
            foreach($children as $child_dir => $timestamp) {
                if ($timestamp < $delete_before) {
                    TempDirectory::recursiveRemove("{$child_dir}");
                }
            }
        }
    }

    // Returns array of path => timestamp.
    // Q: Is this silly? Sorting the path name lexicographically
    //    should work fine. I suppose the date format might change
    //    one day.
    private function scanBranch($branch) {
        $children = array();

        $dir = realpath("{$this->path}/{$branch}");
        if (!$dir) { return $children; }

        foreach(scandir($dir) as $child) {
            if ($child[0] === '.') { continue; }
            // Q: Accept any time? Or just the ones that are generated.
            $timestamp = strtotime($child);
            if ($timestamp === FALSE) {
                Log::warning("Invalid cache dir: {$dir}/{$child}");
            }
            else {
                $children["{$dir}/{$child}"] = $timestamp;
            }
        }

        return $children;
    }

    static function extractSingleRootArchive($file_path, $tmpdir) {
        $subdir = "{$tmpdir}/new";
        mkdir($subdir);

        list($base_name, $extension) = explode('.', basename($file_path), 2);

        switch($extension) {
        case 'tar.bz2':
            Process::run("tar -xjf '{$file_path}'", $subdir, null, null, 60*30);
            break;
        case 'tar.gz':
            Process::run("tar -xzf '{$file_path}'", $subdir, null, null, 60*30);
            break;
        case '7z':
            Process::run("7z x '{$file_path}'", $subdir, null, null, 60*30);
            break;
        case 'zip':
            Process::run("unzip '{$file_path}'", $subdir, null, null, 60*30);
            break;
        default:
            assert(false);
        }

        // Find the extracted tarball in the temporary directory.
        $new_directories = array_filter(scandir($subdir),
            function($x) { return $x[0] != '.'; });
        if (count($new_directories) == 0) {
            throw new RuntimeException("Error extracting archive");
        }
        else if (count($new_directories) != 1) {
            throw new RuntimeException("Multiple roots in archive");
        }
        return "{$subdir}/".reset($new_directories);
    }

    static function urlBaseDir($urls) {
        $base_url = null;
        foreach($urls as $url) {
            if (is_null($base_url)) {
                $base_url = $url;
                $last_slash = strrpos($base_url, "/");
            } else {
                $first_difference = strspn($base_url ^ $url, "\0");
                $last_slash = strrpos($base_url, "/", $first_difference - strlen($base_url));
            }
            $base_url = substr($base_url, 0, $last_slash + 1);
        }
        return $base_url;
    }
}

class ArtifactoryCache_FileDetails {
    var $cache;
    var $bintray_version;
    var $bintray_path;
    var $files;

    function __construct($cache, $bintray_version, $bintray_path, $files) {
        $this->cache = $cache;
        $this->bintray_version = $bintray_version;
        $this->bintray_path = $bintray_path;
        $this->files = $files;
    }

    function getDownloadPage() {
        $urls = array();
        foreach($this->files as $file) {
           $urls[] = $this->getFileUrl($file);
        }
        return ArtifactoryCache::urlBaseDir($urls);
    }

    function getFileUrl($file) {
        // return "https://dl.bintray.com/boostorg/{$file->repo}/{$file->path}";
        return "https://boostorg.jfrog.io/artifactory/main/{$file->repo}/{$file->path}";
    }

    // Return path the file was downloaded to.
    // Throws an exception if something goes wrong while downloading, or the
    // hash of the downloaded file doesn't match.
    function cachedDownload($file) {
        $date = date('Y-m-d\TH:i', strtotime($file->created));

        // 'repo' is actually the branch, that's just the way bintray is organised.
        // $download_dir = "{$this->cache->path}/{$file->repo}/{$date}/{$file->sha1}";
        $download_dir = "{$this->cache->path}/{$file->repo}/{$date}/{$file->sha256}";
        $download_path = "{$download_dir}/{$file->name}";
        $meta_path = "{$download_path}.meta";

        if (!is_file($meta_path)) {
            if (!is_dir($download_dir)) {
                mkdir($download_dir, 0777, true);
            }

            if (is_file($download_path) && hash_file('sha256', $download_path) != $file->sha256) {
                unlink($download_path);
            }

            if (!is_file($download_path)) {
                $this->downloadFile($this->getFileUrl($file), $download_path);
            }

            file_put_contents($meta_path, json_encode($file));
        }

        if (hash_file('sha256', $download_path) != $file->sha256) {
            unlink($download_path);
            throw new RuntimeException("File signature doesn't match: {$this->getFileUrl($file)}");
        }

        return $download_path;
    }

    // Downloads file from $url to local path $dst_path
    // Throws RuntimeException on failure.
    function downloadFile($url, $dst_path) {
        $download_fh = fopen($url, 'rb', false, $this->cache->stream_context);

        if (!$download_fh) {
            throw new RuntimeException("Error connecting to {$url}");
        }

        if (feof($download_fh)) {
            throw new RuntimeException("Empty download: {$url}");
        }

        $tmp_dir = "{$this->cache->path}/tmp";
        if (!is_dir($tmp_dir)) { mkdir($tmp_dir, 0777, true); }
        $temp_path = tempnam($tmp_dir, "download-");
        try {
            file_put_contents($temp_path, $download_fh);
            fclose($download_fh);
            rename($temp_path, $dst_path);
        } catch(Exception $e) {
            if ($download_fh) { fclose($download_fh); }
            unlink($temp_path);
            throw $e;
        }
    }
}
