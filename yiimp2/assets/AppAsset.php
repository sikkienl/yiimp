<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/site.css',
        'css/main.css',
        'css/table.css',
    ];
    public $js = [
        'js/auto_refresh.js',
        'js/jquery.tablesorter.js'
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset'
    ];
}
