<?php

require_once 'src/file_handling.php';

class FileHandlingTest extends \PHPUnit_Framework_Testcase
{

    public function testCanBeNegated()
    {
        $filename = "apsley_2016-03-27_heb-09_carolan.mp3";

        $result = handle_file($filename);


        $this->assertEquals('Carolan', $result['author']);
        $this->assertEquals('Heb-09', $result['title']);
        $this->assertEquals('2016-03-27', $result['date']);
    }

}
