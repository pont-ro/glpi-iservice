<?php

class PluginIserviceDB extends DB
{

    public function __construct($dbHost, $dbName, $dbUser, $dbPassword)
    {
        $this->dbhost     = $dbHost;
        $this->dbuser     = $dbUser;
        $this->dbpassword = $dbPassword;
        $this->dbdefault  = $dbName;

        parent::__construct();
    }

}
