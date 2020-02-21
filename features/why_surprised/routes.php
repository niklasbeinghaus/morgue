<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/events/{id}/why_surprised',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $event = Postmortem::get_event($id);
        if (is_null($event["id"])) {
            return $response->withStatus(404);
        }
        header("Content-Type: application/json");
        echo json_encode(array("why_surprised" => $event["why_surprised"]));
    }
);