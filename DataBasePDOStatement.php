<?php

require_once 'DataBaseStatement.php';

class DataBasePDOStatement implements DataBaseStatement
{
    private $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function fetch()
    {
        return $this->statement->fetch();
    }

    public function fetchAll()
    {
        return $this->statement->fetchAll();
    }

}
