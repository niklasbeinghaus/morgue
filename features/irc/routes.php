<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/events/{id}/channels',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        header("Content-Type: application/json");
        $channels = Irc::get_irc_channels_for_event($id);
        if ($channels["status"] == Irc::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            echo json_encode($channels["values"]);
        }
    }
);
$app->post(
    '/events/{id}/channels',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        header("Content-Type: application/json");
        $channels = $request->getParsedBody()['channels'];
        $channels = explode(",", $channels);
        $channels = array_map('trim', $channels);
        $id = (int)$args['id'];
        $res = Irc::save_irc_channels_for_event($id, $channels);
        if ($res["status"] == Irc::ERROR) {
            $response->withStatus(400);
        } else {
            $response->withStatus(201);
            $channels = Irc::get_irc_channels_for_event($id);
            if ($channels["status"] == Irc::ERROR) {
                $response->withStatus(404);
                return;
            } else {
                echo json_encode($channels["values"]);
            }
        }
    }
);
$app->get(
    '/events/{id}/channels/{channel}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $channel = $args['channel'];
        header("Content-Type: application/json");
        $chan = Irc::get_channel($channel);
        if ($chan["status"] == Irc::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            echo json_encode($chan["value"]);
        }
    }
);
$app->delete(
    '/events/{id}/channels/{channel}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $channel = $args['channel'];
        header("Content-Type: application/json");
        $res = Irc::delete_channel($channel);
        if ($res["status"] == Irc::ERROR) {
            $response->withStatus(500);
            echo json_encode($res["error"]);
        } else {
            $response->withStatus(204);
        }
    }
);

