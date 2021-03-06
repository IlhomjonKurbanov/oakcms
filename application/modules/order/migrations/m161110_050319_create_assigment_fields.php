<?php
/**
 * @package    oakcms
 * @author     Hryvinskyi Volodymyr <script@email.ua>
 * @copyright  Copyright (c) 2015 - 2017. Hryvinskyi Volodymyr
 * @version    0.0.1-beta.0.1
 */

namespace app\modules\order\migrations;

use yii\db\Migration;

class m161110_050319_create_assigment_fields extends Migration
{
    public function up()
    {
        $this->addColumn('{{%order}}', 'is_assigment', $this->boolean());
        $this->addColumn('{{%order_element}}', 'is_assigment', $this->boolean());
    }

    public function down()
    {
        $this->dropColumn('{{%order}}', 'is_assigment');
        $this->dropColumn('{{%order_element}}', 'is_assigment');

        return true;
    }
}
