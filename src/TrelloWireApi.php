<?php
namespace ProcessWire\TrelloWire;

use ProcessWire\Wire;
use ProcessWire\WireHttp;

class TrelloWireApi
{
    /** @var string The base URL for the Trello API. */
    public const API_BASE = 'https://api.trello.com/1/';

    /** @var WireHttp Always contains the WireHttp instance used for the last request made through this object. */
    public $lastRequest;

    /** @var string The API key for Trello. */
    protected $ApiKey;

    /** @var string The API token for Trello. */
    protected $ApiToken;

    /**
     * Construct a new API instance with an API key & token.
     *
     * @param string $ApiKey
     * @param string $ApiToken
     */
    public function __construct(string $ApiKey, string $ApiToken)
    {
        $this->ApiKey = $ApiKey;
        $this->ApiToken = $ApiToken;
    }

    /**
     * Send a GET request to the specified endpoint of the Trello API.
     *
     * @param string $endpoint  The endpoint to call, including relevant parameters, without a leading slash.
     * @param array $data       Associative array of parameters to add to this request.
     * @return mixed
     */
    public function get(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'GET', $data);
    }

    /**
     * Send a POST request to the specified endpoint of the Trello API.
     *
     * @param string $endpoint  The endpoint to post to, including relevant parameters, without a leading slash.
     * @param array $data       Associative array of data to send with this request.
     * @return mixed
     */
    public function post(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'POST', $data);
    }

    /**
     * Send a PUT request to the specified endpoint of the Trello API.
     *
     * @param string $endpoint  The endpoint to call, including relevant parameters, without a leading slash.
     * @param array $data       Associative array of data to send with this request.
     * @return mixed
     */
    public function put(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'PUT', $data);
    }

    /**
     * Send a DELETE request to the specified endpoint of the Trello API.
     *
     * @param string $endpoint  The endpoint to call, including relevant parameters, without a leading slash.
     * @param array $data       Associative array of data to send with this request.
     * @return mixed
     */
    public function delete(string $endpoint, array $data = [])
    {
        return $this->send($endpoint, 'DELETE', $data);
    }

    /**
     * Send a request to any endpoint of the Trello API. This passes requests to
     * the appropriate method of ProcessWire's WireHttp class, so check the
     * documentation for possible return values.
     *
     * @param string $endpoint  The endpoint to call, including relevant parameters, without a leading slash.
     * @param string $method    The HTTP method to use (one of GET, POST, PUT, DELETE).
     * @param array $data       Additional data to send with this request.
     * @see https://processwire.com/api/ref/wire-http/
     * @return mixed
     */
    public function send(string $endpoint, string $method = 'GET', array $data = [])
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

    /**
     * Check if the API token this instance is configured to use is valid.
     *
     * @return boolean
     */
    public function isValidToken(): bool
    {
        $this->get(sprintf('tokens/%s?fields=dateExpires&webhooks=false', $this->ApiToken));
        $httpResponseCode = $this->lastRequest->getHttpCode();
        return $httpResponseCode >= 200 & $httpResponseCode < 300;
    }

    /**
     * Get all boards belonging to the user that created the current API token.
     * 
     * @param array $fields     Board fields to include in the response.
     * @param string $filter    The filter to use for this request.
     * @return array
     */
    public function boards(array $fields = ['id', 'name'], string $filter = 'all'): array
    {
        $result = $this->get(sprintf(
            'members/me/boards?fields=%1$s&filter=%2$s',
            implode(',', $fields),
            $filter
        ));
        $responseCode = $this->lastRequest->getHttpCode();
        if (!$this->isResponseCodeOk($responseCode)) {
            throw new \InvalidArgumentException(sprintf($this->_('Error retrieving Trello boards. HTTP Code: %d'), $responseCode));
        }
        return json_decode($result);
    }

    /**
     * Get the lists in a board.
     *
     * @param string $idBoard   The ID of the board.
     * @param array $fields     The board fields to include in the response.
     * @param string $filter    The filter to use for this request.
     * @param string $cards     The type of cards to include in the response.
     * @param array $cardFields The card fields to include in the response.
     * @return array
     */
    public function lists(string $idBoard, array $fields = ['id', 'name', 'pos'], string $filter = 'open', string $cards = 'none', array $cardFields = []): array
    {
        $result = $this->get(sprintf(
            'boards/%1$s/lists?fields=%2$s&filter=%3$s&cards=%4$s&card_fields=%5$s',
            $idBoard,
            implode(',', $fields),
            $filter,
            $cards,
            implode(',', $cardFields)
        ));
        if (!$this->isResponseCodeOk($this->lastRequest->getHttpCode())) {
            throw new \InvalidArgumentException(sprintf($this->_('Board with ID %s does not appear to exist.'), $idBoard));
        }
        return json_decode($result);
    }

    /**
     * Post a new card to the specified list. Returns true on success, false on failure.
     *
     * @param string $idList    The ID of the list to add the card to.
     * @param string $title     The name / title of the card.
     * @param string $body      The description / body of the card.
     * @param array $addData    Additional fields to pass to the API.
     * @return boolean
     */
    public function postCard(string $idList, string $title, string $body = '', array $addData = [])
    {
        $result = $this->post('cards', array_merge(
            $addData,
            [
                'name' => $title,
                'desc' => $body,
                'idList' => $idList
            ]
        ));
        return $this->isResponseCodeOk($this->lastRequest->getHttpCode()) ? json_decode($result) : false;
    }

    public function updateCard(string $id, ?string $title = null, ?string $body = null, array $addData = [])
    {
        $updateFields = array_merge($addData, array_filter([
            'name' => $title,
            'desc' => $body,
        ]));
        $result = $this->put(sprintf('cards/%s', $id), $updateFields);
        return $this->isResponseCodeOk($this->lastRequest->getHttpCode()) ? json_decode($result) : false;
    }

    /**
     * Check if an HTTP response code is in the OK range (2XX).
     *
     * @param integer $httpCode
     * @return boolean
     */
    public function isResponseCodeOk(int $httpCode): bool
    {
        return $httpCode >= 200 && $httpCode < 300;
    }
}
