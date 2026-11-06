<?php

declare(strict_types=1);

namespace Pam\Browser\Tests\Unit;

use Pam\Browser\Transport\ByteStream;
use Pam\Browser\Transport\StreamConnector;
use Pam\Browser\Transport\WebSocketTransport;
use PHPUnit\Framework\TestCase;

final class WebSocketTransportTest extends TestCase
{
    public function testCloseSurvivesABrokenPipeAndStillClosesTheStream(): void
    {
        $stream = new BrokenPipeByteStream();
        $transport = WebSocketTransport::connect('ws://127.0.0.1:9222/devtools/browser/test', new class ($stream) implements StreamConnector {
            public function __construct(private readonly ByteStream $stream)
            {
            }

            public function connect(string $address, float $timeoutSeconds): ByteStream
            {
                return $this->stream;
            }
        });
        $stream->broken = true;

        $transport->close();

        self::assertTrue($stream->closed);
    }
}

final class BrokenPipeByteStream implements ByteStream
{
    public bool $broken = false;
    public bool $closed = false;

    /** @var list<string> */
    private array $reads = [];

    public function read(int $length, float $timeoutSeconds): string
    {
        return array_shift($this->reads) ?? '';
    }

    public function write(string $data, float $timeoutSeconds): int
    {
        if ($this->broken) {
            throw new \RuntimeException('Unable to write to stream.');
        }
        preg_match('/Sec-WebSocket-Key: ([^\r]+)/', $data, $matches);
        $accept = base64_encode(sha1(($matches[1] ?? '').'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $this->reads[] = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n";

        return strlen($data);
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
