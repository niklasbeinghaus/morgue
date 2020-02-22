<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require_once 'phplib/CurlClient.php';
require_once 'phplib/Postmortem.php';
require_once 'phplib/Configuration.php';
require_once 'phplib/Auth.php';
require_once 'vendor/autoload.php';
$config = Configuration::get_configuration();
if (!$config) {
    $message = "Could not parse configuration file.";
    $content = "error";
    error_log("ERROR: " . $message);
    include '../views/page.php';
    die();
}
$app = new \Slim\App(['settings' => $config]);
/**
 * helper method for returning the selected timezone.
 * If set, get the user timezone else get it from the global config
 * otherwise default to 'America/New_York'
 *
 * @return mixed|string
 */
function getUserTimezone()
{
    $config = Configuration::get_configuration();
    $tz = 'America/New_York';
    if (isset($_SESSION['timezone'])) {
        $tz = $_SESSION['timezone'];
    } elseif (isset($config['timezone'])) {
        $tz = $config['timezone'];
    }
    return $tz;
}

// helper method to sort events reverse by starttime
function cmp($first, $second)
{
    if ($first['starttime'] == $second['starttime']) {
        return 0;
    }
    return ($first['starttime'] < $second['starttime']) ? 1 : -1;
}

// helper method to format the difference between two dates
// param: diff between two dates
// returns: string
function getTimeString($diff)
{
    $min = floor($diff / 60 % 60);
    $hours = floor($diff / 60 / 60);
    if ($min == 1) {
        $min = $min . " minute";
    } else {
        $min = $min . " minutes";
    }
    if ($hours == 1) {
        $hours = $hours . " hour";
    } else {
        $hours = $hours . " hours";
    }
    return $hours . ", " . $min;
}

/**
 * Helper function for default statustime.
 */
function default_status_time()
{
    return new DateTime('1970-01-01', new DateTimeZone('UTC'));
}

/*
 * Now include all routes and libraries for features before actually running the app
 */
foreach ($config['feature'] as $feature) {
    if ($feature['enabled'] == "on") {
        $files = ['lib.php', 'routes.php'];
        $path = '../features/' . $feature['name'] . '/';
        foreach ($files as $file) {
            $include = $path . $file;
            if (file_exists($include)) {
                include $include;
            }
        }
    }
}
// set admin info on the environment array
// so it's available to our request handlers
$env = $app->getContainer()['environment'];
$env['admin'] = MorgueAuth::get_auth_data();
$app->get(
    '/',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {
        $content = 'content/frontpage';
        $show_sidebar = true;
        $selected_tags = trim($request->getQueryParam('tags'));
        if (strlen($selected_tags) > 0) {
            $selected_tags = explode(",", $selected_tags);
            $selected_tags = array_map('trim', $selected_tags);
            $events = Postmortem::get_events_for_tags($selected_tags);
        } else {
            $selected_tags = null;
            $events = Postmortem::get_all_events();
        }
        if ($events["status"] == Postmortem::OK) {
            $events = $events["values"];
        } else {
            $response->withStatus(500);
            echo json_encode($events["error"]);
            return;
        }
        uasort($events, 'cmp');
        $tags = Postmortem::get_tags();
        if ($tags["status"] == Postmortem::OK) {
            $tags = $tags["values"];
        } else {
            $tags = array();
        }
        include 'views/page.php';
    }
);
$app->post(
    '/timezone',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {
        $_SESSION['timezone'] = $request->getParsedBody()['timezone'];
        $app->redirect('/', $request->getServerParams()['HTTP_REFERER']);
    }
);
$app->post(
    '/events',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {
        $title = $request->getParsedBody()['title'];
        $start_date = $request->getParsedBody()['start_date'];
        $start_time = $request->getParsedBody()['start_time'];
        $end_date = $request->getParsedBody()['end_date'];
        $end_time = $request->getParsedBody()['end_time'];
        $detect_date = $request->getParsedBody()['detect_date'];
        $detect_time = $request->getParsedBody()['detect_time'];
        $status_date = $request->getParsedBody()['status_date'];
        $status_time = $request->getParsedBody()['status_time'];
        $timezone = $request->getParsedBody()['timezone'];
        $severity = $request->getParsedBody()['severity'];
        $problem_type = $request->getParsedBody()['problem_type'];
        $impact_type = $request->getParsedBody()['impact_type'];
        $incident_cause = $request->getParsedBody()['incident_cause'];
        $subsystem = $request->getParsedBody()['subsystem'];
        $owner_team = $request->getParsedBody()['owner_team'];
        $startdate = new DateTime($start_date . " " . $start_time, new DateTimeZone($timezone));
        $enddate = new DateTime($end_date . " " . $end_time, new DateTimeZone($timezone));
        $detectdate = new DateTime($detect_date . " " . $detect_time, new DateTimeZone($timezone));
        if (!$status_date || !$status_time) {
            $statusdate = default_status_time();
        } else {
            $statusdate = new DateTime("$status_date $status_time", new DateTimeZone($timezone));
        }
        $event = array(
            "title" => $title,
            "summary" => "",
            "why_surprised" => "",
            "tldr" => "",
            "meeting_notes_link" => "",
            "facilitator" => "",
            "starttime" => $startdate->getTimestamp(),
            "endtime" => $enddate->getTimestamp(),
            "statustime" => $statusdate->getTimestamp(),
            "detecttime" => $detectdate->getTimestamp(),
            "severity" => $severity,
            "problem_type" => $problem_type,
            "impact_type" => $impact_type,
            "incident_cause" => $incident_cause,
            "subsystem" => $subsystem,
            "owner_team" => $owner_team,
        );
        $event = Postmortem::save_event($event);
        if (isset($event['error'])) {
            error_log(print_r($event, true));
        }
        return $response->withStatus(302)->withHeader('Location', '/events/' . $event['id']);
        //$app->redirect('', '/events/' . $event['id']);
    }
);
$app->get(
    '/events/{id}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $event = Postmortem::get_event($id);
        if (is_null($event["id"])) {
            echo "loooool";
            return $response->withStatus(404);
        }
        $page_title = sprintf("%s | Morgue", $event['title']);
        $starttime = $event["starttime"];
        $endtime = $event["endtime"];
        $detect_time = $event["detecttime"];
        $status_time = $event["statustime"];
        $timezone = getUserTimezone();
        $severity = $event["severity"];
        $problem_type = $event["problem_type"];
        $facilitator = $event["facilitator"];
        $subsystem = $event["subsystem"];
        $owner_team = $event["owner_team"];
        $impact_type = $event["impact_type"];
        $incident_cause = $event["incident_cause"];
        $gcal = $event["gcal"];
        $contact = $event["contact"];
        $summary = $event["summary"];
        $why_surprised = $event["why_surprised"];
        $tldr = $event["tldr"];
        $meeting_notes_link = $event["meeting_notes_link"];
        $tz = new DateTimeZone($timezone);
        $start_datetime = new DateTime("@$starttime");
        $start_datetime->setTimezone($tz);
        $end_datetime = new DateTime("@$endtime");
        $end_datetime->setTimezone($tz);
        if ($status_time) {
            $status_datetime = new DateTime("@$status_time");
            $status_datetime->setTimezone($tz);
        } else {
            $status_datetime = false;
        }
        $detect_datetime = new DateTime("@$detect_time");
        $detect_datetime->setTimezone($tz);
        $impacttime = getTimeString($endtime - $starttime);
        if ($endtime >= $detect_time) {
            $resolvetime = getTimeString($endtime - $detect_time);
        } else {
            $resolvetime = $impacttime;
        }
        $undetecttime = getTimeString($detect_time - $starttime);
        $edit_status = Postmortem::get_event_edit_status($event);
        $content = 'content/edit';
        $curl_client = new CurlClient();
        $show_sidebar = false;
        include 'views/page.php';
    }
);
$app->delete(
    '/events/{id}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) {
        header("Content-Type: application/json");
        $id = (int)$args['id'];
        $result = Postmortem::delete_event($id);
        error_log(json_encode($result));
        if ($result["status"] == Postmortem::ERROR) {
            return $response->withStatus(500)->withHeader('error', json_encode($result["error"]));
        } else {
            return $response->withStatus(204, 'Event successfully deleted');
        }
    }
);
$app->get(
    '/events/{id}/undelete',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $result = Postmortem::undelete_event($id);
        if ($result["status"] == Postmortem::ERROR) {
           return $response->withStatus(500)->withBody($result['error']);
        } else {
            $app->redirect("/", "/events/$id");
        }
    }
);
$app->get(
    '/events/{id}/summary',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $event = Postmortem::get_event($id);
        if (is_null($event["id"])) {
            return $response->withStatus(404);
        }
        header("Content-Type: application/json");
        echo json_encode(array("summary" => $event["summary"]));
    }
);
$app->get(
    '/events/{id}/lock',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        header("Content-Type: application/json");
        $id = (int)$args['id'];
        $event = Postmortem::get_event($id);
        $status = Postmortem::get_event_edit_status($event);
        if ($status === Postmortem::EDIT_UNLOCKED) {
            Postmortem::set_event_edit_status($id);
        }
        $return = ["status" => $status, "modifier" => $event["modifier"]];
        echo json_encode($return);
    }
);
$app->put(
    '/events/{id}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        // get the base event data
        $old_event = Postmortem::get_event($id);
        if (is_null($old_event["id"])) {
            return $response->withStatus(500);
        }
        $event = ["title" => $old_event["title"], "id" => $id];
        $params = $request->getParsedBody();
        foreach ($params as $key => $value) {
            switch ($key) {
                case "title":
                    $event["title"] = $value;
                    break;
                case "summary":
                    $event["summary"] = $value;
                    break;
                case "why_surprised":
                    $event["why_surprised"] = $value;
                    break;
                case "tldr":
                    $event["tldr"] = $value;
                    break;
                case "meeting_notes_link":
                    $event["meeting_notes_link"] = $value;
                    break;
                case "start_date":
                case "start_time":
                    if (!isset($params["timezone"])) {
                        return $response->withStatus(400);
                    }
                    $timezone = new DateTimeZone($params["timezone"]);
                    $starttime = isset($event["starttime"]) ? $event["starttime"] : $old_event["starttime"];
                    $edate = new DateTime("@$starttime");
                    $edate->setTimezone($timezone);
                    $new_date = date_parse($value);
                    if ($key == "start_time") {
                        $edate->setTime($new_date["hour"], $new_date["minute"]);
                    } elseif ($key == "start_date") {
                        $edate->setDate($new_date["year"], $new_date["month"], $new_date["day"]);
                    }
                    $event["starttime"] = $edate->getTimeStamp();
                    break;
                case "end_date":
                case "end_time":
                    if (!isset($params["timezone"])) {
                        return $response->withStatus(400);
                    }
                    $timezone = new DateTimeZone($params["timezone"]);
                    $endtime = isset($event["endtime"]) ? $event["endtime"] : $old_event["endtime"];
                    $edate = new DateTime("@$endtime");
                    $edate->setTimezone($timezone);
                    $new_date = date_parse($value);
                    if ($key == "end_time") {
                        $edate->setTime($new_date["hour"], $new_date["minute"]);
                    } elseif ($key == "end_date") {
                        $edate->setDate($new_date["year"], $new_date["month"], $new_date["day"]);
                    }
                    $event["endtime"] = $edate->getTimeStamp();
                    break;
                case "detect_date":
                case "detect_time":
                    if (!isset($params["timezone"])) {
                        return $response->withStatus(400);
                    }
                    $timezone = new DateTimeZone($params["timezone"]);
                    $detecttime = isset($event["detecttime"]) ? $event["detecttime"] : $old_event["detecttime"];
                    $edate = new DateTime("@$detecttime");
                    $edate->setTimezone($timezone);
                    $new_date = date_parse($value);
                    if ($key == "detect_time") {
                        $edate->setTime($new_date["hour"], $new_date["minute"]);
                    } elseif ($key == "detect_date") {
                        $edate->setDate($new_date["year"], $new_date["month"], $new_date["day"]);
                    }
                    $event["detecttime"] = $edate->getTimeStamp();
                    break;
                case "status_datetime":
                    if (!$value) {
                        $event["statustime"] = 0;
                        break;
                    }
                    if (!isset($params["timezone"])) {
                        return $response->withStatus(400);
                    }
                    $timezone = new DateTimeZone($params["timezone"]);
                    $statustime = $old_event["statustime"];
                    $edate = new DateTime("@$statustime");
                    $edate->setTimezone($timezone);
                    $new_date = date_parse($value);
                    $edate->setTime($new_date["hour"], $new_date["minute"]);
                    $edate->setDate($new_date["year"], $new_date["month"], $new_date["day"]);
                    $event["statustime"] = $edate->getTimeStamp();
                    break;
                case "severity":
                    $event["severity"] = $value;
                    break;
                case "contact":
                    $event["contact"] = $value;
                    break;
                case "facilitator":
                    $event["facilitator"] = $value;
                    break;
                case "gcal":
                    $event["gcal"] = $value;
                    break;
                case "problem_type":
                    $event["problem_type"] = $value;
                    break;
                case "impact_type":
                    $event["impact_type"] = $value;
                    break;
                case "incident_cause":
                    $event["incident_cause"] = $value;
                    break;
                case "subsystem":
                    $event["subsystem"] = $value;
                    break;
                case "owner_team":
                    $event["owner_team"] = $value;
                    break;
            }
        }
        $event = Postmortem::save_event($event);
        if (is_null($event["id"])) {
            return $response->withStatus(500);
        }
        $app->redirect('/', '/events/' . $event["id"], 201);
    }
);
$app->post(
    '/events/{id}/history',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        header("Content-Type: application/json");
        $action = $request->getParsedBody()['action'];
        $event = array(
            "id" => $id,
            "summary" => $request->getParsedBody()['summary'],
            "why_surprised" => $request->getParsedBody()['why_surprised'],
        );
        // store history
        $env = $app->getContainer()['environment'];
        $admin = $env['admin']['username'];
        Postmortem::add_history($event['id'], $admin, $action);
    }
);
$app->post(
    '/events/{id}/tags',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        header("Content-Type: application/json");
        $tags = $request->getParsedBody()['tags'];
        $tags = explode(",", $tags);
        $tags = array_map('trim', $tags);
        $tags = array_map('strtolower', $tags);
        $res = Postmortem::save_tags_for_event($id, $tags);
        if ($res["status"] == Postmortem::ERROR) {
            $response->withStatus(400);
        } else {
            $response->withStatus(201);
            $tags = Postmortem::get_tags_for_event($id);
            if ($tags["status"] == Postmortem::ERROR) {
                $response->withStatus(404);
                return;
            } else {
                $output = json_encode($tags["values"]);
                echo str_replace("\\/", "/", $output);
            }
        }
    }
);
$app->delete(
    '/events/{event_id}/tags/{tag_id}',
    function (ServerRequestInterface $request, ResponseInterface $response, $event_id, $tag_id) use ($app) {
        $event_id = (int)$event_id;
        $tag_id = (int)$tag_id;
        header("Content-Type: application/json");
        $res = Postmortem::delete_tag($tag_id, $event_id);
        if ($res["status"] == Postmortem::ERROR) {
            $response->withStatus(500)->withBody($res["error"]);
        } else {
            $response->withStatus(204);
        }
        return $response;
    }
);
$app->get(
    '/ping',
    function () use ($app) {
        header("Content-Type: application/json");
        echo json_encode(array('status' => 'ok'));
    }
);
// Handle custom static assets.
// Javascript first then CSS.
$app->get(
    '/features/{feature}/js/{path}',
    function (ServerRequestInterface $request, ResponseInterface $response, $feature, $path) use ($app) {
        // read the file if it exists. Then serve it back.
        $file = stream_resolve_include_path("features/{$feature}/assets/js/{$path}");
        if (!$file) {
            $response->withStatus(404);
            $app->log->error("couldn't file custom js asset at $path");
            return $response;
        }
        $thru_file = file_get_contents($file);
        $response->withHeader("Content-Type", "application/javascript");
        print $thru_file;
        return $response;
    }
);
$app->get(
    '/features/{feature}/css/{path}',
    function (ServerRequestInterface $request, ResponseInterface $response, $feature, $path) use ($app) {
        // read the file if it exists. Then serve it back.
        $file = stream_resolve_include_path("features/{$feature}/assets/css/{$path}");
        if (!$file) {
            return $response->withStatus(404);
        }
        $thru_file = file_get_contents($file);
        $response->withHeader("Content-Type", "text/css");
        print $thru_file;
        return $response;
    }
);
$app->run();
