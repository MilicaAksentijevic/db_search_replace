<?php


interface DataBase
{
    public function query($query, array $parameters = array());

    public function execute($statement, $parameters);

    public function escape($value);

    public function close();

    public function setCharset($charset);
}
