Puma
====

Puma is a PHP port of the Python library [quma](https://quma.readthedocs.io).

## Test Databases

### Mysql

    CREATE DATABASE puma_test_db;
    CREATE USER puma_test_user@'%' IDENTIFIED BY 'puma_test_password';
    GRANT ALL ON puma_test_db.* TO puma_test_user@'%';

### PostgreSQL

    CREATE DATABASE puma_test_db WITH TEMPLATE = template0 ENCODING = 'UTF8';
    CREATE USER puma_test_user PASSWORD 'puma_test_password';
    GRANT ALL PRIVILEGES ON DATABASE puma_test_db TO puma_test_user;
