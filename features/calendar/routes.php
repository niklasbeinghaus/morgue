<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    "/calendar",
    function () use ($app) {

        $content = "calendar/views/calendar_page";
        $show_sidebar = false;
        $page_title = "Post Mortem Calendar";
        include "views/page.php";
    }
);
$app->get(
    "/calendar/facilitators/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        header("Content-Type: application/json");
        $conn = Persistence::get_database_object();
        $facilitator = Calendar::get_facilitator($id, $conn);
        if ($facilitator["status"] === Persistence::OK) {
            if (count($facilitator["values"]) === 1) {
                echo json_encode($facilitator["values"][0]);
            } else {
                echo json_encode($facilitator["values"]);
            }
        } else {
            $response->withStatus(404);
        }
    }
);
$app->post(
    "/calendar/facilitators/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        header("Content-Type: application/json");
        $conn = Persistence::get_database_object();
        $facilitator = [
            "name" => $request->getParsedBody()['name'],
            "email" => $request->getParsedBody()['email']
        ];
        $error = Calendar::set_facilitator($id, $facilitator, $conn);
        if (!$error) {
            $response->withStatus(201);
            echo json_encode($facilitator);
        } else {
            echo $error;
            return $response->withStatus(400);;
        }
    }
);
$app->get(
    "/calendar/facilitators/request/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $config = Configuration::get_configuration('calendar');
        if (!$config["facilitator"]) {
            return;
        }
        $conn = Persistence::get_database_object();
        $event = Postmortem::get_event($id, $conn);
        $user = MorgueAuth::get_auth_data();
        $userHtml = Contact::get_html_for_user($user['username']);
        $domain = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        $to = implode(", ", $config["facilitators_email"]);
        $to .= ', ' . Contact::get_email_for_user($user['username']);
        $from = "Reservix DevOps <devops+morgue@reservix.de>";
        $subject = "Facilitator needed [POMO-{$id}]";
        $message = '
        <html>
        <head>
          <title>Facilitator Needed for PM-' . $id . '</title>
        </head>
        <body style="font-family: \'Helvetica Neue\', Helvetica,Arial, sans-serif;">
          <h3>' . $userHtml . ' has requested a facilitator for this event :</h3>
          <a href="' . $domain . '/events/' . $id . '" style="text-decoration:none;"><h3>' . $event["title"] . '</h3></a>
          <h3>To facilitate this post-mortem, click <a href="' . $domain . '/calendar/facilitators/add/' . $id . '" style="text-decoration:none;">here</a></h3>
        </body>
        </html> ';
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .= "From: {$from}" . "\r\n";
        $ok = mail($to, $subject, $message, $headers);
        if ($ok) {
            echo "Mail sent!";
        } else {
            echo "Error sending mail";
        }
        return;
    }
);
$app->get(
    "/calendar/facilitators/add/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {

        $config = Configuration::get_configuration('calendar');
        if (!$config["facilitator"]) {
            return;
        }
        $user = MorgueAuth::get_auth_data();
        $facilitator = array();
        $facilitator['name'] = $user['username'];
        $facilitator['email'] = Contact::get_email_for_user($user['username']);
        $conn = Persistence::get_database_object();
        $error = Calendar::set_facilitator($id, $facilitator, $conn);
        if (!$error) {
            $userHtml = Contact::get_html_for_user($user['username']);
            $to = implode(", ", $config["facilitators_email"]);
            $from = "Morgue <morgue@etsy.com>";
            $subject = "Facilitator needed [PM-{$id}]";
            $message = '
            <html>
            <head>
              <title>Facilitator Needed for PM-' . $id . '</title>
            </head>
            <body style="font-family: \'Helvetica Neue\', Helvetica,Arial, sans-serif;">
              <h3>' . $userHtml . ' will facilitate this post-mortem!</h3>
            </body>
            </html>';
            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
            $headers .= "From: {$from}" . "\r\n";
            $ok = mail($to, $subject, $message, $headers);
            $app->redirect('/', '/events/' . $id . '#calendar');
        } else {
            return $response->withStatus(500);
        }
        return;
    }
);
