<?php

namespace App\Websocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Illuminate\Support\Facades\Redis;

use Exception;

class Chat implements MessageComponentInterface {
    protected $clients;
    private static $instance;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        self::$instance = $this;
    }

    public static function getInstance() {
        return self::$instance;
    }

    public function onOpen(ConnectionInterface $conn) {
        try {
            // Store the new connection to send messages to later
            $this->clients->attach($conn);
            $conn->send('welcome');
            Log::v('S', $conn, 'welcome');

            $request = (array)$conn->WebSocket->request;
            $request = (array)array_get($request, "\0*\0headers");
            $request = (array)array_get($request, "\0*\0headers.user-agent");
            $request = array_get($request, "\0*\0values.0");
            $request = (empty($request)) ? 'unknown' : $request;
            Log::v('R', $conn, "new client({$conn->resourceId}) on {$conn->remoteAddress}({$request})");
        } catch (Exception $e) {
            Log::e($e);
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            Log::v('R', $from, "receiving message \"{$msg}\"");
            $numRecv = count($this->clients) - 1;
            foreach ($this->clients as $client) {
                if ($from !== $client) {
                    // The sender is not the receiver, send to each client connected
                    $client->send($msg);
                    Log::v('S', $client, "sending message \"{$msg}\"");
                }
            }
        } catch (Exception $e) {
            Log::e($e);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        try {
            // The connection is closed, remove it, as we can no longer send it messages
            $this->clients->detach($conn);

            Log::v('R', $conn, 'close', "Client({$conn->resourceId}) has disconnected");
        } catch (Exception $e) {
            Log::e($e);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        Log::e($e);
        $conn->close();
    }

    public function broadcast($msg) {
        foreach ($this->clients as $client) {
            $client->send($msg);
            Log::v('S', $client, "sending message \"{$msg}\"");
        }
    }
}
