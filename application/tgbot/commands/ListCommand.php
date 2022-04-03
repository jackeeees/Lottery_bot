<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use app\tgbot\model\Lottery;
use app\tgbot\model\LotteryUser;
use app\tgbot\model\LotteryPrize;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboard;

class ListCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'list';

    /**
     * @var string
     */
    protected $description = '参与的抽奖活动';

    /**
     * @var string
     */
    protected $usage = '/list';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * 是否仅允许私聊机器人时使用
     *
     * @var bool
     */
    protected $private_only = true;

    /**
     * 命令是否启用
     *
     * @var boolean
     */
    protected $enabled = true;

    /**
     * 是否在 /help 命令中显示
     *
     * @var bool
     */
    protected $show_in_help = false;

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        // 翻页操作
        $callback_query = $this->getUpdate()->getCallbackQuery();
        if ($callback_query){
            $message = $callback_query->getMessage();
            $message_id = $message->getMessageId();
            $user_id = $callback_query->getFrom()->getId();
            $chat_id = $message->getChat()->getId();
            $query_data = $callback_query->getData();
        }else{
            $message = $this->getMessage();
            $message_id = $message->getMessageId();
            $user_id = $message->getFrom()->getId();
            $chat_id = $message->getChat()->getId();
        }

        $LotteryUser = new LotteryUser();
        $total = $LotteryUser->where('user_id', $user_id)->count('user_id');

        if (isset($query_data)){
            $param = explode('-', $query_data);
            if ($param[1]<1){
                $page = 1;
            }elseif ($param[1]>$total){
                $page = $total;
            }else{
                $page = $param[1];
            }
        }else{
            $page = 1;
        }

        $user_list = $LotteryUser->where('user_id', $user_id)->page($page, 1)->order('create_time desc')->select();

        // 回复一个带按钮的消息
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page up',
            'callback_data'          => 'list-' . ($page-1),
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => ($total?$page:0) .'/'. $total,
            'callback_data'          => 'list-' . $page,
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page down',
            'callback_data'          =>  'list-' . ($page+1),
        ]);

        $conditions = [
            1 => 'Release time of prize',
            2 => 'Number of participants',
        ];

        $status_code = [
            '-1'=> 'Closed',
            '0' => 'Released',
            '1' => 'No Release',
        ];
        $text = '';
        foreach ($user_list as $info){
            $condition_text = '';
            if ($info->lottery->conditions == 1){
                $condition_text = 'Release time of prize(GMT+8):' . $info->lottery->condition_time;
            }
            if ($info->lottery->conditions == 2){
                $condition_text = 'Number of participants:' . $info->lottery->condition_hot;
            }

            if ($info->lottery->status == 0){   // 已开奖
                // 获取中奖者名单
                $lottery_info = Lottery::get(['id'=>$info->lottery->id]);
                $lottery_user = $lottery_info->prizes()->where('status',1)->select();
                $lottery_user_text = '';
                if ( count($lottery_user)>0 ){
                    $lottery_user_text .= PHP_EOL . 'winners:' . PHP_EOL;
                    foreach ($lottery_user as $user_index => $user_info){
                        $lottery_user_text .= ($user_index+1) . '. ' . $user_info->first_name .' '. $user_info->last_name . PHP_EOL;
                    }
                }

                // 中奖状态
                $prize = LotteryPrize::get(['lottery_id'=>$info->lottery->id, 'user_id'=>$info->user_id]);
                if ($prize){
                    $status =  $prize->status==-1 ? 'You were disqualified from the prize because you leave the group' : 'Win the prize' . $lottery_user_text;
                }else{
                    $status = 'You did not win the prize' . $lottery_user_text;
                }
            }else{  // 未开奖
                $status = $status_code[$info->lottery->status];
            }

            $text .=    'Group:' . $info->lottery->chat_title . PHP_EOL .
                'Prize Name:' . $info->lottery->title . PHP_EOL .
                'Number of prizes:' . $info->lottery->number . PHP_EOL .
                'Conditions for released prizes:' . $conditions[$info->lottery->conditions] . PHP_EOL .
                $condition_text . PHP_EOL .
                'Participation time(GMT+8):' . date('Y-m-d H:i', $info->create_time) . PHP_EOL .
                'Number of participants:' . $info->lottery->hot . PHP_EOL .
                'status:' . $status;

        }

        if ($callback_query){
            $data = [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text'    => $text ?: 'You have not participated in any lottery',
                'reply_markup' => new InlineKeyboard($keyboard_buttons),
                'parse_mode' => 'html',
                'disable_notification'=>true,
                'disable_web_page_preview'=>true,
            ];
            return Request::editMessageText($data);
        }else{
            $data = [
                'chat_id' => $chat_id,
                'text'    => $text ?: 'You have not participated in any lottery',
                'reply_to_message_id' => $message_id,
                'reply_markup' => new InlineKeyboard($keyboard_buttons),
                'parse_mode' => 'html',
                'disable_notification'=>true,
                'disable_web_page_preview'=>true,
            ];
            return Request::sendMessage($data);
        }

    }
}