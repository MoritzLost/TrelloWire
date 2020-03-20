<?php
namespace ProcessWire\TrelloWire;

use ProcessWire\Wire;
use ProcessWire\WireHttp;

class TrelloWireApi extends Wire
{
    protected $ApiKey;
    protected $ApiToken;

    public const API_BASE = 'https://api.trello.com/1/';

    public function __construct(string $ApiKey, string $ApiToken)
    {
        $this->ApiKey = $ApiKey;
        $this->ApiToken = $ApiToken;
    }

    public function get(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'GET', $data);
    }

    public function post(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'POST', $data);
    }

    public function put(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'PUT', $data);
    }

    public function delete(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'DELETE', $data);
    }

    protected function send(string $endpoint, string $method = 'GET', array $data = [])
    {
        $WireHttp = new WireHttp();
        $url = self::API_BASE . ltrim($endpoint, '/');
        $data = array_merge(
            ['key' => $this->ApiKey, 'token' => $this->ApiToken],
            $data
        );
        switch ($method) {
            case 'GET':
                return $WireHttp->get($url, $data);
            case 'POST';
                return $WireHttp->post($url, $data);
            case 'PUT':
            case 'DELETE':
                return $WireHttp->send($url, $data, $method);
            default:
                throw new \InvalidArgumentException(sprintf($this->_('Invalid HTTP requested method: %s'), $method));
        }
    }

    public function isValidToken(): bool
    {
        return $this->get(sprintf('tokens/%s?fields=dateExpires&webhooks=false', $this->ApiToken)) !== false;
    }

    // @TODO: add params
    public function boards(): array
    {
        return json_decode($this->get('members/me/boards?fields=id,name,idOrganization&filter=open'));
    }
}
