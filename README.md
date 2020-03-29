# Trello Wire - Create Trello cards based on ProcessWire pages

This is a module for the [ProcessWire CMF](https://processwire.com/) that allows you to automatically create [Trello cards](https://trello.com/) for ProcessWire pages and update them when the pages are updated. This allows you to setup connected workflows. Card properties and change handling behaviour can be customized through the extensive module configuration. Every action the module performs is hookable, so you can modify when and how cards are created as much as you need to. The module also contains an API-component that makes it easy to make requests to the Trello API and extend the module workflows however you like.

## Table of contents

- [Motivation and how to get started](#motivation-and-how-to-get-started)
- [Advanced configuration and workflow adjustments](#advanced-configuration-and-workflow-adjustments)
- [How the module works](#how-the-module-works)
- [Hookable method list](#hookable-method-list)
    - [TrelloWire hooks](#trellowire-hooks)
    - [TrelloWireCard hooks](#trellowirecard-hooks)
- [Using the API class](#using-the-api-class)

## Motivation and how to get started

This module is supposed to enable custom workflows involving a bridge between ProcessWire pages and Trello cards. What you do with that is up to you. Here are some usage examples:

- If you create pages based on form submissions, you can create a Trello card for each new form submission, so someone on your team can claim the card and handle the request. This way, you can keep track of which requests your team has already responded to and how many pending requests there are. If you trash or delete the form submission pages in ProcessWire after you have dealt with them, you can have the module automatically archive or move the corresponding cards, so you don't have to manage the requests in two places. If you are using [Form Builder](https://processwire.com/store/form-builder), it has [an option to push new requests to pages](https://processwire.com/store/form-builder/#form-submission-features), so you can recreate this workflows very easily.
- If your site has some kind of product database, you can map individual product pages to Trello cards. This way, you can manage and assign product pages to the editors on your team, making it easier to coordinate simultaneous work on your site.

To get started, install the module. Right now you have to download it manually, but the module is pending approval in the ProcessWire modules directory and will soon be installable using the class name `TrelloWire`. Note the system requirements:

- PHP >= 7.2
- ProcessWire >= 3.0.133

After you have installed the module, go to it's configuration page (*Modules -> Configure -> TrelloWire*). To access your Trello board, you need to provide an API key and API token. Use the link in the API key field to generate it (make sure you are logged in to Trello). After you have entered the API key, save the page once, then follow the link in the API token field to generate a new API token. **Attention:** The API key can be created with any account, but the API token needs to be created by an account that has access to the board you want to connect to. After entering the API token and saving the configuration, the module will check if the token is valid and warn you if it isn't.

After you have entered valid API credentials, the rest of the options will appear. For now, you only need to worry about the option **Templates to create trello cards for**. Select one or more templates that you want to create Trello cards for. In the examples above, that would be the form submission template or the product template, respectively. You also need to set a target board and a target list as the default location for new cards. Note that after setting the target board, you have to save the configuration once so the module can retreive the lists on that board.

After those options are set, you're good to go. To test if everything is working, add a new page of one of the templates you selected previously and publish it. There should now be a new card on your Trello board with the same name as your new page.

If everything is working, you can check out the other options available in the module configuration to adjust everything for your intended workflow.

## Advanced configuration and workflow adjustments

The module provides extensive options to control when and where cards are created, what they contain, and what should happen if a page corresponding to a card is modified or deleted. The options are explained on the configuration page, but here's a quick overview of what you can do:

- By default, the card title will match the page title, and the card description will contain only a link to the ProcessWire page on your website. You can change those to either dynamic place with page replacement fields in curly braces (e.g. `{title}`) or completely static text.
- You can add any number of default labels from your board to the card. Labels are specific to boards, so if you change the target board or change the labels on Trello, you may need to adjust that option.
- You can also add a checklist to new cards. If you set at least one checklist item (through the textarea field, one item per line), you can also change the checklists's title. Both of those settings allow field replacements as well.
- By default, new cards are create when an applicable page is *published*, but you can change that to add cards as soon as the page is *added* instead. You can also turn off automatic card creation entirely, which may be useful if you want to create cards programmatically only in certain situations.
- You can set the module to automatically update a card's title and body whenever the page it belongs to is updated. Be careful with this, as it will overwrite manual changes perform on Trello.
- You can control what happens if a pages is published, hidden, trashed and deleted. For each of those status changes you have the following options:
    - Move the card to a different list. In this case, you can select a list to move the card to. This is useful if you have a *Done / Obsolete* list or something like this to contain entries that have been dealt with.
    - You can archive the card, which will hide it on your board, but not delete it. In this case, you have the option to reopen the card when the status change is reversed; that is, if an unpublished page is published, a hidden page is unhidden or a trashed page is restored.
        - Note that the latter option is not available for page deletion events, because deleted pages can't be restored.
    - You can also delete the card entirely. This is irreversible!
- Finally, if you want to turn off all automatic actions this module performs, there's an off-switch right at the start of the module settings.

## How the module works

This is a brief overview of how the module is structured and how it maps pages to cards. This is important to understand if you want to use modify the module's behaviour through hooks, or use it's API effectively. There are three main classes included:

- `ProcessWire\TrelloWire`. This registers all hooks and handles page creation and change events according to the module configuration.
- `ProcessWire\TrelloWireCard`. This is a ProcessWire module as well and is installed alongside the TrelloWire. It is a basic WireData container for card data and has several setter methods for the card title, description, labels, id etc. Every setter method is hookable, so you can hook into the card data independent of in what context it is created.
- `ProcessWire\TrelloWire\TrelloWireApi`. This is a simple wrapper around the Trello API. Check the last section on how to use it.

Whenever an applicable page is published (or created, depending on the settings), TrelloWire creates a new card on Trello using data from that page. The Trello API returns the card ID. This ID is then stored inside the [page's meta data](https://processwire.com/blog/posts/pw-3.0.133/#new-page-gt-meta-method). This way, ProcessWire pages are mapped to Trello cards. For subsequent page save events, TrelloWire will check if the page meta data contains a card ID and update the corresponding card according to the settings. Likewise, if you want to check whether a TrelloWireCard instance is intended for a new card or an existing card, check if it's `id` property is set. If it does, it references an existing card.

## Hookable method list

Most methods inside TrelloWire and TrelloWireCard are hookable so you can customize the behaviour and workflow mapping. The following list includes all hookable methods and explains when they are called.

### TrelloWire hooks

- `TrelloWire::buildCardData`
    - `@param Page $page`        The page this card will belong to.
    - `@return TrelloWireCard`   The card object with values based on the page.

Builds a TrelloWireInstance using data from the passed page. This will automatically set the card's ID if the page already has a reference to an existing card, so you can check the card's ID to determine whether this card will be used to create or update card. This method is called in multiple places, whenever a TrelloWire hook needs to extract data from a page based on the module configuratio.

---

- `TrelloWire::createCardForPage`
    - `@param TrelloWireCard $card`   The card instance with the data for this card.
    - `@param Page $page`             The page this card belongs to.
    - `@return void`

This takes a TrelloWireCard and a page and creates a new card on Trello through the API. It also stores the new card's ID in the page meta data. This method is called when the module wants to create a Trello card after a new page is created or published (depeding on the *Card Creation Trigger* setting). You can hook before this to modify the card before it is posted to Trello or abort the process entirely. Or hook after this to add further content to the card using the API.

---

- `TrelloWire::getDefaultChecklistTitle`
    - `@param Page $page`   The page the card being currently created is based on.
    - `@return string`

Returns the title for the checklist on new cards using based on the module settings and the passed page. Note this will only ever be called if `getDefaultChecklistItems` doesn't return an empty result.

---

- `TrelloWire::getDefaultChecklistItems`
    - `@param Page $page`   The page the card being currently created is based on.
    - `@return array`

Returns an array of strings which will become checklist items for the default checklist on new cards.

---

- `TrelloWire::trelloCreateCard`
    - `@param TrelloWireCard $card`  The card with the values to post to Trello.
    - `@return object|bool`          Returns the card object returned from the API, or false on failure.

Creates a new card on Trello using the API component with values from the TrelloWireCard.

---

- `TrelloWire::trelloUpdateCard`
    - `@param TrelloWireCard $card`  The card with a values to update on Trello. Must contain an ID property referencing an existing card.
    - `@return object|bool`          Returns the card object returned from the API, or false on failure.

Updates an existing card's title and body (description) using the passed TrelloWireCard.

---

### TrelloWireCard hooks

The TrelloWireCard component is just a container for page data. All properties are set through hookable methods to their data can be modified whenever it is set. Note that the card has a `page` property which holds the page this card belongs to, so you can always access it's values. TrelloWire will always set the page property first, so if you are hooking any of the other methods you can rely on the page property being initialized at that point.

- `TrelloWireCard::setPage`
    - `@param Page $page`
    - `@return void`

Set the page this card belongs to.

---

- `TrelloWireCard::setId`
    - `@param string $id`
    - `@return void`

Set this cards ID (only used for cards that already exist in Trello).

---

- `TrelloWireCard::setList`
    - `@param string $idList`
    - `@return void`

Set the ID of the list this card belongs to.

---

- `TrelloWireCard::setTitle`
    - `@param string $title`
    - `@return void`

Set the title / name of this card.

---

- `TrelloWireCard::setBody`
    - `@param string $body`
    - `@return void`

Set the body / description of this card.

---

- `TrelloWireCard::setLabels`
    - `@param array $labelIds`
    - `@return void`

Set the IDs of labels belonging to this cards. Note that label IDs are specific to each board.

## Using the API class

The `TrelloWireApi` class is a wrapper around the Trello API and can be used to conveniently send requests to the API. The easiest way to get an instance of the API configured with the API credentials set in the module configuration is the helper method on the main module.

```php
$api = wire('modules')->get('TrelloWire')->api();
```

That said, if you absolutely want to instantiate the class with different credentials, you can do so manually:

```php
$api = new \ProcessWire\TrelloWire\TrelloWireApi($myApiKey, $myApiToken);
```

All API requests will automatically include the API key & token, so you don't need to include them in the parameters. You can check if the API credentials used by the instance are valid through `$TrelloWireApi->isValidToken()`.

The API methods are structured in three layers. The first is the general `send` method that will perform the API request with the API key and token included. The second layer are specific methods for different HTTP verbs: `get`, `post`, `put`, `delete`. Finally, there are multiple endpoint-specific methods that will perform specific actions. For example, the `boards` method will return an array of available boards, and `addChecklistToCard` will add a checklist to an existing card.

The class uses [WireHttp](https://processwire.com/api/ref/wire-http/) to make requests. The send, get, post, put and delete methods will return the return value of the appropriate WireHttp method unaltered. Most of the specific helper methods will return the decoded JSON response from the API if the request was successful, or `false` if it failed for any reason.

After making a request through one of the module methods, you can check the result in detail through the following public properties, which will always relate to the last request made:

- `$TrelloWireApi->lastRequest` contains the WireHttp instance used for the last request (a new one is created for every request).
- `$TrelloWireApi->lastResponseCode` contains the HTTP response code of the last request as an integer.
- `$TrelloWireApi->lastResponseOk` will be `true` if the last request was successful (HTTP response code in the 2XX range) and `false` if it wasn't.
