<?php


class DataBaseMySQLiStatement implements DataBaseStatement
{
    private $statement;

    public function __construct(mysqli_result $statement)
    {
        $this->statement = $statement;
    }

    public function fetch()
    {

    }

    public function fetchAll()
    {

    }
}
