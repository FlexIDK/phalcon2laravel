<?php

namespace One23\Phalcon2laravel\Session;

use Phalcon\Session\Manager as PhManager;
use Phalcon\Session\ManagerInterface;

/**
 * @method Adapter\LaravelDb getAdapter
 */
class Manager extends PhManager {
    protected bool $encrypt = true;

    public function __construct(array $options = [])
    {
        if (isset($options['encrypt'])) {
            $this->encrypt = !!$options['encrypt'];
        }

        parent::__construct($options);
    }

    //

    public function setId(string $sessionId): ManagerInterface
    {
        if ($this->encrypt && !$this->getAdapter()->isEncryptSessionId($sessionId)) {
            $sessionId = $this->getAdapter()->encryptSessionId($sessionId);
        }

        return parent::setId($sessionId);
    }

    public function regenerateId(bool $deleteOldSession = true): ManagerInterface
    {
        if ($deleteOldSession) {
            $this->destroy();
        }

        $this->generateCustomId();

        $this->start();

        return $this;
    }

    public function start(): bool
    {
        if (!$this->getId()) {
            $this->generateCustomId();
        }

        $params = session_get_cookie_params();

        setcookie(
            $this->getName(),
            $this->getId(),
            [
                'expires'   => $params['lifetime'] ?: null,
                'path'      => $params['path'],
                'domain'    => $params['domain'] ?: null,
                'secure'    => !!$params['secure'],
                'httponly'  => !!$params['httponly'],
                'samesite'  => $params['samesite'],
            ]
        );

        return parent::start();
    }

    //

    /**
     * Check exist session in Db
     *
     * @param string $sessionId
     * @return bool
     */
    public function existId(string $sessionId): bool {
        return $this->getAdapter()->exist($sessionId);
    }

    /**
     * Create custom cookie session_id
     *
     * @return string
     * @throws \Phalcon\Encryption\Security\Exception
     */
    public function generateCustomId(): string {
        $sessionId = (new \Phalcon\Encryption\Security\Random())->base58(
            $this->getAdapter()->idLength
        );

        $this->setId($sessionId);

        return $sessionId;
    }

}

