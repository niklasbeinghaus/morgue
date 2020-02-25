<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get('/events/{id}/tldr', function(ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        $event = Postmortem::get_event($id);
        if (is_null($event['id'])) {
            return $response->withStatus(404);
        }
        $response->getBody()->write(json_encode($event['tldr']));
        return $response->withHeader('Content-Type', 'application/json');
});
