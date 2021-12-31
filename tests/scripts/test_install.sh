#!/bin/sh -e


php ../../make_composer_json.php composer.json << EOF
{
    "require": {
        "ziiframework/zii": "~3.5.4"
    }
}
EOF

$COMPOSER_BINARY install

test -f vendor/autoload.php || (echo "vendor/autoload.php does not exist!"; exit 1)
test -f vendor/ziiframework/extensions.php || (echo "vendor/ziiframework/extensions.php does not exist!"; exit 1)
test -d vendor/ziiframework/zii || (echo "vendor/ziiframework/zii does not exist!"; exit 1)
