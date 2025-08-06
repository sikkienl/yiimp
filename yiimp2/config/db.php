<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host='.YIIMP_DBHOST.';dbname='.YIIMP_DBNAME,
    'username' => YIIMP_DBUSER,
    'password' => YIIMP_DBPASSWORD,
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 60,
    'schemaCache' => 'cache',
];
