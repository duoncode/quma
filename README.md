Quma
====

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
