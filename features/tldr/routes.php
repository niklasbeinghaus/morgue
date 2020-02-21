<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get('/events/{id}/tldr', function(ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        $event = Postmortem::get_event($id);
        if (is_null($event["id"])) {
            $response->withStatus(404);
            return;
        }
        header("Content-Type: application/json");
        echo json_encode(array("tldr" => $event["tldr"]));
});
