#!/bin/sh -e


php ../../make_composer_json.php composer.json << EOF
{

    "require": {
        "ziiframework/zii": "^3.8",
        "symfony/finder": "^5.4"
    },
    "config": {
        "allow-plugins": {
            "ziiframework/composer": true
        }
    }
}
EOF

$COMPOSER_BINARY update

php ../../make_composer_json.php composer.json << EOF
{

    "require": {
        "ziiframework/zii": "^3.8",
        "symfony/finder": "^6.0"
    },
    "config": {
        "allow-plugins": {
            "ziiframework/composer": true
        }
    }
}
EOF

$COMPOSER_BINARY update
