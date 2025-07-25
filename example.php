<?php
namespace bonifac0\pokus;

use bonifac0\VytrvalecValidator\Validator;
require_once __DIR__ . '/vendor/autoload.php';

$validator = new Validator();
//     __DIR__ . '/resources/credentials.txt',
//     __DIR__ . '/errors/errors_test.txt',
//     __DIR__ . '/resources/payload_prompts.json',
//     __DIR__ . '/resources/data_output_format.json'
// );

$imagePath = __DIR__ . '/testdata/test_image_good.jpg';


[$resp, $code] = $validator->validate(
    $imagePath,
    distance: 7900,
    elevation: 110,
    makelogs: true
);
echo "resp: ";
if ($resp) {
    echo "succes";
} else {
    echo "fail";
}
echo "\ncode: ";
echo $code;
echo "\n";