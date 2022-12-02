<?php

namespace One23\Phalcon2laravel\Session\Adapter;

use Illuminate\Support\Str;
use Phalcon\Di\DiInterface;
use Phalcon\Session\Adapter\AbstractAdapter;
use Phalcon\Db\Adapter\AbstractAdapter as DbAbstractAdapter;
use Phalcon\Session\Exception;

class Db extends AbstractAdapter {

    protected $adapter;

    protected DbAbstractAdapter $db;

    protected string $table = 'sessions';
    protected string $serializeHandler = 'php_serialize';
    protected ?\Closure $user = null;
    protected ?string $ip = null;
    protected int $lifetime = 3600;
    protected string $cookieName;

    public int $idLength = 40;
    protected bool $dataFromSessionVar = true;

    protected bool $sessionExist = false;
    protected string $sessionId;
    protected ?array $sessionDb = null;

    protected function checkRequired(array $options, array $required) {
        foreach ($required as $key) {
            if (empty($options[$key])) {
                throw new Exception("The '{$key}' cannot be empty");
            }
        }
    }

    public function __construct(array $options = []) {
        $this->checkRequired($options, [
            'db', 'cookie_name',
        ]);

        //

        if (!$options['db'] instanceof \Closure) {
            throw new Exception("The 'db' is not instance of '\Closure'");
        }
        else {
            $db = $options['db']();
            if (!$db instanceof DbAbstractAdapter) {
                throw new Exception("The 'db' is not instance of '" . DbAbstractAdapter::class . "'");
            }

            $this->db = $db;
        }

        //

        if (!empty($options['table']) && is_string($options['table'])) {
            $this->table = $options['table'];
        }

        if (!$this->db->tableExists($this->table)) {
            throw new Exception("The table '{$this->table}' not exist in database");
        }

        //

        if(!empty($options['serialize_handler']) && is_string($options['serialize_handler'])) {
            $serializes = ['php_serialize', 'php', 'php_binary', 'wddx', ];
            if (!in_array($options['serialize_handler'], $serializes)) {
                throw new Exception("The 'serialize_handler' can only: " . implode(', ', $serializes));
            }

            $this->serializeHandler = $options['serialize_handler'];
        }

        ini_set('session.serialize_handler', $this->serializeHandler);

        //

        if (!empty($options['user'])) {
            if (!$options['user'] instanceof \Closure) {
                throw new Exception("The 'user' is not instance of '\Closure'");
            }

            $this->user = $options['user'];
        }

        //

        if (!empty($options['ip']) && is_string($options['ip'])) {
            $this->ip = $options['ip'];
        }

        //

        if (!empty($options['lifetime']) && is_integer($options['lifetime']) && $options['lifetime'] > 0) {
            $this->lifetime = $options['lifetime'];
        }

        //

        if (!empty($options['dataFromSessionVar'])) {
            $this->dataFromSessionVar = !!$options['dataFromSessionVar'];
        }

        //

        if(!is_string($options['cookie_name'])) {
            throw new Exception("The 'cookie_name' is not string");
        }

        $this->cookieName = $options['cookie_name'];
    }

    //

    public function close(): bool
    {
        return true;
    }

    /**
     * @param int $maxlifetime
     * @return false|int
     */
    public function gc(int $maxlifetime): int|false
    {
        $time = time() - $maxlifetime;

        //

        $result = $this->db->query("SELECT COUNT(*) as `cnt` FROM `{$this->table}` WHERE `last_activity` < :maxtimelife LIMIT 1", [
            'maxtimelife' => $time,
        ]);
        $row = $result->fetch();

        if ($row && $row['cnt']) {
            $this->db->delete(
                $this->table,
                "last_activity < :maxtimelife",
                [
                    'maxtimelife' => $time,
                ]
            );
        }

        return $row ?
            (int)$row['cnt']
            : false;
    }

    public function read($sessionId): string
    {
        $sessionId = $this->tryDecryptSessionId($sessionId);

        //

        $this->sessionId    = $sessionId;
        $this->sessionExist = false;

        $row = $this->getFromDb($sessionId);
        if (!$row) {
            return $this->defaultSerializeData();
        }

        if ($this->expired()) {
            return $this->defaultSerializeData();
        }

        $this->sessionExist = true;
        $payload = base64_decode($row['payload']);
        if ($payload === false) {
            return $this->defaultSerializeData();
        }

        return $payload;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function write($sessionId, $data): bool
    {
        $sessionId = $this->tryDecryptSessionId($sessionId);

        //

        if ($this->dataFromSessionVar && isset($_SESSION)) {
            $payload = base64_encode(
                serialize($_SESSION)
            );
        }
        else {
            $payload = base64_encode($data);
        }

        //

        /** @var DiInterface $di */
        $di = \Phalcon\Di\Di::getDefault();

        /** @var \Phalcon\Http\Request $request */
        $request = $di->get('request');

        //

        $ip = $this->ip ?: $request->getClientAddress();

        $ua = $request->getUserAgent();
        $ua = Str::substr($ua, 0, 255);

        $user_id = null;
        if ($this->user instanceof \Closure) {
            $user = ($this->user)();
            $user_id = is_integer($user) ? $user : null;
        }

        //

        $fields = [
            'ip_address'    => $ip,
            'user_id'       => $user_id,
            'user_agent'    => $ua,
            'last_activity' => time(),
            'payload'       => $payload,
        ];

        if (!$this->exist($sessionId)) {
            return $this->db->insertAsDict($this->table, [
                'id' => $sessionId,
                ...$fields,
            ]);
        }
        else {
            return $this->db->updateAsDict(
                $this->table,
                $fields,
                [
                    'conditions' => 'id = ?',
                    'bind' => [
                        $sessionId,
                    ],
                    'bindTypes' => [
                        'id' => \PDO::PARAM_STR,
                    ]
                ]
            );
        }
    }

    //

    public function destroy($sessionId): bool
    {
        $sessionId = $this->tryDecryptSessionId($sessionId);

        //

        return $this->db->delete(
            $this->table,
            "id = :id",
            [
                'id' => $sessionId
            ]
        );
    }

    //

    public function exist($sessionId): bool
    {
        $sessionId = $this->tryDecryptSessionId($sessionId);

        //

        $result = $this->db->query("SELECT COUNT(*) as `cnt` FROM `{$this->table}` WHERE `id` = :session_id LIMIT 1", [
            'session_id' => $sessionId,
        ]);

        $row = $result->fetch();
        if (!$row) {
            return false;
        }

        return !!$row['cnt'];
    }

    //

    protected function expired(): bool {
        if (!$this->sessionDb) {
            return true;
        }

        if (!$this->sessionDb['last_activity']) {
            return true;
        }

        $time = time() - $this->lifetime;
        if ((int)$this->sessionDb['last_activity'] < $time) {
            return true;
        }

        return false;
    }

    protected function getFromDb($sessionId): ?array {
        $result = $this->db->query("SELECT * FROM `{$this->table}` WHERE `id` = :session_id LIMIT 1", [
            'session_id'    => $sessionId,
        ]);

        $result->setFetchMode(
            \Phalcon\Db\Enum::FETCH_ASSOC
        );

        $row = $result->fetch();
        if (!$row) {
            return null;
        }

        $this->sessionDb = $row;

        return $row;
    }

    protected function defaultSerializeData(): string {
        if ($this->serializeHandler === 'php_serialize') {
            return serialize([]);
        }

        return "";
    }

    //

    protected function tryDecryptSessionId(string $sessionId): string {
        return $sessionId;
    }

    public function getCookieName(): string {
        return $this->cookieName;
    }

    public function isEncryptSessionId(string $sessionId): bool {
        return false;
    }

    public function encryptSessionId(string $sessionId): string {
        return $sessionId;
    }

}

