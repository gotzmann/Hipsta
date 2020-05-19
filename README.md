<p align="center">
  <img width="600" height="250" src="docs/logo.png">
</p>
![GitHub CI](https://github.com/gotzmann/comet/workflows/CI/badge.svg)
# Comet

Comet is a modern PHP framework for building fast REST APIs and microservices. 

## Superpowers

Comet gets all superpowers from Slim and Workerman as well as adds its own magic.

[Slim](https://github.com/slimphp/Slim) is a micro-framework that helps write simple yet powerful web applications and APIs based on modern PSR standards.

[Workerman](https://github.com/walkor/Workerman) is an asynchronous event-driven framework. It delivers high performance to build fast and scalable network applications. 

Comet allows you natively use all the methods of Slim: http://www.slimframework.com/docs/v4/

### Performance

PHP is often criticized for its low throughput and high latency. But that is not necessarily true for modern frameworks. Let's see how Comet outperforms others.

<p align="center">
  <img src="docs/performance-1000.jpg">
</p>

<h5 align="center">
  Benchmarking stripped versions of frameworks with no ORM under 1,000 concurrent connections
</h5>

As you can see, the right architecture provides it with tenfold advantage over Symfony and other popular frameworks. 

### Latency

How long it takes to get response from API often is even more important than overall service throughput. And that is where Comet really shines!

<p align="center">
  <img src="docs/latency-1.jpg">
</p>

<h5 align="center">
  Response latency of minimal versions of popular PHP frameworks under series of serial web requests
</h5>

Comet provides sub-millisecond latency for typical scenarios. Even under hard pressure of thousand concurrent connections it can compete with frameworks of compiled platforms like Go and Java.

### Too good to be true? 

You may run all benchmarks on your own to be sure charts are not scam: https://github.com/gotzmann/benchmarks

## Basics

### Installation

It is recommended that you use [Composer](https://getcomposer.org/) to install Comet.

```bash
$ composer require gotzmann/comet
```

This will install framework itself and all required dependencies. Comet requires PHP 7.1 or newer.

### Hello Comet

Create single app.php file at project root folder with content:

```php
<?php

use Comet\Comet;

require_once __DIR__ . '/vendor/autoload.php';

$app = new Comet();

$app->get('/hello', function ($request, $response) {
    $response
        ->getBody()
        ->write("Hello, Comet!");      
    return $response;
});

$app->run();
```

Start it from command line:

```bash
$ php app.php start
```

Then open browser and type in default address http://localhost - you'll see hello from Comet!

### Simple JSON Response

Let's start Comet server listening on custom port and returning JSON payload.

```php
<?php

use Comet\Comet;

require_once __DIR__ . '/vendor/autoload.php';

$app = new Comet([
    'host' => 'localhost',
    'port' => 8080,
]);

$app->get('/json', function ($request, $response) {        
    $data = [        
        "code" => 200, 
        "message" => "Hello, Comet!",        
    ];
    $payload = json_encode($data);
    $response
        ->getBody()
        ->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json');
});

$app->run();
```

Start Postman and see the JSON resonse from GET http://localhost:8080

## Advanced Topics

### PSR-4 and Autoloading

Before you proceed with complex examples, be sure that your composer.json contains autoload section like this:

```bash
    "autoload": {
        "psr-4": { "\\": "src/" }
    }
```    

If not, you should add the section mentioned above and update all vendor packages and autoload logic by command:

```bash
$ composer install
```    

### Controllers

Create src/Controllers/SimpleController.php:

```php
<?php

namespace Controllers;

use Nyholm\Psr7\ServerRequest as Request;
use Nyholm\Psr7\Response;

class SimpleController
{    
    private static $counter = 0;

    public function getCounter(Request $request, Response $response, $args)
    {
        $response->getBody()->write(self::$counter);  
        return $response->withStatus(200);
    }

    public function setCounter(Request $request, Response $response, $args)    
    {        
        $body = (string) $request->getBody();
        $json = json_decode($json);
        if (!$json) {
            return $response->withStatus(500);
        }  
        self::$counter = $json->counter;
        return $response;        
    }
}  
```    

Then create Comet server app.php at project root folder:

```php
<?php

use Comet\Comet;
use Controllers\SimpleController;

require_once __DIR__ . '/vendor/autoload.php';

$app = new Comet([
    'host' => 'localhost',
    'port' => 8080,
]);

$app->setBasePath("/api/v1"); 

$app->get('/counter',
    'Controllers\SimpleController:getCounter');

$app->post('/counter',    
    'Controllers\SimpleController:setCounter');

$app->run();
```

Now you are ready to get counter value with API GET endpoint. And pay attention to '/api/v1' prefix of URL:

GET http://localhost:8080/api/v1/counter

You can change counter sending JSON request for POST method:

POST http://localhost:8080/api/v1/counter with body { "counter": 100 } and 'application/json' header.

Any call with mailformed body will be replied with HTTP 500 code, as defined in controller.

## Deployment

### Docker

Please see [Dockerfile](Dockerfile) at this repo as starting point for creating your own app images and containers.

### Nginx

If you would like to use Nginx as reverse proxy or load balancer for your Comet app, insert into nginx.conf these lines:

```php
http {
 
    upstream app {
        server http://path.to.your.app:port;
    }
  
    server {
        listen 80;
         location / {
            proxy_pass         http://app;
            proxy_redirect     off;
        }
    }
}    
```
