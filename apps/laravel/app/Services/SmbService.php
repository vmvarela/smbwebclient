<?php

namespace App\Services;

use Icewind\SMB\ServerFactory;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\IShare;

class SmbService
{
    protected $serverFactory;

    public function __construct()
    {
        $this->serverFactory = new ServerFactory();
    }

    public function getShare($host, $shareName, $username, $password): IShare
    {
        $auth = new BasicAuth($username, 'workgroup', $password);
        $server = $this->serverFactory->createServer($host, $auth);
        return $server->getShare($shareName);
    }
}
