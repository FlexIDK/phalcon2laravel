# Phalcon 5 DB session in Laravel format

Phalcon DB session adapter in laravel format 

## Install

```bash
composer require one23/phalcon2laravel
```

Create default laravel session table

```sql
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Quick usage

```php
$adapter = new \One23\Phalcon2laravel\Session\Adapter\Db([
    'db' => function() {
        $di = \Phalcon\Di\Di::getDefault();

        return $di->get('db');
    }, // required instance of Phalcon\Db\Adapter\AbstractAdapter
    'table' => 'sessions', // Session table in DB. Default: sessions
    'serialize_handler' => 'php_serialize', // Session serialize handler. For laravel default: serialize_handler 
    'ip' => '127.0.0.1', // User IP. Default: $request->getClientAddress() 
    'user' => function() {
        return 1;
    }, // Closure with return user id (int). Default: null 
    'cookie_name' => 'laravel_session', // required. Laravel session cookie name (required for decrypt)
    'lifetime'      => 3600, // Session lifetime. Default: 3600 
]);

// or with decrypt Laravel active session (can use phalcon + laravel together)

$adapter = new \One23\Phalcon2laravel\Session\Adapter\LaravelDb([
    'db' => function() {
        $di = \Phalcon\Di\Di::getDefault();

        return $di->get('db');
    }, // required instance of Phalcon\Db\Adapter\AbstractAdapter
    'table' => 'sessions', // Session table in DB. Default: sessions
    'serialize_handler' => 'php_serialize', // Session serialize handler. For laravel default: serialize_handler 
    'ip' => '127.0.0.1', // User IP. Default: $request->getClientAddress() 
    'user' => function() {
        return 1;
    }, // Closure with return user id (int). Default: null 
    'laravel_key'    => 'abc123', // required. Laravel app secret
    'laravel_chiper' => 'AES-256-CBC', // Laravel app secret chiper

    'cookie_name' => 'laravel_session', // required. Laravel session cookie name (required for decrypt)
    'lifetime'      => 3600, // Session lifetime. Default: 3600 
]);

$sessionManager = new \One23\Phalcon2laravel\Session\Manager();

$sessionManager
    ->setName($adapter->getLaravelSessionCookieName())
    ->setAdapter($adapter);
    
$sessionManager->start();
```

## API Methods

### \One23\Phalcon2laravel\Session\Manager

#### existId(string $sessionId): bool

```php
$sessionManager->existId('abc123'); // => bool
```

#### generateCustomId(): string

```php
$sessionManager->generateCustomId(); // => 'eyJpdiI6IldBcGRIU2p...FnIjoiIn0='
```

### \One23\Phalcon2laravel\Session\Adapter\Db

Cookie session id without encrypt  

#### exist($sessionId): bool

```php
$adapter->exist('abc123'); // => true 
```

#### getCookieName(): string

```php
$adapter->getCookieName(); // => 'laravel_session' 
```

### \One23\Phalcon2laravel\Session\Adapter\LaravelDb (extends \One23\Phalcon2laravel\Session\Adapter\Db)

Cookie session id with laravel encrypt

#### exist($sessionId): bool

```php
$adapter->exist('eyJpdiI6IldBcGRIU2p...FnIjoiIn0='); // => true 
```

#### decryptSessionId(string $sessionId): ?string

```php
$adapter->decryptSessionId('eyJpdiI6IldBcGRIU2p...FnIjoiIn0='); // => 'abc123'
```

#### encryptSessionId(string $sessionId): ?string

```php
$adapter->encryptSessionId('abc123'); // => 'eyJpdiI6IldBcGRIU2p...FnIjoiIn0='
```

#### isEncryptSessionId(string $sessionId): bool

```php
$adapter->isEncryptSessionId('eyJpdiI6IldBcGRIU2p...FnIjoiIn0='); // => true
```

---

## Security

If you discover any security related issues, please email eugene@krivoruchko.info instead of using the issue tracker.


## License

[MIT](https://github.com/FlexIDK/language-detection/LICENSE)
