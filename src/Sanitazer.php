<?php

namespace testTask\sanitazer;

class Sanitazer
{
    public $mask = array();
    public const STRING = 'string';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const PHONE = 'phone';
    public const ARRAY = 'array';
    public const MAP = 'map';

    public const SIMPLE_TYPES = [self::STRING, self::INT, self::FLOAT, self::PHONE];


    public function __construct(array $mask)
    {
        $this->mask = $mask;
    }

    public function getConvertedJSON(string $json_sample)
    {
        $output = [];

        $json = json_decode($json_sample, true);

        if ($this->validation($json)) {
            $output = $this->normalize($json);
        }

        return $output;
    }

    private function normalize($json)
    {
        $normalized_json = [];
        foreach ($json as $key => $value) {
            $type = $this->mask[$key];
            if (in_array($type, self::SIMPLE_TYPES)) {
                $normalized_json[$key] = $this->normalizeSimpleType($value, $type);
            } else {
                if ($type[0] == self::ARRAY) {
                    $normalized_json[$key] = [];
                    foreach ($value as $arr_val) {
                        $normalized_json[$key][] = $this->normalizeSimpleType($arr_val, $type[1]);
                    }
                } elseif ($type[0] == self::MAP) {
                    $normalized_json[$key] = [];
                    $map_types = $type[1];
                    foreach ($value as $map_key => $map_val) {
                        $normalized_json[$key][$map_key] = $this->normalizeSimpleType($map_val, $map_types[$map_key]);
                    }
                }
            }
        }
        return $normalized_json;
    }

    private function normalizeSimpleType($value, $type)
    {
        $res;
        if ($type == self::STRING) {
            $res = strval($value);
        } elseif ($type == self::INT) {
            $res = intval($value);
        } elseif ($type == self::FLOAT) {
            $res = floatval($value);
        } elseif ($type == self::PHONE) {
            $pattern = '/[^0-9]/';
            $res = preg_replace($pattern, "", $value);
            $res[0] = 7;
        }
        return $res;
    }


    private function validation(array $json)
    {
        foreach ($json as $field_name => $field_value) {
            if (array_key_exists($field_name, $this->mask)) {
                $type = $this->mask[$field_name];
                $res = $this->validateType($field_value, $type, $field_name);
                if (!$res) {

                    throw new Exception('Не удалось обработать значение - ' . $field_value . ' для ключа - ' . $field_name);
                }
            } else {
                throw new Exception('В маске отсутсвует ключ ' . $field_name);
            }

        }

        return true;
    }

    private function validateString($field_value)
    {
        $result = false;
        if (is_string($field_value)) {
            $result = true;
        }
        return $result;
    }

    private function validateInt($field_value)
    {
        $result = false;
        if (is_int($field_value)) {
            $result = true;
        } elseif (is_string($field_value)) {
            if (ctype_digit($field_value)) {
                $result = true;
            }
        }
        return $result;

    }

    private function validateFloat($field_value)
    {
        $result = false;
        if (is_float($field_value)) {
            $result = true;
        } elseif (is_string($field_value)) {
            if (is_numeric($field_value)) {
                $result = true;
            }
        }
        return $result;

    }

    private function validatePhone($field_value)
    {
        $result = false;
        if (is_string($field_value)) {
            $field_value = str_replace(' ', '', $field_value);
            $result = preg_match("/^((\+7)|7|8)\([7]{1}[0-9]{2}\)[0-9]{3}\-[0-9]{2}\-[0-9]{2}$/", $field_value);
        }
        return $result;
    }

    private function validateArray($field_value, $type, $field_name)
    {
        if (!is_array($field_value)) {
            throw new Exception('Значение ' . $field_name . ' не является массивом');
        }

        $type_in_array = $this->mask[$field_name][1];
        $count_in_array = $this->mask[$field_name][2];

        if (count($field_value) !== $count_in_array) {
            throw new Exception('В маске для массива ' . $field_name . ' не соответсвует длина');
        }

        if ($type_in_array == self::ARRAY || $type_in_array == self::MAP) {
            throw new Exception('В маске для массива ' . $field_name . ' значение указано ' . $type . ', на данный момент класс не поддерживает данную возможность');
        }

        foreach ($field_value as $value) {
            $res = $this->validateType($value, $type_in_array);
            if (!$res) {
                throw new Exception('В массиве - ' . $field_name . ' присутсвуют значения не соответсвующие ' . $type);
            }
        }
        return true;
    }

    private function validateMap($field_value, $type, $field_name)
    {
        if (!is_array($field_value)) {
            throw new Exception('Значение ' . $field_name . ' не является массивом');
        }

        $map_types = $this->mask[$field_name][1];

        foreach ($field_value as $key => $value) {

            if (array_key_exists($key, $map_types)) {
                if (!in_array($map_types[$key], self::SIMPLE_TYPES)) {
                    throw new Exception('В маске для массива ' . $field_name . ' значение для ключа ' . $key . ' указано ' . $map_types[$key] . ', на данный момент класс не поддерживает данную возможность');
                }

                $res = $this->validateType($value, $map_types[$key]);
                if (!$res) {
                    throw new Exception('В массиве - ' . $field_name . ' для ключа ' . $key . ' тип указана неверно');
                }

            } else {
                throw new Exception('В массиве ' . $field_name . ' нет ключа ' . $key);
            }
        }
        return true;
    }

    private function validateType($field_value, $type, $field_name = '')
    {
        $res = false;
        if (in_array($type, self::SIMPLE_TYPES)) {
            if ($type == self::STRING) {
                $res = $this->validateString($field_value);
            } elseif ($type == self::INT) {
                $res = $this->validateInt($field_value);
            } elseif ($type == self::FLOAT) {
                $res = $this->validateFloat($field_value);
            } elseif ($type == self::PHONE) {
                $res = $this->validatePhone($field_value);

            }
        } else {
            $type = $type[0];
            if ($type == self::ARRAY) {
                $res = $this->validateArray($field_value, $type, $field_name);
            } elseif ($type == self::MAP) {
                $res = $this->validateMap($field_value, $type, $field_name);
            }
        }


        return $res;
    }
}

$sanitazer = new Sanitazer(
    [
        'foo'=> Sanitazer::INT,
        "bar"=> Sanitazer::STRING,
        "baz" => Sanitazer::PHONE,
        "arr"=> [Sanitazer::ARRAY, Sanitazer::INT, 3],
        "map"=>[
            Sanitazer::MAP,
            ["map_phone"=> Sanitazer::PHONE, "map_int"=> Sanitazer::PHONE]
        ]
    ]
);
print_r(
    $sanitazer->getConvertedJSON(
        '{
        "foo": "123", 
        "bar": "asd", 
        "baz": "8 (707) 288-56-23", 
        "arr": ["1", "2", "3"], 
        "map": {"map_phone": "8 (707) 288-56-23"}}'
    )
);

?>