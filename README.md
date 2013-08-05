# hypercharge-php

PHP SDK for the Hypercharge payment gateway.

Version 1.24.3-beta

August 5, 2013

tested with PHP 5.3.25 on OSX/Linux

## Installation

Do not download the Hypercharge PHP SKD files manually.
You download and install it with [composer](http://getcomposer.org/), a php package manger.

Let's say  `MY_PROJECT/` is your project root folder.


Do the following three steps:

1) [Download composer.phar](http://getcomposer.org/download/) to `MY_PROJECT/composer.phar`

2) With your text editor create a `MY_PROJECT/composer.json` containing
```json
{
  "require": {
    "php": ">=5.3",
    "hypercharge/hypercharge-php": "*"
  }
}
```
This will tell composer to install the most recent Hypercharge PHP SDK version.

3) in the shell (dos console or terminal) go to `MY_PROJECT/` and run the command
```sh
$ php composer.phar install
```
This downloads and installs Hypercharge PHP SDK and its dependencies into `MY_PROJECT/vendor/`.

Later, when deploying your project to your server you will have to upload the complete `MY_PROJECT/vendor/` directory to your server as well.


## Configuration

To get started, add one of the following snippets to you global configuration file.

### Sandbox
For development and testing. No real money is transfered.

```php
// config.php
require_once dirname(__DIR__).'/vendor/autoload.php';
Hypercharge\Config::set(
   'YOUR-MERCHANT-TEST-LOGIN'
  ,'YOUR-MERCHANT-TEST-PASSWORD'
  ,Hypercharge\Config::ENV_SANDBOX
);
```

### Live

```php
// config.php
require_once dirname(__DIR__).'/vendor/autoload.php';
Hypercharge\Config::set(
   'YOUR-MERCHANT-LOGIN'
  ,'YOUR-MERCHANT-PASSWORD'
  ,Hypercharge\Config::ENV_LIVE
);
```
If there is no `MY_PROJECT/vendor/autoload.php` in your project follow the [installation instructions](#installation).

## Credit Card sale Transaction

Submit 77.00 USD as a credit card sale to hypercharge channel.

```php
require_once 'config.php';

$channelToken = 'e9fd7a957845450fb7ab9dccb498b6e1f6e1e3aa';

$sale = Hypercharge\Transaction::sale($channelToken, array(
  'currency'          => 'USD'
  ,'amount'           => '7700' // in cents
  ,'transaction_id'   => 'YOUR-GENERATED-UNIQUE-ID'
  ,'usage'            => 'Appears in the customers bank statement'
  ,'card_holder'      => 'Max Mustermann'
  ,'expiration_month' => '07'
  ,'expiration_year'  => '2018'
  ,'card_number'      => '4200000000000000' // a valid dummy
  ,'cvv'              => '123'
  ,'customer_email'   => 'max@mustermann.de'
  ,'customer_phone'   => '+403012345678'
));

if($sale->isSuccess()) {
  // cc transaction successfull
} else {
  //
}
```
## Web Payment Form (WPF) session

The following example is more complex.

- create a WPF session.
- redirect customer browser to WPF url provided
- customer submits WPF and is redirected to return_success_url you provided.
    In the mean time hypercharge notifies your backend by calling `notification_url`, providing the payment status.

```php
require_once 'config.php';

try {
  // create the WPF session
  $wpf = Hypercharge\Payment::wpf(array(
    'currency' => 'EUR'
    ,'amount' => '1000' // in cents
    ,'transaction_id' => 'YOUR-GENERATED-UNIQUE-ID'
    ,'usage' => 'Appears in the customers bank statement'
    ,'notification_url' => 'https://your-server.com/hypercharge-wpf-notifications.php'
    ,'return_success_url' => 'http://your-server.com/payment-return-page.php?status=success'
    ,'return_failure_url' => 'http://your-server.com/payment-return-page.php?status=failure'
    ,'return_cancel_url'  => 'http://your-server.com/payment-return-page.php?status=cancel'
  ));

  if($wpf->shouldRedirect()) {
    // ok, WPF session created.

    save_payment_unique_id_to_order( $payment->unique_id );

    // redirect user to WPF
    header('Location: '. $payment->redirect_url);

  // handle errors...
  } elseif($payment->isPersistentInHypercharge()) {
    // payment has been created in hypercharge but something went wrong.
    save_payment_unique_id_to_order( $payment->unique_id );

    // 1.) check $payment->error (a subclass of Hypercharge\Errors\Error)
    //     and show error message to customer
    // 2.) manually login to hypercharge merchant backend.
    //     Go to "Payments", search by unique_id and analize the log messages.

  } else {
    // TODO handle error
    // authentication error -> check $login, $password
    // inputdata error -> check your php code for missing or
    //   misspelled fields in $paymentData

  }

} catch(Hypercharge\Errors\ValidationError $e) {
  // no payment created in hypercharge because of local pre-validation errors

  // show validation errors to customer
  // $e->errors is an Array of Hash, format: [ { "property": String , "message" : String }, ... ]

} catch(Exception $e) {
  // severe error
  // log $e
  // display apologies to customer
}
```

The WPF is displayed in English by default (`'en'`). If you want a German WPF simply change the redirection line to:

```php
    header('Location: '. $payment->getRedirectUrl('de'));
```


## WPF Notification

hypercharge -> plain POST data -> your_server

You place the code under the url you specify as notification_url (`https://your-server.com/hypercharge-wpf-notifications.php` in the example abough)

```php
require_once 'config.php';

$notification = Hypercharge\Payment::notification($_POST);
if($notification->isVerified()) {
  if($notification->isApproved()) {
    // pseudocode...
    // Notice: to be 100% reacecondition proof update status to 'payment_approved' has to be done atomically
    $updatedRows = update_order(array(
      'set'   => array('status'=> 'payment_approved')
      'where' => array('status'=> 'waiting_for_payment_approval'
                      ,'hypercharge_unique_id' => $notification->payment_unique_id
      )
    ));

    if($updatedRows == 1) {
      // ok, start shipping
      $order = find_order_where(array('status' => 'payment_approved'
                                      ,'hypercharge_unique_id' => $notification->payment_unique_id
      ));
      $order->ship_goods_to_customer();

    } else {
      // hypercharge notification already received! ignore duplicate notification.
    }
  }

  // Tell hypercharge the notification has been successfully processed
  // and ensure output ends here
  die( $notification->ack() );

} else {
  // signature invalid or message does not come from hypercharge.
  // check your configuration or notificatoin request origin
}
```

## Create Mobile Payment Session

Mobile Payments are quite similar to WPF Payments.
The Session creation has slightly different data.
The Notification code is the same as WPF Notification abough.

your_server -> POST XML -> hypercharge

```php
require_once 'config.php';

try {
  // create the mobile payment session
  $payment = Hypercharge\Payment::mobile(array(
    'currency' => 'EUR'
    ,'amount' => '1000' // in cents
    ,'transaction_id' => 'YOUR-GENERATED-UNIQUE-ID'
    ,'usage' => 'Appears in the customers bank statement'
    ,'notification_url' => 'https://your-server.com/hypercharge-wpf-notifications.php'
  ));

  if($payment->shouldContinueInMobileApp()) {
    // ok, mobile payment session created.

    save_payment_unique_id_to_order($payment->unique_id);

    // tell your mobile device where to
    // a) submit credit card xml data to (submit_url)
    // b) cancel the payment if user presses 'cancel' in your mobile app (cancel_url)
    // see example below.
    die(json_encode($payment));

  } else {

    // TODO handle error
    // vaildation
    // authentication error -> check $login, $password
    // inputdata error -> check your php code for missing
    // or misspelled fields in $paymentData

  }
} catch(Hypercharge\Error $e) {
  // no payment created in hypercharge because of local pre-validation errors

  // check your php code
  // display apologies to customer "Sorry, no payment possible at the moment."
}
```

## Submit Mobile Payment from mobile device

Example Mobile Submit XML your mobile mobile application posts to `$payment->submit_url` to process the payment.

```xml
<payment>
  <payment_method>credit_card</payment_method>
  <card_holder>Manfred Mann</card_holder>
  <card_number>4200000000000000</card_number>
  <cvv>123</cvv>
  <expiration_year>2015</expiration_year>
  <expiration_month>12</expiration_month>
</payment>
```

If you're concerned of POSTing cc data via internet: The `$payment->submit_url` will look something like `https://testpayment.hypercharge.net/mobile/submit/eabcb7a41044e764746b0c7e32c1e9d1` so the xml will be transmitted encrypted.

## Tests

install dev dependecies
```sh
composer update --dev
```

### Unit Tests
Run the unit tests
```sh
php test/all_tests.php
```

## Warranty

This software is provided "as is" and without any express or implied warranties, including, without limitation, the implied warranties of merchantibility and fitness for a particular purpose.