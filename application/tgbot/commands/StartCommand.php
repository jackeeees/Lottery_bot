<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;

/**
 * Start command
 *
 * @todo Remove due to deprecation!
 */
class StartCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'start';

    /**
     * @var string
     */
    protected $description = '开始命令';

    /**
     * @var string
     */
    protected $usage = '/start';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * Command execute method
     *
     * @return mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();            // Get Message object

        $chat_id = $message->getChat()->getId();   // Get the current Chat ID

        $text    = trim($message->getText(true));

        Request::sendChatAction(['chat_id' => $chat_id, 'action'=>'typing']);

        // 默认回复信息
        $data = [
            'chat_id' => $chat_id,
            'text'    =>
                '· If you are a lottery participant, you will need to come to me to collect the prize after winning the prize.' . PHP_EOL .
                '· If you are a group administrator, invite me into the group you manage, give me administrator and permission to delete messages, and pin messages. The _/create_ command allows you to create a lottery in your group.' . PHP_EOL . PHP_EOL .

                'You can also use the following commands to control me:' . PHP_EOL .PHP_EOL .

                '*Participants*' . PHP_EOL .
                '/list - The lottery that has been participated in' . PHP_EOL .
                '/wait - Waiting for the prize' . PHP_EOL .
                '/winlist - Collect the prize' . PHP_EOL . PHP_EOL .

                '*Sponsor*' . PHP_EOL .
                '/create - Use this command in your group to create a lottery' . PHP_EOL .
                '/released - Check the lottery I created' . PHP_EOL .
                '/edit - Add an ID after the command to modify the lottery that has been created ( Use the _/released_ command to get the ID)' . PHP_EOL .
                '/close - Add an ID after the command to close the lottery ( Use the _/released_ command to get the ID)' . PHP_EOL .
                '/leave - Leave your group' . PHP_EOL . PHP_EOL .

                '*Other commands*' . PHP_EOL .
                '/cancel - Cancel the current session ( For example: cancel the lottery you are creating )',
            'parse_mode' => 'Markdown',
            'disable_notification'=>true,
            'disable_web_page_preview'=>true,
        ];

        // 没有参数
        if (empty($text)){
            return Request::sendMessage($data);
        }

        // 解析参数
        $param = explode('-', $text);

        // 参数不对
        if (is_array($param) == false || count($param) != 2){
            return Request::sendMessage($data);
        }

        $action = $param[0];    // 操作

        // 执行操作
        $this->telegram->executeCommand($action);
    }
}
