<?php

require_once(__DIR__.'/../vendor/autoload.php');

function webhook() {
    header("Content-Type: text/plain");
    EvilGlobals::init(array('path' => 'webhook.neon', 'webhook' => true));
    $event = get_webhook_event();

    switch($event->event_type) {
    case 'push':
        webhook_push_handler($event);
        break;
    default:
        throw new RuntimeException("Unrecognized event: {$event->event_type}");
    }
}

function webhook_push_handler($event) {
    // TODO: Move to configuration?
    $repos = array(
        'boost' => array(
            'refs/heads/master' => '/home/www/shared/repos/boost-master',
            'refs/heads/develop' => '/home/www/shared/repos/boost-develop',
        ),
        'website' => array(
            'refs/heads/master' => '/home/www/live.boost.org',
            'refs/heads/beta' => '/home/www/beta.boost.org',
        ),
        'build' => array(
           'refs/heads/website' => '/home/www/shared/build-site',
        ),
    );

    $payload = $event->payload;

    $repo_path = null;
    if (array_key_exists($payload->repository->name, $repos)) {
        $branches = $repos[$payload->repository->name];
    }
    else {
        return false;
    }

    if (array_key_exists($payload->ref, $branches)) {
        $repo_path = $branches[$payload->ref];
    }
    else {
        echo "Ignoring ref: ".htmlentities($payload->ref)."\n";
        mail('dnljms@gmail.com', "Not updateding ref: {$payload->ref}): ".date('j M Y'), print_r($payload, true));
        return false;
    }

    echo "Pulling...\n";

    $result = '';
    $result .= "Pull\n";
    $result .= "====\n";
    $result .= "\n";
    $result .= Process::run('git stash', $repo_path);
    $result .= Process::run('git pull -q', $repo_path);
    $result .= Process::run('git stash pop', $repo_path);
    $result .= "\n";

    echo "Done, emailing results.\n";

    // Add the commit details.
    $result .= "Commits\n";
    $result .= "=======\n";
    $result .= "Branch: {$payload->ref}\n";
    $result .= $payload->forced ? "Force pushed " : "Pushed ";
    $result .= "by: {$payload->pusher->name} <{$payload->pusher->email}>\n";

    foreach ($payload->commits as $commit) {
        $result .= "\n";
        $result .= "{$commit->id}\n";
        $result .= "{$commit->author->name} <{$commit->author->email}>\n";
        $result .= "{$commit->message}\n";
    }

    // Email the result
    mail('dnljms@gmail.com', 'Boost website update: '.date('j M Y'), $result);
}

class GitHubWebHookEvent {
    var $event_type;
    var $payload;
}

function get_webhook_event() {
    $secret_key = EvilGlobals::settings('github-webhook-secret');
    if (!$secret_key) {
        throw new RuntimeException("github-webhook-secret not set.");
    }

    $post_body = file_get_contents('php://input');

    // Check the signature

    $signature = array_key_exists('HTTP_X_HUB_SIGNATURE', $_SERVER) ?
        $_SERVER['HTTP_X_HUB_SIGNATURE'] : false;

    if (!preg_match('@^(\w+)=(\w+)$@', $signature, $match)) {
        throw new RuntimeException("Unable to parse signature");
    }

    $check_signature = hash_hmac($match[1], $post_body, $secret_key);
    if ($check_signature != $match[2]) {
        throw new RuntimeException("Signature doesn't match");
    }

    // Get the payload

    switch($_SERVER['CONTENT_TYPE']) {
    case 'application/json':
        $payload_text = $post_body;
        break;
    case 'application/x-www-form-urlencoded':
        if (!array_key_exists('payload', $_POST)) {
            throw new RuntimeException("Unable to find payload");
        }
        $payload_text = $_POST['payload'];
        break;
    default:
        throw new RuntimeException("Unexpected content_type: ".$_SERVER['CONTENT_TYPE']);
    }

    $payload = json_decode($payload_text);
    if (!$payload) {
        throw new RuntimeException("Error decoding payload.");
    }

    // Get the event type
 
    if (!array_key_exists('HTTP_X_GITHUB_EVENT', $_SERVER)) {
        throw new RuntimeException("No event found.");
    }
    $event_type = $_SERVER['HTTP_X_GITHUB_EVENT'];

    $x = new GitHubWebHookEvent;
    $x->event_type = $event_type;
    $x->payload = $payload;
    return $x;
}
