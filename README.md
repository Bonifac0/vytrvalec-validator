# Validator Library

A PHP library containing an LLM-based validator for the **Vytrvalec** project.

## Requirements

- A running Ollama server.
- `credentials.txt` file in the `resources/` folder (or another path specified in the `Validator` constructor), formatted as:

  ```
  URL
  login
  password
  ```

- `credentials.txt` available upon request from Bonifac0 (or you may use your own server).

## Prerequisites

Your `composer.json` must include:

```json
"require": {
    "bonifac0/vytrvalec-validator": "dev-main"
},
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/Bonifac0/vytrvalec-validator"
    }
]
```

## Usage

### Example

```php
use bonifac0\VytrvalecValidator\Validator;

$validator = new Validator();

[$resp, $code] = $validator->validate(
    $imagePath,
    distance: 7900,
    elevation: 110,
    makelogs: false
);
```

The `validate()` function takes the path to an image and user-provided `distance` and `elevation`.

It returns:

- **$resp**
  - `true` if the user data and image match and the competition rules are followed.
  - `false` otherwise.

- **$code**
  - `0` – OK  
  - `1` – Rules not followed  
  - `2` – Distance mismatch  
  - `3` – Elevation mismatch  
  - `4` – Other error

## Running Tests

Before committing changes, please run:

```bash
vendor/bin/phpunit tests
```
## TODO
Add fraud detection and test of it.
Rewrite payload_prompts.json rule definition (simplify it)
---

Written by **Bonifac0**