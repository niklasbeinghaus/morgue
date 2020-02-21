<?php
/**
 * Routes for upload
 */
/*
 * This is to handle images coming in via our dropzone
 *
 * We assume that these images are to be associated with an event.
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->post(
    '/upload/{id}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        $id = (int)$id;
        $ds = DIRECTORY_SEPARATOR;
        $config = Configuration::get_configuration();
        $upload_base_path = $config['upload_dir'];
        if (!empty($_FILES)) {
            // Step One: Put our uploaded files somewhere
            $tempFile = $_FILES['file']['tmp_name'];
            $targetPath = "{$upload_base_path}{$ds}{$id}{$ds}";
            // Ensure we have somewhere to upload to
            // We're grouping uploades by their associated event so
            // we're making directories here
            shell_exec("mkdir -p $targetPath");
            $targetFile = $targetPath . $_FILES['file']['name'];
            if (!move_uploaded_file($tempFile, $targetFile)) {
                $response->withStatus(500);
            }
            $options = Configuration::get_configuration('upload');
            // Step Two: Send the file somewhere and expect a URL back
            $uploader = new Uploader($options);
            try {
                $response = $uploader->send_file($targetFile, $id);
            } catch (Exception $e) {
                print $e->getMessage();
                return;
            }
            $location = $response['location'];
            // we should have the $location of our uploaded file
            if (empty($location)) {
                throw new Exception("Upload expected an image location");
            }
            // Step Three: Add the URL of the file as an image for the event
            // Lucky us, we just have to call renderImage on the front
            // end. That will save the image to the the database.
            header("Content-Type: application/json");
            echo json_encode(array("location" => $location));
        } else {
            $response->withStatus(400);
            return;
        }
    }
);


