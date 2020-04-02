# Trello Wire - Create Trello cards based on ProcessWire pages

This is a module for the [ProcessWire CMF](https://processwire.com/) that allows you to automatically create [Trello cards](https://trello.com/) for ProcessWire pages and update them when the pages are updated. This allows you to setup connected workflows. Card properties and change handling behaviour can be customized through the extensive module configuration. Every action the module performs is hookable, so you can modify when and how cards are created as much as you need to. The module also contains an API-component that makes it easy to make requests to the Trello API and build your own connected ProcessWire-Trello workflows.

## Table of contents

- [Motivation and how to get started](#motivation-and-how-to-get-started)
- [Advanced configuration and workflow adjustments](#advanced-configuration-and-workflow-adjustments)
- [How the module works](#how-the-module-works)
- [Using the API class](#using-the-api-class)
- [API & hook documentation](#api--hook-documentation)
    - [ProcessWire\TrelloWire](#processwiretrellowire)
    - [ProcessWire\TrelloWireCard](#processwiretrellowirecard)
    - [ProcessWire\TrelloWire\TrelloWireApi](#processwiretrellowiretrellowireapi)

## Motivation and how to get started

This module is supposed to enable custom workflows involving a bridge between ProcessWire pages and Trello cards. What you do with that is up to you. Here are some usage examples:

- If you create pages based on form submissions, you can create a Trello card for each new form submission, so someone on your team can claim the card and handle the request. This way, you can keep track of which requests your team has already responded to and how many pending requests there are. If you trash or delete the form submission pages in ProcessWire after you have dealt with them, you can have the module automatically archive or move the corresponding cards, so you don't have to manage the requests in two places. If you are using [Form Builder](https://processwire.com/store/form-builder), it has [an option to push new requests to pages](https://processwire.com/store/form-builder/#form-submission-features), so you can recreate this workflow very easily.
- If your site has some kind of product database, you can map individual product pages to Trello cards. This way, you can manage and assign product pages to the editors on your team, making it easier to coordinate simultaneous work on your site.

To get started, install the module. Right now you have to download it manually, but the module is pending approval in the ProcessWire modules directory and will soon be installable using the class name `TrelloWire`. Note the system requirements:

- PHP >= 7.2
- ProcessWire >= 3.0.133

After you have installed the module, go to it's configuration page (*Modules -> Configure -> TrelloWire*). To access your Trello board, you need to provide an API key and API token. Use the link in the API key field to generate your personal key. After you have entered the API key, save the page once, then follow the link in the API token field to generate a new API token. **Attention:** The API key can be created with any account, but the API token needs to be created by an account that has access to the board you want to connect to. After entering the API token and saving the configuration, the module will check if the token is valid and warn you if it isn't.

After you have entered valid API credentials, the rest of the options will appear. For now, you only need to worry about the option **Templates to create trello cards for**. Select one or more templates that you want to create Trello cards for. In the examples above, that would be the form submission template or the product template, respectively. You also need to set a target board and a target list as the default location for new cards. Note that after setting the target board, you have to save the configuration once so the module can retreive the lists on that board.

After those options are set, you're good to go. To test if everything is working, add a new page using one of the templates you selected previously and publish it. There should now be a new card on your Trello board with the same name as your new page.

If everything is working, you can check out the other options available in the module configuration to adjust everything for your intended workflow.

## Advanced configuration and workflow adjustments

The module provides extensive options to control when and where cards are created, what they contain, and what should happen if a page corresponding to a card is modified or deleted. The options are explained on the configuration page, but here's a quick overview of what you can do:

- By default, the card title will match the page title, and the card description will contain only a link to the ProcessWire page on your website. You can change those to either dynamic text with page replacement fields in curly braces (e.g. `{title}`) or completely static text.
- You can add any number of default labels from your board to the card. Labels are specific to boards, so if you change the target board or change the labels on Trello, you may need to adjust that option.
- You can also add a checklist to new cards. If you set at least one checklist item (through the textarea field, one item per line), you can also change the checklist's title. Both of those settings allow field replacements as well.
- By default, new cards are created when an applicable page is *published*, but you can change that to add cards as soon as a page is *added* instead. You can also turn off automatic card creation entirely, which may be useful if you want to create cards programmatically only in certain situations.
- You can set the module to automatically update a card's title and body whenever the page it belongs to is updated. Be careful with this, as it will overwrite manual changes performed on Trello.
- You can control what happens if a pages is published, hidden, trashed and deleted. For each of those status changes you have the following options:
    - Move the card to a different list. In this case, you can select a list to move the card to. This is useful if you have a *Done / Obsolete* list or something like this to contain cards that have been dealt with.
    - You can archive the card, which will hide it on your board, but not delete it. In this case, you have the option to reopen the card when the status change is reversed; that is, if an unpublished page is published, a hidden page is unhidden or a trashed page is restored.
        - Note that the restore option is not available for page deletion events, because deleted pages can't be restored.
    - You can also delete the card entirely. This is irreversible!
- Finally, if you want to turn off all automatic actions this module performs, there's an off-switch right at the start of the module settings.

## How the module works

This is a brief overview of how the module is structured and how it maps pages to cards. This is important to understand if you want to use modify the module's behaviour through hooks, or use it's API effectively. There are three main classes included:

- `ProcessWire\TrelloWire`. This registers all hooks and handles page creation and change events according to the module configuration.
- `ProcessWire\TrelloWireCard`. This is a ProcessWire module as well and is installed alongside the TrelloWire. It is a basic WireData container for card data and has several setter methods for the card title, description, labels, id etc. Every setter method is hookable, so you can hook into the card data independent of what context it is created in.
- `ProcessWire\TrelloWire\TrelloWireApi`. This is a simple wrapper around the Trello API. Check the last section on how to use it.

Whenever an applicable page is published (or created, depending on the settings), TrelloWire creates a new card on Trello using data from that page. The Trello API returns the card ID. This ID is then stored inside the [page's meta data](https://processwire.com/blog/posts/pw-3.0.133/#new-page-gt-meta-method). This way, ProcessWire pages are mapped to Trello cards. For subsequent page save events, TrelloWire will check if the page meta data contains a card ID and update the corresponding card according to the settings. Likewise, if you want to check (e.g. in a hook) whether a TrelloWireCard instance corresponds to a new card or an existing card, check if it's `id` property is set. If it is, it references an existing card.

## Using the API class

The `TrelloWireApi` class is a wrapper around the Trello API and can be used to conveniently send requests to the API. The easiest way to get an instance of the API configured with the API credentials set in the module configuration is the helper method on the main module.

```php
$api = wire('modules')->get('TrelloWire')->api();
```

That said, if you absolutely want to instantiate the class with different credentials, you can do so manually:

```php
$api = new \ProcessWire\TrelloWire\TrelloWireApi(
    $myApiKey,
    $myApiToken
);
```

All API requests will automatically include the API key & token, so you don't need to include them in the parameters. You can check if the API credentials used by the instance are valid through `$TrelloWireApi->isValidToken()`.

The API methods are structured in three layers. The first is the general `send` method that will perform the API request with the API key and token included. The second layer are specific methods for different HTTP verbs: `get`, `post`, `put`, `delete`. Finally, there are multiple endpoint-specific methods that will perform specific actions. For example, the `boards` method will return an array of available boards, and `addChecklistToCard` will add a checklist to an existing card.

The class uses [WireHttp](https://processwire.com/api/ref/wire-http/) to make requests. The send, get, post, put and delete methods will return the return value of the appropriate WireHttp method unaltered. Most of the specific helper methods will return the decoded JSON response from the API if the request was successful, or `false` if it failed for any reason.

After making a request through one of the module methods, you can check the result in detail through the following public properties, which will always relate to the last request made:

- `$TrelloWireApi->lastRequest` contains the WireHttp instance used for the last request (a new one is created for every request).
- `$TrelloWireApi->lastResponseCode` contains the HTTP response code of the last request as an integer.
- `$TrelloWireApi->lastResponseOk` will be `true` if the last request was successful (HTTP response code in the 2XX range) and `false` if it wasn't.

## API & hook documentation

Below you'll find a complete API documentation for the module. Most methods inside TrelloWire and TrelloWireCard are hookable so you can customize the behaviour and workflow mapping (those methods are labelled accordingly).

### ProcessWire\TrelloWire

Retrieve a new instance of TrelloWire through ProcessWire's API:

```php
$TrelloWire = wire('modules')->get('TrelloWire');
```

---

- `TrelloWire::api`
    - `@return TrelloWireApi|null`

Get an instance of the TrelloWireApi class using the API key and token set through the module configuration. This will return null if the API key or token are missing.

---

- `TrelloWire::buildCardData`
    - **Hookable**
    - `@param Page $page`        The page this card will belong to.
    - `@return TrelloWireCard`   The card object with values based on the page.

Construct a new TrelloWireCard instance based on the passed page using the module settings. Hook after this if you want to change how Trello card data is generated for pages. The method will automatically set the card's ID if the page already has a reference to an existing card, so you can check the card's ID to determine whether this card will be used to create or update card. This method is called in multiple places, whenever a TrelloWire hook needs to extract data from a page based on the module configuration.

---

- `TrelloWire::createCardForPage`
    - **Hookable**
    - `@param TrelloWireCard $card`   The card instance with the data for this card.
    - `@param Page $page`             The page this card belongs to.
    - `@return void`

Creates a card on Trello based on the data contained in the passed TrelloWireCard. It also creates a new checklist on this card based on the module settings (Checklist title & items). Those settings are retrieved through the corresponding helper methods so you can modify or remove them by hooking those methods. After the card is created, the module will also store it's ID in the meta data of the passed $page, so it can be updated later. This method is called when the module wants to create a Trello card after a new page is created or published (depeding on the *Card Creation Trigger* setting). You can hook before this to modify the card before it is posted to Trello or abort the process entirely. Or hook after this to add further content to the card using the API.

---

- `TrelloWire::deleteCardForPage`
    - `@param TrelloWireCard $card`  The card instance holding the ID for the card to delete.
    - `@param Page $page`            The page the card belongs to.
    - `@return void`

Delete a card associated wih a page from Trello. Because the card no longer exists after this, the reference stored inside $page->meta() is removed as well.

---

- `TrelloWire::getDefaultChecklistTitle`
    - **Hookable**
    - `@param Page $page`   The page the card being currently created is based on.
    - `@return string`

Get the default title for the checklist added to new Trello cards. It receives the page the card belongs to as an argument so you can hook this method and modify the return value based on the page.

---

- `TrelloWire::getDefaultChecklistItems`
    - **Hookable**
    - `@param Page $page`   The page the card being currently created is based on.
    - `@return array`

Get a list of items to add to the checklist added to new Trello cards. It received the page the card belongs to as an argument so you can hook this method and modify the return value based on the page.

---

- `TrelloWire::trelloCreateCard`
    - **Hookable**
    - `@param TrelloWireCard $card`  The card with the values to post to Trello.
    - `@return object|bool`          Returns the card object returned from the API, or false on failure.

Create a new Trello card through the API based on a TrelloWireCard instance.

---

- `TrelloWire::trelloUpdateCard`
    - **Hookable**
    - `@param TrelloWireCard $card`  The card with a values to update on Trello. Must contain an ID property referencing an existing card.
    - `@return object|bool`          Returns the card object returned from the API, or false on failure.

Update an existing Trello card's title and body with values from the TrelloWireCard instance.

### ProcessWire\TrelloWireCard

The TrelloWireCard component is just a container for page data. All properties are set through hookable methods so their data can be modified whenever it is set. Note that the card has a `page` property which holds the page this card belongs to, so you can always access it's values. TrelloWire will always set the page property first, so if you are hooking any of the other methods you can rely on the page property being initialized at that point.

You can create an empty instance through ProcessWire's API, or create a populated instance with data based on a ProcessWire page using the helper method on `TrelloWire`:

```php
// create an empty instance
$TrelloWireCard = wire('modules')->get('TrelloWireCard');

// create an instance based on a page
$TrelloWireCard = wire('modules')->get('TrelloWire')->buildCardData($page);
```

---

- `TrelloWireCard::setPage`
    - **Hookable**
    - `@param Page $page`
    - `@return void`

Set the page this card belongs to.

---

- `TrelloWireCard::setId`
    - **Hookable**
    - `@param string $id`
    - `@return void`

Set this card's ID (only used for cards that already exist in Trello).

---

- `TrelloWireCard::setList`
    - **Hookable**
    - `@param string $idList`
    - `@return void`

Set the ID of the list this card belongs to.

---

- `TrelloWireCard::setTitle`
    - **Hookable**
    - `@param string $title`
    - `@return void`

Set the title / name of this card.

---

- `TrelloWireCard::setBody`
    - **Hookable**
    - `@param string $body`
    - `@return void`

Set the body / description of this card.

---

- `TrelloWireCard::setLabels`
    - **Hookable**
    - `@param array $labelIds`
    - `@return void`

Set the IDs of labels belonging to this cards. Note that label IDs are specific to each board.

### ProcessWire\TrelloWire\TrelloWireApi

The Trello Wire API can be used independently of the rest of the module. See [Using the API class](#using-the-api-class) for information on how to best instantiate the class and how it is structured.

---

- `$lastRequest`
    - `@var WireHttp` Always contains the WireHttp instance used for the last request made through this instance.

---

- `$lastResponseCode`
    - `@var int` Always contains the HTTP response code of the last request made through this instance.

---

- `$lastResponseOk`
    - `@var bool` Always contains true if the last request made through this instance was successful (status code 2XX) or false if it wasn't.

---

- `TrelloWireApi::send`
     - `@param string $endpoint`  The endpoint to call, including relevant parameters, without a leading slash.
     - `@param string $method`    The HTTP method to use (one of GET, POST, PUT, DELETE).
     - `@param array $data`       Additional data to send with this request.
     - `@see` https://processwire.com/api/ref/wire-http/
     - `@return mixed`

Send a request to any endpoint of the Trello API. This passes requests to the appropriate method of ProcessWire's WireHttp class, so check the documentation for possible return values. You can always access the WireHttp instance used for the last request through the public $lastRequest property.

---

- `TrelloWireApi::get`
     - `@param string $endpoint`  The endpoint to call, including relevant parameters, without a leading slash.
     - `@param array $data`       Associative array of parameters to add to this request.
     - `@return mixed`

Send a GET request to the specified endpoint of the Trello API.

---

- `TrelloWireApi::post`
     - `@param string $endpoint`  The endpoint to post to, including relevant parameters, without a leading slash.
     - `@param array $data`       Associative array of data to send with this request.
     - `@return mixed`

Send a POST request to the specified endpoint of the Trello API.

---

- `TrelloWireApi::put`
     - `@param string $endpoint`  The endpoint to call, including relevant parameters, without a leading slash.
     - `@param array $data`       Associative array of data to send with this request.
     - `@return mixed`

Send a PUT request to the specified endpoint of the Trello API.

---

- `TrelloWireApi::delete`
     - `@param string $endpoint`  The endpoint to call, including relevant parameters, without a leading slash.
     - `@param array $data`       Associative array of data to send with this request.
     - `@return mixed`

Send a DELETE request to the specified endpoint of the Trello API.

---

- `TrelloWireApi::isResponseCodeOk`
     - `@param integer $httpCode`
     - `@return boolean`

Check if an HTTP response code is in the OK range (2XX).

---

- `TrelloWireApi::isValidToken`
     - `@return boolean`

Check if the API token this instance is configured to use is valid.

---

- `TrelloWireApi::boards`
     - `@param array $fields`     Board fields to include in the response.
     - `@param string $filter`    The filter to use for this request.
     - `@return array|bool       Returns an array of board objects or false on failure.`

Get all boards belonging to the user that created the current API token.

---

- `TrelloWireApi::lists`
     - `@param string $idBoard`   The ID of the board.
     - `@param array $fields`     The board fields to include in the response.
     - `@param string $filter`    The filter to use for this request.
     - `@param string $cards`     The type of cards to include in the response.
     - `@param array $cardFields` The card fields to include in the response.
     - `@return array|boolean    Returns an array of list objects or false on failure.`

Get the lists existing in a board.

---

- `TrelloWireApi::labels`
     - `@param string $idBoard`   The ID of the board.
     - `@param array $fields`     The label fields to include in the response.
     - `@return array|boolean`

Get the available labels of a board.

---

- `TrelloWireApi::card`
     - `@param string $idCard`    The ID of the card to retrieve.
     - `@param array $fields`     Array of fields to include in the response.
     - `@return object|bool      Returns the card object on success or false on failure.`

Get a card from the API.

---

- `TrelloWireApi::createCard`
     - `@param string $idList`    The ID of the list to add the card to.
     - `@param string $title`     The name / title of the card.
     - `@param string $body`      The description / body of the card.
     - `@param array $addData`    Additional fields to pass to the API (associative array).
     - `@return object|boolean   Returns the card object on success or false on failure.`

Post a new card to the specified list. Returns true on success, false on failure.

---

- `TrelloWireApi::updateCard`
     - `@param string $idCard`        The ID of the card.
     - `@param array $data`           Additional fields to update (associative array).
     - `@return object|bool          Returns the card object on success or false on failure.`

Update an existing card on Trello.

---

- `TrelloWireApi::moveCard`
     - `@param string $idCard`    The ID of the card to move.
     - `@param string $idList`    The ID of the list to move the card to.
     - `@return object|bool      Returns the card object on success or false on failure.`

Move a card to a different list.

---

- `TrelloWireApi::archiveCard`
     - `@param string $idCard`    The ID of the card to close.
     - `@return object|bool      Returns the card object on success or false on failure.`

Archive / close a card.

---

- `TrelloWireApi::restoreCard`
     - `@param string $idCard`    The ID of the card to restore.
     - `@return object|bool      Returns the card object on success or false on failure.`

Restore / open a card.

---

- `TrelloWireApi::deleteCard`
     - `@param string $idCard`    The ID of the card to delete.
     - `@return boolean          Returns true on success or false on failure.`

Permanently delete a card. Cards deleted this way can NOT be restored!

---

- `TrelloWireApi::addCommentToCard`
     - `@param string $idCard`    The ID of the card to comment on.
     - `@param string $comment`   The comment as a string (supports markdown).
     - `@return object|bool      Returns the card object on success or false on failure.`

Add a comment to a card.

---

- `TrelloWireApi::addLabelToCard`
     - `@param string $idCard`    The ID of the card to add the label to.
     - `@param string $idLabel`   The ID of the label to add (must be one of the labels available on the board the card is on).
     - `@return object|bool      Returns an array of label IDs on the card (including the new one) upon success or false on failure.`

Add a label to a card.

---

- `TrelloWireApi::addChecklistToCard`
     - `@param string $idCard`    The ID of the card.
     - `@param string $title`     The title of the checklist.
     - `@return object|bool      Returns the checklist object on success or false on failure.`

Add a new checklist to a card.

---

- `TrelloWireApi::addItemToChecklist`
     - `@param string $idChecklist`   The ID of the checklist.
     - `@param string $title`         The title / name of the new item.
     - `@param boolean $checked`      Is the new item checked?
     - `@param string $position`      Position of the new item ('top', 'bottom', or positive integer).
     - `@return boolean              Returns true on success or false on failure.`

Add an item to a checklist.
