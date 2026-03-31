<?php

declare(strict_types=1);

namespace AdamSolaris\MonologBitrixHandler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Utils;

/**
 * Handler sends logs to Bitrix24 using Bitrix24 Webhook API.
 *
 * Compatible with Monolog 2.x and 3.x.
 */
class BitrixHandler extends AbstractProcessingHandler
{
    /**
     * The maximum number of characters allowed in a message according to the Bitrix24 im.message.add documentation.
     * Bitrix24 usually allows around 4000-5000 chars, we use 4000 as a safe limit.
     */
    private const MAX_MESSAGE_LENGTH = 5000;

    /** @var string */
    private $address;

    /** @var string */
    private $userId;

    /** @var string */
    private $webhook;

    /** @var string */
    private $dialogId;

    /** @var bool */
    private $splitLongMessages;

    /** @var bool */
    private $delayBetweenMessages;

    /**
     * @param string     $address              Bitrix24 address (e.g. 'your-domain.bitrix24.ru')
     * @param string     $userId               Bitrix24 user ID for webhook
     * @param string     $webhook              Bitrix24 webhook secret
     * @param string     $dialogId             Bitrix24 dialog ID (chat ID)
     * @param int|string $level                The minimum logging level at which this handler will be triggered
     * @param bool       $bubble               Whether the messages that are handled can bubble up the stack or not
     * @param bool       $splitLongMessages    Whether to split long messages into multiple requests
     * @param bool       $delayBetweenMessages Adds 1-second delay between sending a split message (to avoid rate limits)
     */
    public function __construct(
        string $address,
        string $userId,
        string $webhook,
        string $dialogId,
        $level = Logger::DEBUG,
        bool $bubble = true,
        bool $splitLongMessages = false,
        bool $delayBetweenMessages = false
    ) {
        if (!\extension_loaded('curl')) {
            throw new \RuntimeException('The curl extension is needed to use the BitrixHandler');
        }

        parent::__construct($level, $bubble);

        $this->address = $address;
        $this->userId = $userId;
        $this->webhook = $webhook;
        $this->dialogId = $dialogId;
        $this->splitLongMessages = $splitLongMessages;
        $this->delayBetweenMessages = $delayBetweenMessages;
    }

    /**
     * {@inheritdoc}
     *
     * @param array|LogRecord $record
     */
    protected function write($record): void
    {
        $message = ($record instanceof LogRecord) ? $record->formatted : (string) $record['formatted'];

        $messages = $this->handleMessageLength($message);

        foreach ($messages as $key => $msg) {
            if ($this->delayBetweenMessages && $key > 0) {
                sleep(1);
            }

            $this->send($msg);
        }
    }

    /**
     * Handle a message that is too long: truncates or splits into several.
     *
     * @param string $message
     * @return string[]
     */
    private function handleMessageLength(string $message): array
    {
        $truncatedMarker = ' (...truncated)';
        if (!$this->splitLongMessages && \strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return [Utils::substr($message, 0, self::MAX_MESSAGE_LENGTH - \strlen($truncatedMarker)) . $truncatedMarker];
        }

        return str_split($message, self::MAX_MESSAGE_LENGTH);
    }

    /**
     * Sends the message to Bitrix24 via webhook.
     *
     * @param string $message
     */
    protected function send(string $message): void
    {
        $url = sprintf(
            'https://%s/rest/%s/%s/im.message.add.json',
            $this->address,
            $this->userId,
            $this->webhook
        );

        $data = [
            'DIALOG_ID' => $this->dialogId,
            'MESSAGE'   => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('BitrixHandler curl error: ' . $error);
        }

        if ($response) {
            $result = json_decode($response, true);
            if (isset($result['error'])) {
                error_log('BitrixHandler API error: ' . ($result['error_description'] ?? $result['error']));
            }
        }
    }
}
