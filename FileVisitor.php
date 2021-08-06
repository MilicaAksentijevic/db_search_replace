<?php

require_once 'Visitor.php';

class FileVisitor implements Visitor
{

    private $handler;
    public function __construct()
    {
        $this->handler = fopen('result.txt', 'w');
    }

    function writeResult($data)
    {
       $json = json_encode($data);
       fwrite($this->handler, $json . PHP_EOL);
    }

    function destroy()
    {
        fclose($this->handler);
    }


}
