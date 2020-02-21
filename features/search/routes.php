<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/search',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {
        $q = $request->getQueryParams()['q'];
        $q = urldecode($q);
        if ($q === null || $q === "" || $q === "\"\"") {
            $app->redirect('/', '/');
        } else {
            $results = Search::perform($q);
            $content = "search/views/search";
            $show_sidebar = false;
            $page_title = "Search Results";
            include "views/page.php";
        }
    }
);