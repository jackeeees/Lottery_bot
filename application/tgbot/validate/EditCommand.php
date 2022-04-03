<?php
namespace app\tgbot\validate;

use think\Validate;

class EditCommand extends Validate
{
    // 定义验证规则
    protected $rule = [
        'field|修改的字段' => [
            'in:Prize Name,Release time of prize,Number of participants,Cancel',
        ],
        'title|奖品名称' => [
            'length:1,200',
        ],
        'condition_time|开奖条件:时间' => [
            'date',
            'dateFormat:Y-m-d H:i',
            'checkDate:',
        ],
        'condition_hot|开奖条件:人数' => [
            'number',
            'checkNumber:',
        ],
    ];

    protected $message = [
        'field.in' => 'Please select from the keyboard',

        'title.length' => 'Cannot be greater than 200 characters',

        'condition_time.date' => 'Invalid time',
        'condition_time.dateFormat' => 'Time format error',
        'condition_time.checkDate' => 'Cannot be less than the current time',

        'condition_hot.number' => 'Please tell me a number',
        'condition_hot.checkNumber' => 'Must be greater than the number of participants',
    ];

    // 自定义验证规则
    protected function checkDate($value)
    {
        return strtotime($value) > time() ? true : false;
    }

    protected function checkNumber($value, $rule, $data)
    {
        // trace($data,'info');
        return $value > $data['hot'] ? true : false;
    }
}