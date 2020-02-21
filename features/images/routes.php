<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/events/{id}/images',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        header("Content-Type: application/json");
        $images = Images::get_images_for_event($id);
        if ($images["status"] == Images::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            $output = json_encode($images["values"]);
            echo str_replace("\\/", "/", $output);
        }
    }
);
$app->post(
    '/events/{id}/images',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        header("Content-Type: application/json");
        $images = $request->getParsedBody()['images'];
        $images = explode(",", $images);
        $images = array_map('trim', $images);
        $res = Images::save_images_for_event($id, $images);
        if ($res["status"] == Images::ERROR) {
            $response->withStatus(400);
        } else {
            $response->withStatus(201);
            $images = Images::get_images_for_event($id);
            if ($images["status"] == Images::ERROR) {
                $response->withStatus(404);
                return;
            } else {
                $output = json_encode($images["values"]);
                echo str_replace("\\/", "/", $output);
            }
        }
    }
);
$app->get(
    '/events/{id}/images/{img}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id, $img) use ($app) {
        header("Content-Type: application/json");
        $id = (int)$id;
        $img = (int)$img;
        $image = Images::get_image($img);
        if ($image["status"] == Images::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            echo json_encode($image["value"]);
        }
    }
);
$app->delete(
    '/events/{id}/images/{image}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id, $image) use ($app) {
        $id = (int)$id;
        $image = (int)$image;
        header("Content-Type: application/json");
        $res = Images::delete_image($image);
        if ($res["status"] == Images::ERROR) {
            $response->withStatus(500);
            echo json_encode($res["error"]);
        } else {
            $response->withStatus(204);
        }
    }
);
