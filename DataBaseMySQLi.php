<?php


class DataBaseMySQLi implements DataBase
{
    public $connection;

    public $name;
    public $host;
    public $password;
    public $user;
    public $port;

    public function __construct($configuration)
    {
        $this->setConfiguration($configuration);

        // Silence possible warnings thrown by mysqli
        // e.g. Warning: mysqli::mysqli(): Headers and client library minor version mismatch. Headers:50540 Library:50623
        $this->connection = @new mysqli($this->host, $this->user, $this->password, $this->name, $this->port);

        if ($this->connection->connect_errno === 2002 && strtolower($this->host) === 'localhost') {
            // Attempt to recover from "[2002] No such file or directory" error.
            $this->connection = @new mysqli('127.0.0.1', $this->user, $this->password, $this->name, $this->port);
        }
        if (!$this->connection->ping()) {
            throw new Exception($this->connection->connect_error);
        }
    }

    /**
     * @param string $query
     * @param array  $parameters
     *
     * @return DataBaseMySQLiStatement
     *
     */
    public function query($query, array $parameters = array())
    {
        $statement = $this->connection->query($query, 0);
        return new DataBaseMySQLiStatement($statement);
    }

    public function execute($statement, $parameters)
    {
        // TODO: Implement execute() method.
    }

    public function escape($value)
    {
        return $value === null ? 'null' : "'".$this->connection->real_escape_string($value)."'";
    }

    public function close()
    {
        $this->connection = null;
    }

    public function setCharset($charset)
    {
        $this->connection->set_charset($charset);
    }

    public function setConfiguration($configuration)
    {
        $this->name     = $configuration['dbName'];
        $this->password = $configuration['dbPassword'];
        $this->user     = $configuration['dbUser'];
        $this->host     = $configuration['dbHost'];
    }
}
