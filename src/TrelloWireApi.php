<?php
namespace ProcessWire\TrelloWire;

use ProcessWire\Wire;
use ProcessWire\WireHttp;

class TrelloWireApi
{
    public const API_BASE = 'https://api.trello.com/1/';

    protected $ApiKey;
    protected $ApiToken;

    public $lastRequest;

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
        $this->lastRequest = $WireHttp;
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
        $this->get(sprintf('tokens/%s?fields=dateExpires&webhooks=false', $this->ApiToken));
        $httpResponseCode = $this->lastRequest->getHttpCode();
        return $httpResponseCode >= 200 & $httpResponseCode < 300;
    }

    public function boards(): array
    {
        // @TODO: add params: fields, filter
        $result = $this->get('members/me/boards?fields=id,name,idOrganization&filter=open');
        $responseCode = $this->lastRequest->getHttpCode();
        if (!$this->isResponseCodeOk($responseCode)) {
            throw new \InvalidArgumentException(sprintf($this->_('Error retrieving Trello boards. HTTP Code: %d'), $responseCode));
        }
        return json_decode($result);
    }

    public function lists(string $idBoard): array
    {
        // @TODO: add params: fields, filter
        $result = $this->get("boards/${idBoard}/lists?cards=none&filter=open&fields=id,name,pos");
        if (!$this->isResponseCodeOk($this->lastRequest->getHttpCode())) {
            throw new \InvalidArgumentException(sprintf($this->_('Board with ID %s does not appear to exist.'), $idBoard));
        }
        return json_decode($result);
    }

    public function postCard(string $idList, string $title, string $body = '', array $addData = []): bool
    {
        $this->post('cards', array_merge(
            $addData,
            [
                'name' => $title,
                'desc' => $body,
                'idList' => $idList
            ]
        ));
        return $this->isResponseCodeOk($this->lastRequest->getHttpCode());
    }

    public function isResponseCodeOk(int $httpCode): bool
    {
        return $httpCode >= 200 && $httpCode < 300;
    }
}
