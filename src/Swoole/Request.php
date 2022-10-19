<?php

namespace Utopia\Swoole;

use Utopia\Request as UtopiaRequest;
use Swoole\Http\Request as SwooleRequest;

class Request extends UtopiaRequest
{
    /**
     * Swoole Request Object
     *
     * @var SwooleRequest
     */
    protected SwooleRequest $swoole;

    /**
     * Request constructor.
     */
    public function __construct(SwooleRequest $request)
    {
        $this->swoole = $request;
    }

    /**
     *
     *
     */
    public function getRawPayload(): string
    {
        return $this->swoole->rawContent();
    }

    /**
     * Get server
     *
     * Method for querying server parameters. If $key is not found $default value will be returned.
     *
     * @param string $key
     * @param string|null  $default
     * @return string|null
     */
    public function getServer(string $key, string $default = null): ?string
    {
        return $this->swoole->server[$key] ?? $default;
    }

    public function setServer(string $key, string $value): UtopiaRequest
    {
        $this->swoole->server[$key] = $value;

        return $this;
    }

    /**
     * Get IP
     *
     * Returns users IP address.
     * Support HTTP_X_FORWARDED_FOR header usually return
     *  from different proxy servers or PHP default REMOTE_ADDR
     */
    public function getIP(): string
    {
        $ips = explode(',', $this->getHeader('x-forwarded-for', $this->getServer('remote_addr') ?? '0.0.0.0'));

        return trim($ips[0] ?? '');
    }

    /**
     * Get Protocol
     *
     * Returns request protocol.
     * Support HTTP_X_FORWARDED_PROTO header usually return
     *  from different proxy servers or PHP default REQUEST_SCHEME
     *
     * @return string
     */
    public function getProtocol(): string
    {
        $protocol = $this->getHeader('x-forwarded-proto', $this->getServer('server_protocol') ?? 'https');

        if ($protocol === 'HTTP/1.1') {
            return 'http';
        }

        return match ($protocol) {
            'http', 'https', 'ws', 'wss' => $protocol,
            default => 'https'
        };
    }

    /**
     * Get Port
     *
     * Returns request port.
     *
     * @return string
     */
    public function getPort(): string
    {
        return $this->getHeader('x-forwarded-port', (string)\parse_url($this->getProtocol() . '://' . $this->getHeader('x-forwarded-host', $this->getHeader('host')), PHP_URL_PORT));
    }

    /**
     * Get Hostname
     *
     * Returns request hostname.
     *
     * @return string
     */
    public function getHostname(): string
    {
        return \parse_url($this->getProtocol() . '://' . $this->getHeader('x-forwarded-host', $this->getHeader('host')), PHP_URL_HOST);
    }

    /**
     * Get Method
     *
     * Return HTTP request method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->getServer('request_method') ?? 'UNKNOWN';
    }

    public function setMethod(string $method): UtopiaRequest
    {
        $this->setServer('request_method', $method);

        return $this;
    }

    /**
     * Get URI
     *
     * Return HTTP request URI
     *
     * @return string
     */
    public function getURI(): string
    {
        return $this->getServer('request_uri') ?? '';
    }

    public function setURI(string $uri): UtopiaRequest
    {
        $this->setServer('request_uri', $uri);

        return $this;
    }

    /**
     * Get Referer
     *
     * Return HTTP referer header
     *
     * @return string
     */
    public function getReferer(string $default = ''): string
    {
        return $this->getHeader('referer', '');
    }

    /**
     * Get Origin
     *
     * Return HTTP origin header
     *
     * @return string
     */
    public function getOrigin(string $default = ''): string
    {
        return $this->getHeader('origin', $default);
    }

    /**
     * Get User Agent
     *
     * Return HTTP user agent header
     *
     * @return string
     */
    public function getUserAgent(string $default = ''): string
    {
        return $this->getHeader('user-agent', $default);
    }

    /**
     * Get Accept
     *
     * Return HTTP accept header
     *
     * @return string
     */
    public function getAccept(string $default = ''): string
    {
        return $this->getHeader('accept', $default);
    }

    /**
     * Get files
     *
     * Method for querying upload files data. If $key is not found empty array will be returned.
     *
     * @param string $key
     * @return array
     */
    public function getFiles($key): array
    {
        $key = strtolower($key);

        return $this->swoole->files[$key] ?? [];
    }

    /**
     * Get cookie
     *
     * Method for querying HTTP cookie parameters. If $key is not found $default value will be returned.
     *
     * @param string $key
     * @param string  $default
     * @return string
     */
    public function getCookie(string $key, string $default = ''): string
    {
        $key = strtolower($key);

        return $this->swoole->cookie[$key] ?? $default;
    }

    /**
     * Get header
     *
     * Method for querying HTTP header parameters. If $key is not found $default value will be returned.
     *
     * @param  string $key
     * @param  string   $default
     * @return string 
     */
    public function getHeader(string $key, string $default = ''): string
    {
        return $this->swoole->header[$key] ?? $default;
    }

    /**
     * Method for adding HTTP header parameters.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setHeader(string $key, string $value): void
    {
        $this->swoole->header[$key] = $value;
    }

    /**
     * Method for removing HTTP header parameters.
     *
     * @param string $key
     * @return void
     */
    public function removeHeader(string $key): void
    {
        if (isset($this->swoole->header[$key])) {
            unset($this->swoole->header[$key]);
        }
    }

    /**
     * Generate input
     *
     * Generate PHP input stream and parse it as an array in order to handle different content type of requests
     *
     * @return array
     */
    protected function generateInput(): array
    {
        if (null === $this->payload) {
            $contentType    = $this->getHeader('content-type');

            // Get content-type without the charset
            $length         = strpos($contentType, ';');
            $length         = (empty($length)) ? strlen($contentType) : $length;
            $contentType    = substr($contentType, 0, $length);

            switch ($contentType) {
                case 'application/json':
                    $this->payload = json_decode($this->swoole->rawContent(), true);
                    break;

                default:
                    $this->payload = $this->swoole->post;
                    break;
            }

            if (empty($this->payload)) { // Make sure we return same data type even if json payload is empty or failed
                $this->payload = [];
            }
        }

        return $this->payload;
    }

    /**
     * Generate headers
     *
     * Parse request headers as an array for easy querying using the getHeader method
     *
     * @return array
     */
    protected function generateHeaders(): array
    {
        return $this->swoole->header;
    }
}
