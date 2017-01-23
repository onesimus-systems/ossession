<?php
/**
 * osSession - Centralized session handling for PHP applications
 *
 * @author Lee Keitel  <keitellf@gmail.com>
 * @copyright 2015 Lee Keitel, Onesimus Systems
 *
 * @license BSD 3-Clause
 */
namespace Onesimus\Session;

use PDO;
use SC;

class SessionHandler implements \SessionHandlerInterface
{
    protected $sessionName = '';
    protected $timeout = 120;
    protected $gcLotto = [1, 100];
    protected $table = '';

    private $sc;

    /**
     * SessionHandler constructor
     * @param PDO $pdo - PDO object to use for connection
     * @param array $options - Options for the session handler. Available options are:
     *                       'gclotto' = [odds, max] Used to calculate change of a garbage collection
     *                       'timeout' = Time in minutes a session is considered valid since its last accessed time
     *                       'table' = Database table name to use for storage
     *                       'sc' = An SC (Onesimus\SC) object to use instead of a raw PDO
     */
    public function __construct(PDO $pdo, array $options)
    {
        $this->gcLotto = $this->getOptions($options, 'gclotto', [1, 100]);
        $this->timeout = $this->getOptions($options, 'timeout', 120);
        $this->table = $this->getOptions($options, 'table', '');
        $this->sc = $this->getOptions($options, 'sc', null);

        if (is_null($this->sc)) {
            $this->sc = new SC\SC($pdo);
        }
    }

    private function getOptions(array $options, $name, $default)
    {
        if (array_key_exists($name, $options)) {
            return $options[$name];
        }
        return $default;
    }

    public function open($savePath, $sessionName)
    {
        $this->sessionName = $sessionName;

        // Garbage collection
        $odds = $this->gcLotto[0];
        $max = $this->gcLotto[1];

        if ($odds > 0) {
            // If GC is enabled, do it
            if ($max < 1) {
                $max = 100;
            }

            if (mt_rand(0, $max - 1) < $odds) {
                $this->gc($this->timeout);
            }
        }
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $session = $this->readFromDB($id);
        return is_null($session) ? '' : $session;
    }

    private function readFromDB($id)
    {
        $session = $this->sc->readItem($this->table, $id);
        return isset($session['data']) ? $session['data'] : null;
    }

    public function write($id, $data)
    {
        $session = $this->readFromDB($id);

        if (!is_null($session)) {
            $this->sc->updateItem($this->table, $id, [
                'data' => $data,
                'last_accessed' => time()
            ]);
        } else {
            $this->sc->createItem($this->table, [
                'id'   => $id,
                'data' => $data,
                'last_accessed' => time()
            ]);
        }

        return true;
    }

    public function destroy($id)
    {
        $this->sc->deleteItem($this->table, $id);
        SessionManager::clear();
        return true;
    }

    public function gc($maxlifetime)
    {
        $r = $this->sc->find($this->table)
            ->where('last_accessed + ? < ?', [$maxlifetime, time()])
            ->delete();
        return (bool) $r;
    }
}
