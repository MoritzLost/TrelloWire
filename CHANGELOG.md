# Changelog

## [2.0.0] - 2020-10-02

- **Milestone:** The last version was discovered to contain several problems with both the module and ProcessWire's cURL implementation in the `WireHttp` class, which stopped it from working correctly. This update fixes those problems, but requires ProcessWire 3.0.167 or above, because the cURL issues were fixed in this version. See the README for more information.
- **Feature:** It is now possible to configure which network interface the module uses for requests to the TrelloApi (cURL, fopen or socket). See *ProcessWire version support and switching the network implementation* in the README.

## [1.1.0] - 2020-08-19

- **Feature:** Added a public property to the API class (TrelloWireApi::lastResponse) that always contains the raw response to the last request made through this instance. This may be useful for logging error messages, because some of the helper methods only return false in case of a failed request.

## [1.0.1] - 2020-04-28

- **Bugfix:** Fixes an error that the module throws during card creation if an empty body field is set in the configuration. (@see https://processwire.com/talk/topic/23389-trellowire-turn-your-pages-into-trello-cards/?tab=comments#comment-200836)

## [1.0.0] - 2020-04-02

- **Milestone:** Initial public release!
