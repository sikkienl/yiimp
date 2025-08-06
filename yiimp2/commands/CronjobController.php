<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace app\commands;

use app\components\rpc\WalletRPC;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CronjobController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @return int Exit code
     */
    public function actionIndex()
    {
        $coin = Coins::find()->where(['symbol' => 'INN'])->one();

        $remote = new WalletRPC($coin);

        $block = $remote->getblocktemplate(null);
        var_dump($block);
        return 0;
    }
}
