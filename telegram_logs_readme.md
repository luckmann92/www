# Логирование сообщений Telegram-бота

## Где смотреть логи

Сообщения, полученные от Telegram-бота, логируются в файл:
```
storage/logs/laravel.log
```

## Формат логов

Каждое полученное сообщение записывается в лог с меткой времени и информацией:
- `user_id` - ID пользователя в Telegram
- `chat_id` - ID чата
- `user_name` - Имя пользователя
- `message_text` - Текст сообщения
- `timestamp` - Время получения сообщения

Пример записи в логе:
```
[2026-01-06 09:26:22] local.INFO: Telegram Bot Message Received {"user_id": 123456789, "chat_id": -123456789, "user_name": "John Doe", "message_text": "/start 123-456", "timestamp": "2026-01-06T09:26:22.000000Z"}
```

## Просмотр логов

Для просмотра последних записей в логе можно использовать команду:
```
tail -f storage/logs/laravel.log | grep "Telegram Bot Message Received"
```

Или открыть файл `storage/logs/laravel.log` в любом текстовом редакторе.
