<?php
namespace ProcessWire\TrelloWire;

use ProcessWire\Wire;
use ProcessWire\WireHttp;

class TrelloWireApi extends Wire
{
    /** @var string The base URL for the Trello API. */
    public const API_BASE = 'https://api.trello.com/1/';

    /** @var WireHttp Always contains the WireHttp instance used for the last request made through this instance. */
    public $lastRequest;

    /** @var int Always contains the HTTP response code of the last request made through this instance. */
    public $lastResponseCode;

    /** @var bool Always contains true if the last request made through this instance was successful (status code 2XX) or false if it wasn't. */
    public $lastResponseOk;

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
     * Send a request to any endpoint of the Trello API. This passes requests to
     * the appropriate method of ProcessWire's WireHttp class, so check the
     * documentation for possible return values. You can always access the WireHttp
     * instance used for the last request through the public $lastRequest property.
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
                $result = $WireHttp->get($url, $data);
                break;
            case 'POST';
                $result = $WireHttp->post($url, $data);
                break;
            case 'PUT':
            case 'DELETE':
            default:
                $result = $WireHttp->send($url, $data, $method);
        }
        $this->lastResponseCode = $WireHttp->getHttpCode();
        $this->lastResponseOk = $this->isResponseCodeOk($this->lastResponseCode);
        return $result;
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
     * Check if an HTTP response code is in the OK range (2XX).
     *
     * @param integer $httpCode
     * @return boolean
     */
    public function isResponseCodeOk(int $httpCode): bool
    {
        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Check if the API token this instance is configured to use is valid.
     *
     * @return boolean
     */
    public function isValidToken(): bool
    {
        $this->get(sprintf('tokens/%s?fields=dateExpires&webhooks=false', $this->ApiToken));
        return $this->lastResponseOk;
    }

    /**
     * Get all boards belonging to the user that created the current API token.
     * 
     * @param array $fields     Board fields to include in the response.
     * @param string $filter    The filter to use for this request.
     * @return array|bool       Returns an array of board objects or false on failure.
     */
    public function boards(array $fields = ['id', 'name'], string $filter = 'all')
    {
        $result = $this->get(sprintf(
            'members/me/boards?fields=%1$s&filter=%2$s',
            implode(',', $fields),
            $filter
        ));
        return $this->lastResponseOk ? json_decode($result) : false;
    }

    /**
     * Get the lists existing in a board.
     *
     * @param string $idBoard   The ID of the board.
     * @param array $fields     The board fields to include in the response.
     * @param string $filter    The filter to use for this request.
     * @param string $cards     The type of cards to include in the response.
     * @param array $cardFields The card fields to include in the response.
     * @return array|boolean    Returns an array of list objects or false on failure.
     */
    public function lists(
        string $idBoard,
        array $fields = ['id', 'name', 'pos'],
        string $filter = 'open',
        string $cards = 'none',
        array $cardFields = []
    ) {
        $result = $this->get(sprintf(
            'boards/%1$s/lists?fields=%2$s&filter=%3$s&cards=%4$s&card_fields=%5$s',
            $idBoard,
            implode(',', $fields),
            $filter,
            $cards,
            implode(',', $cardFields)
        ));
        return $this->lastResponseOk ? json_decode($result) : false;
    }

    /**
     * Get the available labels of a board.
     *
     * @param string $idBoard   The ID of the board.
     * @param array $fields     The label fields to include in the response.
     * @return array|boolean
     */
    public function labels(string $idBoard, array $fields = ['id', 'name', 'color'])
    {
        $result = $this->get(sprintf(
            'boards/%1$s/labels?fields=%2$s',
            $idBoard,
            implode(',', $fields)
        ));
        return $this->lastResponseOk ? json_decode($result) : false;
    }

    /**
     * Post a new card to the specified list. Returns true on success, false on failure.
     *
     * @param string $idList    The ID of the list to add the card to.
     * @param string $title     The name / title of the card.
     * @param string $body      The description / body of the card.
     * @param array $addData    Additional fields to pass to the API (associative array).
     * @return object|boolean   Returns the card object on success or false on failure.
     */
    public function createCard(string $idList, string $title, string $body = '', array $addData = [])
    {
        $result = $this->post('cards', array_merge(
            $addData,
            [
                'name' => $title,
                'desc' => $body,
                'idList' => $idList
            ]
        ));
        return $this->lastResponseOk ? json_decode($result) : false;
    }

    /**
     * Update an existing card on Trello.
     *
     * @param string $idCard        The ID of the card.
     * @param string|null $title    New title / name for the card. Pass null to leave the existing title.
     * @param string|null $body     New body / description for the card. Pass null to leave the existing body.
     * @param array $addData        Additional fields to update (associative array).
     * @return object|bool          Returns the card object on success or false on failure.
     */
    public function updateCard(string $idCard, ?string $title = null, ?string $body = null, array $addData = [])
    {
        $updateFields = array_merge($addData, array_filter([
            'name' => $title,
            'desc' => $body,
        ]));
        $result = $this->put(sprintf('cards/%s', $idCard), $updateFields);
        return $this->lastResponseOk ? json_decode($result) : false;
    }

    /**
     * Move a card to a different list.
     *
     * @param string $idCard    The ID of the card to move.
     * @param string $idList    The ID of the list to move the card to.
     * @return object|bool      Returns the card object on success or false on failure.
     */
    public function moveCard(string $idCard, string $idList)
    {
        return $this->updateCard($idCard, null, null, ['idList' => $idList]);
    }

    /**
     * Archive / close a card.
     *
     * @param string $idCard    The ID of the card to close.
     * @return object|bool      Returns the card object on success or false on failure.
     */
    public function archiveCard(string $idCard)
    {
        return $this->updateCard($idCard, null, null, ['closed' => true]);
    }

    /**
     * Restore / open a card.
     *
     * @param string $idCard    The ID of the card to restore.
     * @return object|bool      Returns the card object on success or false on failure.
     */
    public function restoreCard(string $idCard)
    {
        return $this->updateCard($idCard, null, null, ['closed' => false]);
    }

    /**
     * Permanently delete a card. Cards deleted this way can NOT be restored!
     *
     * @param string $idCard    The ID of the card to delete.
     * @return boolean          Returns true on success or false on failure.
     */
    public function deleteCard(string $idCard): bool
    {
        $this->delete(sprintf('cards/%s', $idCard));
        return $this->lastResponseOk;
    }

    /**
     * Add a new checklist to a card.
     *
     * @param string $idCard    The ID of the card.
     * @param string $title     The title of the checklist.
     * @return object|bool      Returns the checklist object on success or false on failure.
     */
    public function addChecklistToCard(string $idCard, string $title)
    {
        $result = $this->post('checklists', [
            'idCard' => $idCard,
            'name' => $title,
        ]);
        return $this->lastResponseOk ? json_decode($result) : false;
    }

    /**
     * Add an item to a checklist.
     *
     * @param string $idChecklist   The ID of the checklist.
     * @param string $title         The title / name of the new item.
     * @param boolean $checked      Is the new item checked?
     * @param string $position      Position of the new item ('top', 'bottom', or positive integer).
     * @return boolean              Returns true on success or false on failure.
     */
    public function addItemToChecklist(string $idChecklist, string $title, bool $checked = false, $position = 'bottom'): bool
    {
        $this->post(sprintf('checklists/%s/checkItems', $idChecklist), [
            'name' => $title,
            'checked' => $checked,
            'pos' => $position
        ]);
        return $this->lastResponseOk ? true : false;
    }
}
