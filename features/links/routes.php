<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/events/{id}/forum_links',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        $id = (int)$id;
        header("Content-Type: application/json");
        $forum_links = Links::get_forum_links_for_event($id);
        if ($forum_links["status"] == Links::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            $output = json_encode($forum_links["values"]);
            echo str_replace("\\/", "/", $output);
        }
    }
);
$app->post(
    '/events/{id}/forum_links',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        $id = (int)$id;
        header("Content-Type: application/json");
        $forum_data = array(
            'link' => $app->request->post('forum_link'),
            'comment' => $app->request->post('forum_comment'),
            'event_id' => $id,
        );
        $res = Links::save_forum_links($forum_data); //need to find this function
        if ($res["status"] == Links::ERROR) {
            $response->withStatus(400);
        } else {
            $response->withStatus(201);
            $forum_links = Links::get_forum_links_for_event($id);
            if ($forum_links["status"] == Links::ERROR) {
                $response->withStatus(404);
                return;
            } else {
                $output = json_encode($forum_links["values"]);
                echo str_replace("\\/", "/", $output);
            }
        }
    }
);
$app->get(
    '/events/{id}/forum_links/{forum_link}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id, $forum_link) use ($app) {
        $id = (int)$id;
        header("Content-Type: application/json");
        $forum_link = Links::get_forum_link($id); //need to find this function
        if ($forum_link["status"] == Links::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            echo json_encode($forum_link["value"]);
        }
    }
);
$app->delete(
    '/events/{id}/forum_links/{forum_link}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id, $forum_link) use ($app) {
        header("Content-Type: application/json");
        $res = Links::delete_forum_link($forum_link);
        if ($res["status"] == Links::ERROR) {
            $response->withStatus(500);
            echo json_encode($res["error"]);
        } else {
            $response->withStatus(204);
        }
    }
);
