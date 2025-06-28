<?php
namespace bonifac0\pokus;

use bonifac0\VytrvalecValidator\Validator;
require_once __DIR__ . '/vendor/autoload.php';

$validator = new Validator(
    __DIR__ . '/credentials.txt',
    __DIR__ . '/errors/errors_test.txt',
    __DIR__ . '/rules.json',
    __DIR__ . '/output_schema.json'
);

$imagePath = __DIR__ . '/testdata/test_image.jpg';


[$resp, $code] = $validator->validate(
    $imagePath,
    distance: 7900,
    elevation: 110,
    makelogs: true
);
echo "resp ";
echo $resp + 1;
echo "\n";
echo $code;
echo "\n";