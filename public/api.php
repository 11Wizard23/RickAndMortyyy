<?php
declare(strict_types=1);

/**
 * Front controller for the JSON endpoints consumed by assets/app.js.
 *
 *   GET  api.php?action=list&page=1&name=&status=&species=&gender=&scope=all
 *   POST api.php?action=star&id=1
 *   POST api.php?action=delete&id=1
 *   POST api.php?action=restore
 *
 * Its only jobs are wiring, dispatch, and turning exceptions into status codes.
 */

require dirname(__DIR__) . '/src/autoload.php';

use RickAndMorty\Container;
use RickAndMorty\Exception\BadRequestException;
use RickAndMorty\Exception\TransportException;
use RickAndMorty\Exception\UnknownActionException;
use RickAndMorty\Exception\UpstreamException;
use RickAndMorty\Web\Request;

header('Content-Type: application/json; charset=utf-8');

$request = Request::fromGlobals();

try {
    $payload = (new Container())->action($request->action())->handle($request);
    $status  = 200;
} catch (BadRequestException $e) {
    [$payload, $status] = [['error' => $e->getMessage()], 400];
} catch (UnknownActionException $e) {
    [$payload, $status] = [['error' => $e->getMessage()], 404];
} catch (UpstreamException | TransportException $e) {
    // Already retried; this is the upstream genuinely being unavailable.
    [$payload, $status] = [['error' => $e->getMessage()], 502];
}

http_response_code($status);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
