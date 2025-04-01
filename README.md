Quma
====

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/duoncode/quma.svg)](https://scrutinizer-ci.com/g/duoncode/quma/code-structure)
[![Psalm coverage](https://shepherd.dev/github/duoncode/quma/coverage.svg?)](https://shepherd.dev/github/duoncode/quma)
[![Psalm level](https://shepherd.dev/github/duoncode/quma/level.svg?)](https://duoncode.dev/quma)
[![Quality Score](https://img.shields.io/scrutinizer/g/duoncode/quma.svg)](https://scrutinizer-ci.com/g/duoncode/quma)

Quma is a PHP port of the similary named Python library [quma](https://quma.readthedocs.io).

## Test Databases

### Mysql

    CREATE DATABASE quma_db;
    CREATE USER quma_user@'%' IDENTIFIED BY 'quma_password';
    GRANT ALL ON quma_db.* TO quma_user@'%';

### PostgreSQL

    CREATE DATABASE quma_db WITH TEMPLATE = template0 ENCODING = 'UTF8';
    CREATE USER quma_user PASSWORD 'quma_password';
    GRANT ALL PRIVILEGES ON DATABASE quma_db TO quma_user;
    ALTER DATABASE quma_db OWNER TO quma_user;
