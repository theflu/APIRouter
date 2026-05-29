<?php

namespace APIRouter;

class ServerRequestFactory
{
    static public function withGlobals(): ServerRequest
    {
        $server = $_SERVER;

        if (!isset($server['REQUEST_METHOD'])) {
            $server['REQUEST_METHOD'] = 'GET';
        }

        $server_request = (new ServerRequest($server['REQUEST_METHOD'], null, file_get_contents('php://input'), $server['SERVER_PROTOCOL']))
            ->withQueryParams($_GET);

        if ($server['REQUEST_METHOD'] == 'POST' && isset($_POST)) {
            $server_request = $server_request->withParsedBody($_POST);
        }

        return $server_request;
    }
}