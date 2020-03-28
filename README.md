# Trello Wire - Create Trello cards based on ProcessWire pages

This is a module for the [ProcessWire CMF](https://processwire.com/) that allows you to automatically create [Trello cards](https://trello.com/) for ProcessWire pages and update them when the pages are updated. This allows you to setup connected workflows. Card properties and change handling behaviour can be customized through the extensive module configuration. Every action the module performs is hookable, so you can modify when and how cards are created as much as you need to. The module also contains an API-component that makes it easy to make requests to the Trello API and extend the module workflows however you like.

## Motivation and how to get started.

- Workflows / process management through trello
    - Example workflows (form builder -> new requests to trello cards; product database -> new page created, handle updates)
- Get started
    - install the module manually / pending approval TrelloWire class
    - go to the module config
    - generate api key & token (check the notes to see if it was successful)
    - set target board & target list
    - set templates
- Now try to create and publish a new page. There should be a new Trello card

## Advanced configuration and workflow adjustments

- modify card title & body
- add labels and checklists
- change when cards are created
- set cards to auto update
- turn off module entirely
- add status change handling

## Hookable method list



## Using the API class

- three method layers
    - send
    - get / post / put / delete
    - specific helper methods (currently only those used by the module, may add more later)
- methods return FALSE on failure / error
    - for successful requests, they mostly return the decoded json containing the requested or created object / array
- internally uses WireHttp
    - using lastRequest, lastResponseCode, lastResponseOk
- api key & token added automatically; manually override by creating new TrelloWireApi
