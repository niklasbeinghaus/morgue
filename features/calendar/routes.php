<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    "/calendar",
    function () use ($app) {

        $content = "../features/calendar/views/calendar_page";
        $show_sidebar = false;
        $page_title = "Post Mortem Calendar";
        include __DIR__ . "/../../views/page.php";
    }
);
$app->get(
    "/calendar/facilitators/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        $conn = Persistence::get_database_object();
        $facilitator = Calendar::get_facilitator($id, $conn);
        if ($facilitator["status"] === Persistence::OK) {
            if (count($facilitator["values"]) === 1) {
                $response->getBody()->write(json_encode($facilitator["values"][0]));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                $response->getBody()->write(json_encode($facilitator["values"]));
                return $response->withHeader('Content-Type', 'application/json');
            }
        } else {
            return $response->withStatus(404);
        }
    }
);
$app->post(
    "/calendar/facilitators/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        $conn = Persistence::get_database_object();
        $facilitator = [
            "name" => $request->getParsedBody()['name'],
            "email" => $request->getParsedBody()['email']
        ];
        $error = Calendar::set_facilitator($id, $facilitator, $conn);
        if (!$error) {
            $response->getBody()->write(json_encode($facilitator));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } else {
            $response->getBody()->write(json_encode($error));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
);
$app->get(
    "/calendar/facilitators/request/{id}",
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $config = Configuration::get_configuration('calendar');
        if (!$config["facilitator"]) {
            return $response->withStatus(404);
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
            return $response->withStatus('200');
        } else {
            return $response->withStatus('500');
        }
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
        } else {
            return $response->withStatus(500);
        }
        $app->redirect('/', '/events/' . $id . '#calendar');
    }
);
