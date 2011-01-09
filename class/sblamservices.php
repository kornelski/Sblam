<?php

require_once "class/sblamhttp.php";

class SblamServices implements ISblamServices
{
    function __construct(PDO $db)
    {
        $this->db = $db;
    }

    function getDB()
    {
        return $this->db;
    }

    function getHTTP()
    {
        return new SblamHTTP();
    }
}
