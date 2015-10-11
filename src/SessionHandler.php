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

    public function __construct(PDO $pdo, array $options)
    {
        $this->gcLotto = array_key_exists('gclotto', $options) ? $options['gclotto'] : [1, 100];
        $this->timeout = array_key_exists('timeout', $options) ? $options['timeout'] : 120;
        $this->table = array_key_exists('table', $options) ? $options['table'] : '';

        $this->sc = new SC\SC($pdo);
    }

    public function open($savePath, $sessionName)
    {
        $this->sessionName = $sessionName;

        // Garbage collection
        $odds = $this->gcLotto[0];
        $max = $this->gcLotto[1];

        if ($max < 1) {
            $max = 100;
        }

        if (mt_rand(0, $max - 1) < $odds) {
            $this->gc($this->timeout);
        }
        return true;
    }

    public function close()
    {
        $this->sc = null;
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

        return;
    }

    public function destroy($id)
    {
        $this->sc->deleteItem($this->table, $id);
        SessionManager::clear();
        return;
    }

    public function gc($maxlifetime)
    {
        $r = $this->sc->find($this->table)
            ->where('last_accessed + ? < ?', [$maxlifetime, time()])
            ->delete();
        return (bool) $r;
    }
}
