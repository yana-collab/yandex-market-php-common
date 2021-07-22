<?php
/**
 * Yandex PHP Library
 *
 * @copyright NIX Solutions Ltd.
 * @link https://github.com/nixsolutions/yandex-php-library
 */

/**
 * @namespace
 */

namespace Yandex\Common;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Yandex\Common\Exception\MissedArgumentException;
use Yandex\Common\Exception\ProfileNotFoundException;
use Yandex\Common\Exception\YandexException;

/**
 * Class AbstractServiceClient
 *
 * @package Yandex\Common
 *
 * @author   Eugene Zabolotniy <realbaziak@gmail.com>
 * @created  13.09.13 12:09
 */
abstract class AbstractServiceClient extends AbstractPackage
{
    /**
     * Request schemes constants
     */
    const HTTPS_SCHEME = 'https';
    const HTTP_SCHEME = 'http';

    const DECODE_TYPE_JSON = 'json';
    const DECODE_TYPE_XML = 'xml';
    const DECODE_TYPE_DEFAULT = self::DECODE_TYPE_JSON;

    /**
     * @var string
     */
    protected $serviceScheme = self::HTTPS_SCHEME;

    /**
     * Can be HTTP 1.0 or HTTP 1.1
     *
     * @var string
     */
    protected $serviceProtocolVersion = '1.1';

    /**
     * @var string
     */
    protected $serviceDomain = '';

    /**
     * @var string
     */
    protected $servicePort = '';

    /**
     * @var string
     */
    protected $accessToken = '';


    /**
     * @var \DateTime
     */
    protected $expiresIn;

    /**
     * @var string
     */
    protected $proxy = '';

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var null
     */
    protected $client;

    /**
     * @var string
     */
    protected $libraryName = 'yandex-php-library';

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @return \DateTime
     */
    public function getExpiresIn()
    {
        return $this->expiresIn;
    }

    /**
     * @param \DateTime $expiresIn
     */
    public function setExpiresIn($expiresIn)
    {
        $this->expiresIn = $expiresIn;
    }

    /**
     * @return string
     */
    public function getServicePort()
    {
        return $this->servicePort;
    }

    /**
     * @param string $servicePort
     *
     * @return self
     */
    public function setServicePort($servicePort)
    {
        $this->servicePort = $servicePort;

        return $this;
    }

    /**
     * @return string
     */
    public function getServiceScheme()
    {
        return $this->serviceScheme;
    }

    /**
     * @param string $serviceScheme
     *
     * @return self
     */
    public function setServiceScheme($serviceScheme = self::HTTPS_SCHEME)
    {
        $this->serviceScheme = $serviceScheme;

        return $this;
    }

    /**
     * Check package configuration
     *
     * @return boolean
     */
    protected function doCheckSettings()
    {
        return true;
    }

    /**
     * Sends a request
     *
     * @param string $method HTTP method
     * @param string $uri URI object or string.
     * @param array  $options Request options to apply.
     *
     * @throws Exception\MissedArgumentException
     * @throws Exception\ProfileNotFoundException
     * @throws Exception\YandexException
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function sendRequest($method, $uri, array $options = [])
    {
        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (ClientException $ex) {
            // get error from response
            $decodedResponseBody = $this->getDecodedBody($ex->getResponse()->getBody());
            $code = $ex->getResponse()->getStatusCode();

            // handle a service error message
            if (is_array($decodedResponseBody) && isset($decodedResponseBody['error'], $decodedResponseBody['message'])
            ) {
                switch ($decodedResponseBody['error']) {
                    case 'MissedRequiredArguments':
                        throw new MissedArgumentException($decodedResponseBody['message']);
                    case 'AssistantProfileNotFound':
                        throw new ProfileNotFoundException($decodedResponseBody['message']);
                    default:
                        throw new YandexException($decodedResponseBody['message'], $code);
                }
            }

            // unknown error
            throw $ex;
        }
        return $response;
    }

    /**
     * @param array|null $headers
     * @return ClientInterface
     */
    protected function getClient($headers = null)
    {
        if ($this->client === null) {
            $defaultOptions = [
                'base_uri' => $this->getServiceUrl(),
                'headers' => [
                    'Authorization' => 'OAuth ' . $this->getAccessToken(),
                    'Host' => $this->getServiceDomain(),
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => '*/*',
                ],
            ];
            if ($headers && is_array($headers)) {
                $defaultOptions["headers"] += $headers;
            }
            if ($this->getProxy()) {
                $defaultOptions['proxy'] = $this->getProxy();
            }
            if ($this->getDebug()) {
                $defaultOptions['debug'] = $this->getDebug();
            }
            $this->client = new Client($defaultOptions);
        }

        return $this->client;
    }

    /**
     * @param ClientInterface $client
     *
     * @return $this
     */
    protected function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public function getServiceUrl($resource = '')
    {
        return $this->serviceScheme . '://' . $this->serviceDomain . '/' . rawurlencode($resource);
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     *
     * @return self
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getServiceDomain()
    {
        return $this->serviceDomain;
    }

    /**
     * @param string $serviceDomain
     *
     * @return self
     */
    public function setServiceDomain($serviceDomain)
    {
        $this->serviceDomain = $serviceDomain;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        if (!$this->userAgent) {
            return $this->libraryName . '/' . Version::$version;
        }
        return $this->userAgent;
    }

    /**
     * @param $userAgent
     *
     * @return $this
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * @return string
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * @param $proxy
     *
     * @return $this
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param $debug
     *
     * @return $this
     */
    public function setDebug($debug)
    {
        $this->debug = (bool)$debug;

        return $this;
    }

    /**
     * @param        $body
     * @param string $type
     *
     * @return mixed|\SimpleXMLElement
     */
    protected function getDecodedBody($body, $type = null)
    {
        if (!isset($type)) {
            $type = static::DECODE_TYPE_DEFAULT;
        }
        switch ($type) {
            case self::DECODE_TYPE_XML:
                return simplexml_load_string((string)$body);
            case self::DECODE_TYPE_JSON:
            default:
                return json_decode((string)$body, true);
        }
    }
}
