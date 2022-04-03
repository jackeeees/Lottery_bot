<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use app\tgbot\model\Lottery;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboard;

class ReleasedCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'released';

    /**
     * @var string
     */
    protected $description = '查看你发起的抽奖活动';

    /**
     * @var string
     */
    protected $usage = '/released';

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

        $Lottery = new Lottery();
        $total = $Lottery->where(['user_id'=>$user_id])->count('id');
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

        $lottery_list = $Lottery->where(['user_id'=>$user_id])->page($page, 1)->order('id desc')->select();

        // 回复一个带按钮的消息
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page up',
            'callback_data'          => 'released-' . ($page-1),
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => ($total?$page:0) .'/'. $total,
            'callback_data'          => 'released-' . $page,
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page down',
            'callback_data'          =>  'released-' . ($page+1),
        ]);

        $conditions = [
            1 => 'Release time of prize',
            2 => 'Number of participants',
        ];

        $join_type = [
            1 => 'Send keywords in the group to participate in the lottery',
            2 => 'Private Chat bot Participates in the lottery',
        ];

        $status_code = [
            '-1'=> 'Closed',
            '0' => 'Released',
            '1' => 'No Release',
        ];
        $text = '';
        foreach ($lottery_list as $info){
            $prizes = $info->prizes;
            $prize_text = '';
            foreach ($prizes as $index => $prize){
                $condition_text = '';
                if ($info->conditions == 1){
                    $condition_text = 'Release time of prize(GMT+8):' . $info->condition_time;
                }
                if ($info->conditions == 2){
                    $condition_text = 'Number of participants:' . $info->condition_hot;
                }

                switch ( $info->join_type ){
                    case  1 :   // 群内抽奖关键词
                        $keyword_text = 'keywords:' . $info->keyword . PHP_EOL;
                        break;
                    case  2 :   // 私聊机器人抽奖无关键词
                        $keyword_text = '';
                        break;
                }

                if ($prize->status == 1){
                    $status_text = "<a href=\"tg://user?id={$prize->user_id}\">@{$prize->username}</a>";
                }elseif($prize->status == -1){
                    $status_text = "<a href=\"tg://user?id={$prize->user_id}\">@{$prize->username}</a> Participants leave the group. The prize has not been announced";
                }else{
                    $status_text = 'The prize has not been announced';
                }
                $prize_text .= ($index+1) . '. ' . $prize->prize . ' ( ' . $status_text . ' )' . PHP_EOL ;
            }

            $text .=
                'ID:' . $info->id . PHP_EOL .
                'Group:' . $info->chat_title . PHP_EOL .
                'Prize Name:' . $info->title . PHP_EOL .
                'Number of prizes:' . $info->number . PHP_EOL .
                'Conditions for released prizes:' . $conditions[$info->conditions] . PHP_EOL .
                $condition_text . PHP_EOL .
                'How to Participate in the lottery:' . $join_type[$info->join_type] . PHP_EOL .
                $keyword_text .
                'Time(GMT+8):' . date('Y-m-d H:i', $info->getData('create_time')) . PHP_EOL .

                'Number of participants:' . $info->hot . PHP_EOL .
                'List of Prizes:' . PHP_EOL . $prize_text .
                'Status:' . $status_code[$info->status];
        }

        if ($callback_query){
            $data = [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text'    => $text ?: 'You have not created any lottery',
                'reply_markup' => new InlineKeyboard($keyboard_buttons),
                'parse_mode' => 'html',
                'disable_notification'=>true,
                'disable_web_page_preview'=>true,
            ];
            return Request::editMessageText($data);
        }else{
            $data = [
                'chat_id' => $chat_id,
                'text'    => $text ?: 'You have not created any lottery',
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