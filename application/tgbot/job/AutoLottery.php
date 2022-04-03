<?php
namespace app\tgbot\job;

use think\queue\Job;
use think\Db;
use think\Queue;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use app\tgbot\model\LotteryChannel;

// 自动开奖队列
class AutoLottery
{
    /**
     * fire方法是消息队列默认调用的方法
     * @param Job            $job      当前的任务对象
     * @param array|mixed    $data     发布任务时自定义的数据
     */
    public function fire(Job $job, $data){
        if ($job->attempts() > 3) {
            return $job->delete();
        }

        $config = module_config('tgbot.bot_token,bot_username,bot_id,channel_id,channel_username');
        $bot_api_key  = $config['bot_token'];
        $bot_username = $config['bot_username'];
        $bot_id       = $config['bot_id'];
        $channel_id = $config['channel_id'];
        $channel_username = $config['channel_username'];

        try {
            new Telegram($bot_api_key, $bot_username);
        } catch (TelegramException $e) {
        }

        // 清除删 9 留 1 计数器
        cache('counter:' . $data['id'], NULL);

        // 清除群里是否有抽奖活动的缓存记录
        cache('has_lottery:' . $data['chat_id'], NULL);

        // 获取机器人信息
        $bot_info = Request::getChatMember([
            'chat_id' => $data['chat_id'],
            'user_id' => $bot_id,
        ]);
        if ($bot_info->isOk() == false){
            $job->delete();
            return Request::sendMessage([
                'chat_id' => $data['chat_id'],
                'text'    => 'Bot information verification failed, the prize cannot be released!',
            ]);
        }

        // 验证机器人是否为管理员 “administrator”
        $member_bot_status = $bot_info->getResult()->getStatus();
        if ($member_bot_status != 'administrator'){
            cache('bot_info:' . $data['chat_id'], NULL);
            $job->delete();
            return Request::sendMessage([
                'chat_id' => $data['chat_id'],
                'text'    => 'Bot administrator canceled, the prize cannot be released!',
            ]);
        }

        // 超级群权限检查
        if ($data['chat_type'] == 'supergroup'){
            $can_delete_messages = $bot_info->getResult()->getCanDeleteMessages();
            $can_pin_messages = $bot_info->getResult()->getCanPinMessages();
            if ($can_delete_messages == false || $can_pin_messages == false){
                cache('bot_info:' . $data['chat_id'], NULL);
                $job->delete();
                return Request::sendMessage([
                    'chat_id' => $data['chat_id'],
                    'text'    => 'Bot administrator canceled, the prize cannot be released!',
                ]);
            }
        }

        // 修改频道里活动的状态
        if ($data['is_push_channel'] == 1){
            $lottery_channel_info = LotteryChannel::get(['lottery_id'=>$data['id']]);
            if ($lottery_channel_info && $lottery_channel_info->status == 1){
                $conditions = [
                    1 => 'Release time of prize',
                    2 => 'Number of participants',
                ];
                $condition_text = '';
                if ($data['conditions'] == 1){
                    $condition_text = 'Release time of prize:' . date('Y-m-d H:i', $data['condition_time']);
                }
                if ($data['conditions'] == 2){
                    $condition_text = 'Number of participants:' . $data['condition_hot'] . PHP_EOL .
                                      'Release time of prize:'. date('Y-m-d H:i', $data['time']);
                }

                $keyboard_buttons[] = new InlineKeyboardButton([
                    'text'          => 'Join',
                    'url'          => $data['chat_url'],
                ]);
                $keyboard_buttons[] = new InlineKeyboardButton([
                    'text'          => 'Share',
                    'url'          => 'https://t.me/' . $bot_username . '?start=share-' . $lottery_channel_info->id,
                ]);

                Request::editMessageText([
                    'chat_id' => $channel_id,
                    'message_id' => $lottery_channel_info->message_id,
                    'text' => 'Group:' . $data['chat_title'] . PHP_EOL .
                        'Prize Name: ' . $data['title'] . PHP_EOL .
                        'Number of prizes: ' . $data['number'] . PHP_EOL .
                        'Conditions for released prizes: ' . $conditions[$data['conditions']] . PHP_EOL .
                        $condition_text . PHP_EOL .
                        'Status:Released' . PHP_EOL . PHP_EOL .
                        'For specific ways to participate, please send『Lottery』within the group',
                    'parse_mode' => 'html',
                    'disable_web_page_preview' => true,
                    'reply_markup' => new InlineKeyboard($keyboard_buttons),
                ]);
            }
        }

        /* 获取中奖用列表 */
        $connect = Db::name('tgbot_lottery_user');
        // 统计参与人数
        $count = $connect->where('lottery_id', $data['id'])->count('id');
        // 生成随机分页
        if ($count < $data['number']){  // 参与人数小于奖品总数时
            $rand_page = unique_rand(1, $count, $count);
        }else{  // 参与人数大于等于奖品总数时
            $rand_page = unique_rand(1, $count, $data['number']);
        }
        // 根据随机分页逐条查出中奖用户
        $user_text = '';
        foreach ($rand_page as $page){
            $user = $connect->where('lottery_id', $data['id'])->page($page, 1)->order('id ASC')->select();
            Queue::push('app\tgbot\job\AutoSendPrize', [
                'user_id'    => $user[0]['user_id'],
                'first_name'    => $user[0]['first_name'],
                'last_name'    => $user[0]['last_name'],
                'username'    => $user[0]['username'],
                'lottery_id' => $data['id'],
                'title' => $data['title'],
                'chat_title' => $data['chat_title'],
                'time' => $data['time'],
            ], 'AutoSendPrize');
            $user_text .= "<a href=\"tg://user?id={$user[0]['user_id']}\">@{$user[0]['username']}</a>" . PHP_EOL;
        }

        if (empty($user_text)){
            $result = Request::sendMessage([
                'chat_id' => $data['chat_id'],
                'text' =>
                    '<b>' . $data['title'] . '</b> released.Unfortunately, no one won the lottery.',
                'parse_mode' => 'html',
            ]);
        }else{
            $result = Request::sendMessage([
                'chat_id' => $data['chat_id'],
                'text' =>
                    '<b>' . $data['title'] . '</b> released,The winning user:' . PHP_EOL .
                    $user_text . PHP_EOL .
                    'If you win the prize but do not get a message. Please use the <i>/winlist</i> command to receive the prize @' . $bot_username . '
                    ' . PHP_EOL .
                    "For more lottery, please join the @{$channel_username} channel",
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
            ]);
        }

        // 临时置顶开奖结果，并通知所有用户
        if ($result->isOk() && $data['notification'] == 1){
            // 获取开奖结果消息ID
            $new_pin_message_id = $result->getResult()->getMessageId();
            // 从群组信息中获取原置顶消息的ID
            $chat_info = Request::getChat(['chat_id' => $data['chat_id']]);
            // trace($chat_info , 'info');
            if ($chat_info->getResult()->getPinnedMessage()){
                $old_pin_message_id = $chat_info->getResult()->getPinnedMessage()->getMessageId();
            }
            // 置顶开奖结果
            $new_pin_result = Request::pinChatMessage([
                'chat_id' => $data['chat_id'],
                'message_id' => $new_pin_message_id,
                'disable_notification'=>false,
            ]);
            // 置顶成功
            if ($new_pin_result->isOk()){
                if (isset($old_pin_message_id)){    // 群组原来有置顶消息，60秒后重新置顶原来的置顶消息，但不再发起通知
                    $old_pin_data = [
                        'data' => [
                            'chat_id' => $data['chat_id'],
                            'message_id'    => $old_pin_message_id,
                            'disable_notification'=>true,
                        ],
                        'method' => 'pinChatMessage',
                    ];
                    Queue::later(60, 'app\tgbot\job\AutoSendMessage', $old_pin_data, 'AutoSendMessage');
                }else{  // 群组原来没有置顶消息，60秒后取消置顶
                    $unpin_data = [
                        'data' => [
                            'chat_id' => $data['chat_id'],
                        ],
                        'method' => 'unpinChatMessage',
                    ];
                    Queue::later(60, 'app\tgbot\job\AutoSendMessage', $unpin_data, 'AutoSendMessage');
                }

            }

        }

        //如果任务执行成功，删除任务
        $job->delete();
        print("任务执行成功\n");

    }

    /**
     * 该方法用于接收任务执行失败的通知，可以发送邮件给相应的负责人员
     * @param $jobData  string|array|...      //发布任务时传递的 jobData 数据
     */
    public function failed($data){
        print('警告: 队列任务执行错误，尝试次数已达上限'. PHP_EOL .'任务数据: ' . PHP_EOL . PHP_EOL .var_export($data,true).PHP_EOL);
    }
}