<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$app->get(
    '/events/{id}/slack-channels',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int) $args['id'];
        header("Content-Type: application/json");
        $channels = Slack::get_slack_channels_for_event($id);
        if ($channels["status"] == Slack::ERROR) {
            $response->withStatus(404);
            return;
        }
        echo json_encode($channels["values"]);
    }
);
$app->post(
    '/events/{id}/slack-channels',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        header("Content-Type: application/json");
        $channel_id = $request->getParsedBody()['channel_id'];
        $channel_name = $request->getParsedBody()['channel_name'];
        $res = Slack::save_slack_channels_for_event($id, $channel_id, $channel_name);
        if ($res["status"] == Slack::ERROR) {
            $response->withStatus(400);
            return;
        }
        $response->withStatus(201);
        $channels = Slack::get_slack_channels_for_event($id);
        if ($channels["status"] == Slack::ERROR) {
            $response->withStatus(404);
            return;
        }
        echo json_encode($channels["values"]);
    }
);
$app->get(
    '/events/{id}/slack-channels/{channel}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $channel = $args['channel'];
        header("Content-Type: application/json");
        $chan = Slack::get_channel($channel);
        if ($chan["status"] == Slack::ERROR) {
            $response->withStatus(404);
            return;
        }
        echo json_encode($chan["value"]);
    }
);
$app->delete(
    '/events/{id}/slack-channels/{channel}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        $channel = $args['channel'];
        header("Content-Type: application/json");
        $res = Slack::delete_channel($channel);
        if ($res["status"] == Slack::ERROR) {
            $response->withStatus(500);
            echo json_encode($res["error"]);
            return;
        }
        $response->withStatus(204);
    }
);
$app->get(
    '/events/{id}/slack-channels-messages/{starttime}/{endtime}',
    function (ServerRequestInterface $request, ResponseInterface $response, $args) use ($app) {
        $id = (int)$args['id'];
        header("Content-Type: application/html");
        $channels = Slack::get_slack_channels_for_event($id);
        if ($channels["status"] == Slack::ERROR) {
            $response->withStatus(404);
            return;
        }
        $starttime = str_replace("-", "/", $args['starttime']);
        $newStartDateTimeArr = date_parse($starttime);
        $newStartDateTime = $newStartDateTimeArr['year'] . "-" . $newStartDateTimeArr['month'] . "-" . $newStartDateTimeArr['day'] . " " . $newStartDateTimeArr['hour'] . ":" . $newStartDateTimeArr['minute'];
        $endtime = str_replace("-", "/", $args['endtime']);
        $newEndDateTimeArr = date_parse($endtime);
        $newEndDateTime = $newEndDateTimeArr['year'] . "-" . $newEndDateTimeArr['month'] . "-" . $newEndDateTimeArr['day'] . " " . $newEndDateTimeArr['hour'] . ":" . $newEndDateTimeArr['minute'];
        $curlClient = new CurlClient();
        $slack = new Slack($curlClient);
        $channels_for_event = $channels["values"];
        $returnStr = '';
        foreach ($channels_for_event as $channelInfo) {
            $channel_id = $channelInfo['channel_id'];
            $channel_name = $channelInfo['channel_name'];
            $message = '';
            $message .= '<h4>Conversation from #' . $channel_name . '</h4><div class="messages" id="' . $channel_id . '-message-div">';
            $message .= $slack->get_channel_messages_for_datetime_range($newStartDateTime, $newEndDateTime, $channel_id);
            $message .= '<div>';
            $messageUpdate = Slack::update_slack_channel_message($channel_id, $message);
            if ($messageUpdate["status"] == Slack::ERROR) {
                $response->withStatus(500);
                echo json_encode($messageUpdate["error"]);
                return;
            }
            $returnStr .= $message;
        }
        echo $returnStr;
    }
);
