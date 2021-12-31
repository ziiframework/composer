#!/bin/sh -e


php ../../make_composer_json.php composer.json << EOF
{

    "require": {
        "ziiframework/zii": "^3.5",
        "cebe/markdown": "~1.0.0"
    }
}
EOF

$COMPOSER_BINARY update

php ../../make_composer_json.php composer.json << EOF
{

    "require": {
        "ziiframework/zii": "^3.5",
        "cebe/markdown": "~1.1"
    }
}
EOF

$COMPOSER_BINARY update
