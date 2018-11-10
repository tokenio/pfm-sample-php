## Token PFM Sample: PHP

Simple personal finance app that illustrates Token.io's Access Tokens

This sample app shows how to request Token's Access Tokens, useful
for fetching account information.

To run this code, you need PHP 5.5.0 or later and the [gRPC PHP extension](https://grpc.io/docs/quickstart/php.html#install-the-grpc-php-extension).

To install dependencies: `composer install`

To run, `composer start`

This starts up a server.

The server operates against Token's Sandbox environment by default.
This testing environment lets you try out UI and account flows without
exposing real bank accounts.

The server shows a web page at `localhost:3000`. The page has a Link with Token button.
Clicking the button displays Token UI that requests an Access Token.
When the app has an Access Token, it uses that Access Token to get account balances.
