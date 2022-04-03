<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use app\tgbot\model\LotteryPrize;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboard;

class WinlistCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'winlist';

    /**
     * @var string
     */
    protected $description = '中奖纪录';

    /**
     * @var string
     */
    protected $usage = '/winlist';

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

        $LotteryPrize = new LotteryPrize();
        $total = $LotteryPrize->where('user_id', $user_id)->count('id');
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

        $prize_list = $LotteryPrize->where('user_id', $user_id)->page($page, 1)->order('id desc')->select();

        // 回复一个带按钮的消息
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page up',
            'callback_data'          => 'winlist-' . ($page-1),
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => ($total?$page:0) .'/'. $total,
            'callback_data'          => 'winlist-' . $page,
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page down',
            'callback_data'          =>  'winlist-' . ($page+1),
        ]);

        $text = '';
        foreach ($prize_list as $info){
            $text .=    'Group:' . $info->lottery->chat_title . PHP_EOL .
                        'Prize Name:' . $info->lottery->title . PHP_EOL .
                        'Prize:' . ($info->status==-1 ? 'You were disqualified from the prize because you leave the group' : $info->prize ) . PHP_EOL .
                        'Time(GMT+8):' . $info->time;
        }

        if ($callback_query){
            $data = [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text'    => $text ?: 'No relevant information was found',
                'reply_markup' => new InlineKeyboard($keyboard_buttons),
                'parse_mode' => 'html',
                'disable_notification'=>true,
                'disable_web_page_preview'=>true,
            ];
            return Request::editMessageText($data);
        }else{
            $data = [
                'chat_id' => $chat_id,
                'text'    => $text ?: 'No relevant information was found',
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