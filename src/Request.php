<?php
declare(strict_types=1);

namespace Comet;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;;
use Psr\Http\Message\UploadedFileInterface;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * Fast PSR-7 ServerRequest implementation
 * @package Comet
 */
class Request extends GuzzleRequest implements ServerRequestInterface
{
    /** @var array */
    private $attributes = [];

    /** @var array */
    private $cookieParams = [];

    /** @var null|array|object */
    private $parsedBody;

    /** @var array */
    private $queryParams = [];

    /** @var array */
    private $serverParams;

    /** @var array */
    private $uploadedFiles = [];

    /** @var Comet\Session */
    public $session = null;

    /**
     * Request constructor
     *
     * @param string $httpBuffer
     */
    public function __construct($httpBuffer) {
        $request = new WorkermanRequest($httpBuffer);
        $headers = $request->header();

        // Sanitize URI to avoid exceptions
        $uri = preg_replace('~//+~', '/', $request->uri());

        parent::__construct(
            $request->method(),
            $uri,
            $headers,
            $request->rawBody(),
            '1.1'
        );

        $this->serverParams = $_SERVER;
        $this->uploadedFiles = $request->file();
        $this->queryParams = $request->get();
        $this->cookieParams = $request->cookie();

        // --- Parse POST forms and JSON bodies

        if (array_key_exists('content-type', $headers)) {
            if ($headers['content-type'] == 'application/json') {
                $this->parsedBody = json_decode($request->rawBody(), true);
            } else if ($headers['content-type'] == 'application/x-www-form-urlencoded') {
                \parse_str($request->rawBody(), $this->parsedBody);
            }
        }

        // Wake up active or create new shadow session
        $defaultSessionName = Session::sessionName();
        if (array_key_exists($defaultSessionName, $this->cookieParams)) {
            $session_id = $this->cookieParams[$defaultSessionName];
            $this->session = new Session($session_id);
        } else {
            $this->session = new Session();
        }
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files A array which respect $_FILES structure
     * @throws InvalidArgumentException for unrecognized values
     * @return array
     */
    public static function normalizeFiles(array $files)
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = self::createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalizeFiles($value);
                continue;
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an UploadedFile instance from a $_FILES specification.
     *
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array $value $_FILES struct
     * @return array|UploadedFileInterface
     */
    private static function createUploadedFileFromSpec(array $value)
    {
        if (is_array($value['tmp_name'])) {
            return self::normalizeNestedFileSpec($value);
        }

        return new UploadedFile(
            $value['tmp_name'],
            (int) $value['size'],
            (int) $value['error'],
            $value['name'],
            $value['type']
        );
    }

    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @param array $files
     * @return UploadedFileInterface[]
     */
    private static function normalizeNestedFileSpec(array $files = [])
    {
        $normalizedFiles = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size'     => $files['size'][$key],
                'error'    => $files['error'][$key],
                'name'     => $files['name'][$key],
                'type'     => $files['type'][$key],
            ];
            $normalizedFiles[$key] = self::createUploadedFileFromSpec($spec);
        }

        return $normalizedFiles;
    }

    /**
     * We should not allow creating requests from GLOBALS with Workerman-based framework
     *
     * @throws InvalidArgumentException 
     */
    public static function fromGlobals()
    {
    	throw new InvalidArgumentException('Do not use fromGlobals() method for Comet\Request objects!');
    }

    private static function extractHostAndPortFromAuthority($authority)
    {
        $uri = 'http://'.$authority;
        $parts = parse_url($uri);
        if (false === $parts) {
            return [null, null];
        }

        $host = isset($parts['host']) ? $parts['host'] : null;
        $port = isset($parts['port']) ? $parts['port'] : null;

        return [$host, $port];
    }

    /**
     * DEPRECATED
     * Get a Uri populated with values from $_SERVER.
     *
     * @return UriInterface
     */
    public static function getUriFromGlobals()
    {
        $uri = new Uri('');

        $uri = $uri->withScheme(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');

        $hasPort = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            list($host, $port) = self::extractHostAndPortFromAuthority($_SERVER['HTTP_HOST']);
            if ($host !== null) {
                $uri = $uri->withHost($host);
            }

            if ($port !== null) {
                $hasPort = true;
                $uri = $uri->withPort($port);
            }
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $uri = $uri->withHost($_SERVER['SERVER_ADDR']);
        }

        if (!$hasPort && isset($_SERVER['SERVER_PORT'])) {
            $uri = $uri->withPort($_SERVER['SERVER_PORT']);
        }

        $hasQuery = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $requestUriParts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $uri = $uri->withQuery($requestUriParts[1]);
            }
        }

        if (!$hasQuery && isset($_SERVER['QUERY_STRING'])) {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }

        return $uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams()
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $this->uploadedFiles = $uploadedFiles;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies)
    {
        $this->cookieParams = $cookies;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query)
    {
        $this->queryParams = $query;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data)
    {
        $this->parsedBody = $data;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($attribute, $default = null)
    {
        if (false === array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    /**
     * Efficient way to set up attributes without cloning request
     *
     * @param $attribute
     * @param $value
     * @return Request
     */
    public function setAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($attribute)
    {
        if (false === array_key_exists($attribute, $this->attributes)) {
            return $this;
        }

        unset($this->attributes[$attribute]);

        return $this;

    }

    /**
     * Get session
     *
     * @return \Comet\Session
     */
    public function getSession()
    {
        if ($this->session === null) {
            $this->session = new Session();
        }

        return $this->session;
    }
}
