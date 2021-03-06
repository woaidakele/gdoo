<?php namespace App\Support;

use Gdoo\User\Models\User;
use Gdoo\Customer\Models\Customer;

class License
{
    public static function check($type)
    {
        $data = [
            'user' => 9999,
            'customer' => 9999,
        ];

        if ($type == 'user') {
            $count = User::group('user')->count('id');
            if ($count > $data['user']) {
                abort_error('无法新建用户授权许可不足。');
            }
        } else if ($type == 'customer') {
            $count = Customer::count('id');
            if ($count > $data['customer']) {
                abort_error('无法新建客户授权许可不足。');
            }
        }
    }

    /**
     * 设置演示表，操作时候进行判断
     */
    public static function demoCheck($table = null)
    {
        if (env('DEMO_VERSION') == false) {
            return;
        }

        $demoDatas = ['user','system_log'];

        if (in_array($table, $demoDatas)) {
            return;
        }

        abort_error('演示模式，不允许本操作。');
    }
}
