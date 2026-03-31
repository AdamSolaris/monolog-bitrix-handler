# Monolog Bitrix Handler

A Monolog handler for sending logs to Bitrix24 via webhooks.

## Installation

```bash
composer require adamsolaris/monolog-bitrix-handler
```

## Usage

### Symfony Configuration

In your `services.yaml`:

```yaml
services:
    AdamSolaris\MonologBitrixHandler\BitrixHandler:
        arguments:
            $address: '%env(BITRIX_ADDRESS)%'
            $userId: '%env(BITRIX_USER_ID)%'
            $webhook: '%env(BITRIX_WEBHOOK)%'
            $dialogId: '%env(BITRIX_DIALOG_ID)%'
            $level: 'error' # Optional, default is 'debug'
            $splitLongMessages: true # Optional: split message if it's too long, default  is 'false'
            $delayBetweenMessages: true # Optional: add 1-second delay between split messages, default  is 'false'
```

In your `monolog.yaml`:

```yaml
monolog:
    handlers:
        bitrix:
            type: service
            id: AdamSolaris\MonologBitrixHandler\BitrixHandler
            level: error
            channels: ["!event", "!doctrine"]
```

### Environment Variables (.env)

```env
BITRIX_ADDRESS=your-domain.bitrix24.ru
BITRIX_USER_ID=1
BITRIX_WEBHOOK=your-webhook-secret
BITRIX_DIALOG_ID=chat123
```

## Features

- Supports PHP 7.4+
- Compatible with Monolog 2.x and 3.x
- **Message Truncation/Splitting**: Handles long messages (max 5000 characters) to comply with Bitrix24 API limits.
- **Rate Limit Protection**: Optional 1-second delay between sending split messages.
- **Custom Formatting**: Uses standard Monolog formatters.
- Uses native `curl` to avoid extra dependencies.

## License

MIT
