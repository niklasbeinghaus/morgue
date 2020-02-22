<?php
/**
 * Routes for Anniversay Feature
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/api/anniversary',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {
        /*
         * JSON boolean if there is an anniversary today or not
         */
        $today = gmdate("Y-m-d", time());
        $get_date = trim($request->getParsedBody()['date']);
        if ($get_date) {
            $get_date = gmdate("Y-m-d", strtotime($get_date));
            $today = $get_date;
        }
        $conn = Persistence::get_database_object();
        $pm_ids = Anniversary::get_ids($today, $conn);
        if ($pm_ids['status'] === Anniversary::OK) {
            $anivs = count($pm_ids['values']);
        } else {
            $anivs = 0;
        }
        $output = json_encode(['anniversaries_today' => $anivs]);
        $response->getBody()->write($output);
        return $response->withHeader('Content-Type', 'application/json');
    }
);
$app->get(
    '/anniversary',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {

        $content = "anniversary/views/anniversary";
        $show_sidebar = false;
        $page_title = "Today in Post Mortem History";
        $today = date("Y-m-d", time());
        $get_date = trim($request->getParsedBody()['date']);
        if ($get_date) {
            $get_date = date("Y-m-d", strtotime($get_date));
            $today = $get_date;
        }
        $conn = Persistence::get_database_object();
        $pm_ids = Anniversary::get_ids($today, $conn);
        $human_readable_date = date("F jS", strtotime($today));
        $pms = array();
        if ($pm_ids['status'] === 0) {
            foreach ($pm_ids['values'] as $k => $v) {
                $pm = Persistence::get_postmortem($v['id'], $conn);
                $pms[] = $pm;
            }
        } else {
            $message = $pm_ids['error'];
            $content = "error";
            include 'views/page.php';
            return $response->withStatus(500);
        }
        if (count($pms)) {
            // get the tags for each PM we found so we can display them
            foreach ($pms as $k => $pm) {
                $tags = Postmortem::get_tags_for_event($pm['id'], null);
                $pms[$k]['tags'] = $tags['values'];
            }
        }
        $pms = $pms;
        include 'views/page.php';
        return $response->withStatus(200);
    }
);
$app->get(
    '/anniversary/mail',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {
        $config = Configuration::get_configuration('anniversary');
        if ($config['enabled'] !== 'on') {
            return $response->withStatus(412);
        }
        if (empty($config['mailto'])) {
            return $response->withStatus(400);
        }
        $content = "anniversary/views/anniversary-mail";
        $show_sidebar = false;
        $page_title = "Today in Post Mortem History";
        $today = date("Y-m-d", time());
        $get_date = trim($app->request->get('date'));
        if ($get_date) {
            $get_date = date("Y-m-d", strtotime($get_date));
            $today = $get_date;
        }
        $conn = Persistence::get_database_object();
        $pm_ids = Anniversary::get_ids($today, $conn);
        $human_readable_date = date("F jS", strtotime($today));
        $pms = array();
        if ($pm_ids['status'] === 0) {
            foreach ($pm_ids['values'] as $k => $v) {
                $pm = Persistence::get_postmortem($v['id'], $conn);
                $pms[] = $pm;
            }
        } else {
            $message = $pm_ids['error'];
            $content = "error";
            include 'views/page.php';
            return $response->withStatus(500);
        }
        if (count($pms)) {
            // get the tags for each PM we found so we can display them
            foreach ($pms as $k => $pm) {
                $tags = Postmortem::get_tags_for_event($pm['id'], null);
                $pms[$k]['tags'] = $tags['values'];
            }
        }
        if (count($pms)) {

            ob_start();
            include $content . ".php";
            $out = ob_get_contents();
            ob_end_clean();
            print $out;
            $to = $config['mailto'];
            $subject = "$subject";
            $message = $out;
            $headers = "From: {$config['mailfrom']}\r\n";
            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
            $headers .= "Return-Path: {$config['mailfrom']}\r\n";
            $ok = mail($to, $subject, $message, $headers, "-f{$config['mailfrom']}");
            print  "Mail sent. {$ok}";
        } else {
            print "Nothing broke on this date.";
        }
        return $response->withStatus(200);
    }
);


