# Mpesa c2b 

[![Latest Stable Version](https://poser.pugx.org/lawrence615/mpesa/v/stable)](https://packagist.org/packages/lawrence615/mpesa)
[![Total Downloads](https://poser.pugx.org/lawrence615/mpesa/downloads)](https://packagist.org/packages/lawrence615/mpesa)
[![Latest Unstable Version](https://poser.pugx.org/lawrence615/mpesa/v/unstable)](https://packagist.org/packages/lawrence615/mpesa)
[![License](https://poser.pugx.org/lawrence615/mpesa/license)](https://packagist.org/packages/lawrence615/mpesa)

Build up upon from https://packagist.org/packages/lawrence615/mpesa 


This package is created to integrate c2b Mpesa. This package will only take payments payed by users in the system.

## Requirements
- [PHP >=5.6.0](http://php.net/)
- [Laravel 5.x](https://github.com/laravel/framework)

## Quick Installation
```bash
composer require "ftg/mpesac2b:dev-master"
```

#### Service Provider
```php
Ftg\Mpesa\MpesaServiceProvider::class,
```

#### Configuration and Assets
```bash
php artisan vendor:publish --provider="Ftg\Mpesa\MpesaServiceProvider"
```

Then run php artisan migrate to create the tables in you database. This will create two tables;

1. mpesa_payment_logs table - logs everything received from Safaricom

2. payments table - breaks down what is received from Safaricom into a number of columns


## Receiver Route
The route that receives the IPN is `c2b/payments/receiver` i.e. http://example.com/c2b/payments/receiver. This is the endpoint you give to Safaricom.

## Events
There are events triggered when certain actions happen. You can extend the package's behaviour by setting up your own event listeners to provide custom functionality.

These are the events triggered by the package. The list will grow with time as more events come up;

| Event                | Available data          |
|----------------------|-------------------------|
|c2b.received.payment  | Full C2B Payment Object


__C2B Payment Event Listener__

Add the following to the .env
```
BusinessNumber=
MaxAmount=

```


Create a Controller i.e. PaymentsController then create a function c2bPayment
```php
    //$payload will have the data from the event
    public function c2bPayment($payload){
       
    }
```

Register an event listener  in the boot method of your  EventServiceProvider:
```php
Event::listen('c2b.received.payment', 'App\Http\Controllers\PaymentsController@c2bPayment');
```

