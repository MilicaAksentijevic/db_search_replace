<?php

require_once 'DataBase.php';

class DataBasePDO implements DataBase
{
    public $connection;

    public $name;
    public $host;
    public $password;
    public $user;

    public function __construct($configuration)
    {
        $this->setConfiguration($configuration);
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        );
        try {
            $this->connection = new PDO(self::getDsn($configuration), $this->user, $this->password, $options);
        } catch (PDOException $e) {
          throw $e;
        }
    }

    /**
     * @param string $query
     * @param array  $parameters
     *
     * @return DataBasePDOStatement
     *
     */
    public function query($query, array $parameters = array())
    {
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);
        return new DataBasePDOStatement($statement);
    }

    public function execute($statement, $parameters)
    {
        // TODO: Implement execute() method.
    }

    public function escape($value)
    {
        $this->connection->quote($value);
    }

    public function close()
    {
        $this->connection = null;
    }

    public function setCharset($charset)
    {
        $this->connection->exec(sprintf('SET NAMES %s', $charset));
    }

    public function setConfiguration($configuration)
    {
        $this->name     = $configuration['dbName'];
        $this->password = $configuration['dbPassword'];
        $this->user     = $configuration['dbUser'];
        $this->host     = $configuration['dbHost'];
    }

    public static function getDsn($configuration)
    {
        $pdoParameters = array(
            'dbname'  => $configuration['dbName'],
            'charset' => 'utf8',
        );
        $pdoParameters['host'] = $configuration['dbHost'];
        $pdoParameters['port'] = $configuration['dbPort'];
        $parameters = array();
        foreach ($pdoParameters as $name => $value) {
            $parameters[] = $name.'='.$value;
        }
        return sprintf('mysql:%s', implode(';', $parameters));
    }

}
