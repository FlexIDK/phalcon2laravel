<?php

namespace One23\Phalcon2laravel\Session\Adapter;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use Phalcon\Session\Exception;

class LaravelDb extends Db {

    protected string $laravelChiper = 'AES-256-CBC';
    protected string $laravelKey;

    public function __construct(array $options = []) {
        parent::__construct($options);

        $this->checkRequired($options, [
            'laravel_key',
        ]);

        //

        if(!is_string($options['laravel_key'])) {
            throw new Exception("The 'laravel_key' is not string");
        }

        $this->laravelKey = $options['laravel_key'];

        //

        if (!empty($options['laravel_chiper']) && is_string($options['laravel_chiper'])) {
            $this->laravelChiper = $options['laravel_chiper'];
        }
    }

    //

    protected function tryDecryptSessionId(string $sessionId): string {
        $decryptSessionId = $this->decryptSessionId($sessionId);

        return $decryptSessionId ?: $sessionId;
    }

    // encrypt && decrypt

    protected function parseKey(string $key): string {
        if (Str::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }

        return $key;
    }

    public function decryptSessionId(string $sessionId): ?string {
        try {
            $key = $this->parseKey($this->laravelKey);
            $enc = new Encrypter(self::parseKey($this->laravelKey), $this->laravelChiper);

            $val = $enc->decrypt($sessionId, false);
        }
        catch (\Exception $exception) {
            return null;
        }

        if ($val === false) {
            return null;
        }

        if (!CookieValuePrefix::validate($this->cookieName, $val, $key)) {
            return null;
        }

        $e = explode('|', $val);
        if (!$e[1]) {
            return null;
        }

        return (string)$e[1];
    }

    public function encryptSessionId(string $sessionId): string {
        $key = $this->parseKey($this->laravelKey);
        $enc = new Encrypter($key, $this->laravelChiper);

        $val = $enc->encrypt(
            CookieValuePrefix::create($this->cookieName, $key) .
            $sessionId,
            false
        );

        return $val;
    }

    public function isEncryptSessionId(string $sessionId): bool {
        if (\mb_strlen($sessionId, 'UTF-8') === $this->idLength) {
            return false;
        }

        return !!self::decryptSessionId($sessionId);
    }

}

