<?php
/**
 * Created by LotteryBot.
 * User: TingV
 * Date: 2019-05-25
 * Time: 14:50
 */

namespace app\tgbot\validate;

use think\Validate;

class Create extends Validate
{
    // 定义验证规则
    protected $rule = [
        'title|Prize Name' => [
            'length:1,100',
        ],
        'number|Number of prizes' => [
            'number',
            'egt:1',
            'elt:30',
        ],
        'prize|List of Prizes' => [
            'length:1,100',
        ],
        'keyword|keywords' => [
            'length:1,50',
        ],
        'conditions|Conditions for released prizes' => [
            'in:Release time of prize,Number of participants',
        ],
        'condition_time|开奖条件:时间' => [
            'date',
            'dateFormat:Y-m-d H:i',
            'checkDate:',
        ],
        'condition_hot|开奖条件:人数' => [
            'number',
            'egt:1',
        ],
        'notification|开奖通知' => [
            'in:Yes,No',
        ],
        'join_type|参与方式' => [
            'in:Send keywords in the group,Private message bot',
        ],
        'is_push_channel|是否推送活动到频道' => [
            'in:Yes I agree to push,No thanks',
        ],
        'chat_url|群组链接' => [
            'checkChatUrl:',
        ],
        'submit|确定或取消' => [
            'in:✅ Yes,🚫 No',
        ],
    ];

    protected $message = [
        'title.length' => 'Cannot be greater than 100 characters',
        'prize.length' => 'Cannot be greater than 100 characters',
        'keyword.length' => 'Cannot be greater than 50 characters',
        'number.number' => 'Please tell me a number',
        'number.egt' => 'Number of prizes must be greater than 0',
        'number.elt' => 'Number of prizes cannot be greater than 30',

        'conditions.in' => 'Please select from the keyboard',

        'condition_time.date' => 'Invalid time',
        'condition_time.dateFormat' => 'Time format error',
        'condition_time.checkDate' => 'Cannot be less than the current time',

        'condition_hot.number' => 'Please tell me a number',
        'condition_hot.egt' => 'Participants must be greater than 0',

        'notification.in' => 'Please select from the keyboard',

        'join_type.in' => 'Please select from the keyboard',

        'is_push_channel.in' => 'Please select from the keyboard',

        'chat_url.checkChatUrl' => 'For example <i>https://t.me/xxxx</i> ',

        'submit.in' => 'Please select from the keyboard',
    ];

    // 自定义验证规则
    protected function checkDate($value)
    {
        return strtotime($value) > time() ? true : false;
    }

    protected function checkChatUrl($value)
    {
        return preg_match('/^https:\/\/t\.me\/.+/i', $value) ? true : false;
    }
}