<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use think\Db;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboard;

class WaitCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'wait';

    /**
     * @var string
     */
    protected $description = '待开奖的活动';

    /**
     * @var string
     */
    protected $usage = '/wait';

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

        $LotteryUser = Db::name('tgbot_lottery_user');
        $total = $LotteryUser->alias('tlu')
                            ->join('__TGBOT_LOTTERY__ tl','tlu.lottery_id = tl.id')
                            ->where('tlu.user_id', $user_id)
                            ->where('tl.status', 1)
                            ->count('tlu.user_id');

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

        $LotteryUser = Db::name('tgbot_lottery_user');
        $user_list = $LotteryUser->field('tlu.id, tlu.user_id, tlu.lottery_id, tlu.first_name, tlu.last_name, tlu.username, tlu.create_time, tl.chat_title, tl.title, tl.number, tl.conditions, tl.condition_time, tl.condition_hot, tl.hot')
                                ->alias('tlu')
                                ->join('__TGBOT_LOTTERY__ tl','tlu.lottery_id = tl.id')
                                ->where('tlu.user_id', $user_id)
                                ->where('tl.status', 1)
                                ->page($page, 1)
                                ->order('tlu.create_time desc')
                                //->fetchSql(true)
                                ->select();
        //trace($user_list, 'error');

        // 回复一个带按钮的消息
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page up',
            'callback_data'          => 'wait-' . ($page-1),
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => ($total?$page:0) .'/'. $total,
            'callback_data'          => 'wait-' . $page,
        ]);
        $keyboard_buttons[] = new InlineKeyboardButton([
            'text'          => 'page down',
            'callback_data'          =>  'wait-' . ($page+1),
        ]);

        $conditions = [
            1 => 'Release time of prize',
            2 => 'Number of participants',
        ];

        $text = '';
        foreach ($user_list as $info){
            $condition_text = '';
            if ($info['conditions'] == 1){
                $condition_text = 'Release time of prize(GMT+8):' .  date('Y-m-d H:i', $info['condition_time']);
            }
            if ($info['conditions'] == 2){
                $condition_text = 'Number of participants:' . $info['condition_hot'];
            }

            $text .=    'Group:' . $info['chat_title'] . PHP_EOL .
                'Prize Name:' . $info['title'] . PHP_EOL .
                'Prize Name:' . $info['number'] . PHP_EOL .
                'Conditions for released prizes:' . $conditions[$info['conditions']] . PHP_EOL .
                $condition_text . PHP_EOL .
                'Participation time(GMT+8):' . date('Y-m-d H:i', $info['create_time']) . PHP_EOL .
                'Number of participants:' . $info['hot'] . PHP_EOL .
                'status:Not released';
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