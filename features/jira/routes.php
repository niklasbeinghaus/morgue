<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/events/{id}/tickets',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        header("Content-Type: application/json");
        $id = (int)$id;
        $tickets = Jira::get_jira_tickets_for_event($id);
        if ($tickets["status"] == Jira::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            $tickets = Jira::merge_jira_tickets($tickets["values"]);
            echo json_encode($tickets);
        }
    }
);
$app->post(
    '/events/{id}/tickets',
    function (ServerRequestInterface $request, ResponseInterface $response, $id) use ($app) {
        $id = (int)$id;
        header("Content-Type: application/json");
        $curl = new CurlClient();
        $jira = new JiraClient($curl);
        $tickets = explode(',', $app->request()->post('tickets'));
        $tickets = array_map('trim', $tickets);
        $tickets = array_keys($jira->getJiraTickets($tickets));
        $res = Jira::save_jira_tickets_for_event($id, $tickets);
        if ($res["status"] == Jira::ERROR) {
            $response->withStatus(400);
        } else {
            $response->withStatus(201);
            $tickets = Jira::get_jira_tickets_for_event($id);
            if ($tickets["status"] == Jira::ERROR) {
                $response->withStatus(404);
                return;
            } else {
                $tickets = Jira::merge_jira_tickets($tickets["values"]);
                echo json_encode($tickets);
            }
        }
    }
);
$app->get(
    '/events/{id}/tickets/{ticket}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id, $ticket) use ($app) {
        $id = (int)$id;
        $ticket = (int)$ticket;
        header("Content-Type: application/json");
        $tick = Jira::get_ticket($ticket);
        if ($tick["status"] == Jira::ERROR) {
            $response->withStatus(404);
            return;
        } else {
            echo json_encode($tick["value"]);
        }
    }
);
$app->delete(
    '/events/{id}/tickets/{ticket}',
    function (ServerRequestInterface $request, ResponseInterface $response, $id, $ticket) use ($app) {
        $id = (int)$id;
        header("Content-Type: application/json");
        $res = Jira::delete_ticket($ticket);
        if ($res["status"] == Jira::ERROR) {
            $response->withStatus(500);
            echo json_encode($res["error"]);
        } else {
            $response->withStatus(204);
        }
    }
);
