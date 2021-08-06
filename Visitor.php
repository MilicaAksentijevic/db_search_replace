<?php


interface Visitor
{
    function writeResult($data);

    function destroy();
}
