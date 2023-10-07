<?php

namespace testTask\Test;
require __DIR__ . "/../src/Sanitazer.php";
use PHPUnit\Framework\TestCase;
use testTask\sanitazer\Sanitazer;

class SanitazerTest extends TestCase
{
    public function testCreateSanitazer(): void
    {
        $sanitazer = new Sanitazer([
            'foo'=> Sanitazer::INT,
            "bar"=> Sanitazer::STRING,
            "baz" => Sanitazer::PHONE,
            "arr"=> [Sanitazer::ARRAY, Sanitazer::INT, 3],
            "map"=>[
                Sanitazer::MAP,
                ["map_phone"=> Sanitazer::PHONE, "map_int"=> Sanitazer::PHONE]
            ]
        ]);

        $this->assertSame([
            "foo" => 123,
            "bar" => "asd",
            "baz" => '77072885623',
            "arr" => [
                1,
                2,
                3,
            ],
            "map" => [
                "map_phone"=> '77072885623'
            ]
        ],
            $sanitazer->getConvertedJSON(
                    '{
                    "foo": "123", 
                    "bar": "asd", 
                    "baz": "8 (707) 288-56-23", 
                    "arr": ["1", "2", "3"], 
                    "map": {"map_phone": "8 (707) 288-56-23"}
                }'
            )
        );

    }
}