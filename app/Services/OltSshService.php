<?php

namespace App\Services;

use phpseclib3\Net\SSH2;

class OltSshService
{
    public function connect($ip, $username, $password)
    {
        $ssh = new SSH2($ip);

        if (!$ssh->login($username, $password)) {
            return false;
        }

        return $ssh;
    }
}

