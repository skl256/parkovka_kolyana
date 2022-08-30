<?php
	//config_parkovka_kolyana_secret.php
	define("BOT_TOKEN", "0123456789:Abcdefghij1234567890zxcvbnmasd12345"); //Секретный токен для работы с Telegram API, выдаёт бот https://t.me/BotFather
	define("HTTP_HEADER_TOKEN", ""); //Секретный токен, который Telegram API может передавать в заголовках HTTPS-запросов, для дополнительной безопасности. Если оставить пустым приложение не будет проверять его, и любой, кто имеет доступ к Webhook сможет посылать запросы приложению от имени Telegram API.
	define("LOG_PATH_SECRET", "9876543210"); //Секретный префикс, который будет добавлен к файлам логов и историй, для предотвращения доступа к нам через HTTP(S)
	define("DO_NOT_USE_IN_PRODUCTION_CACHE_FILE_NAME", "lib_nikolay_telegram_api.php.FILE_CACHE_9876543210"); //Секретный префикс, который будет добавлен к файлам данных библиотеки lib_nikolay_telegram_api.php, для предотвращения доступа к нам через HTTP(S)
	//Чтобы установить Webhook необходимо послать запрос к Telegram API:
	//https://api.telegram.org/botBOT_TOKEN/setWebhook?url=FULL_URL_TO_APP_PARKOVKA_KOLYANA.PHP&secret_token=HTTP_HEADER_TOKEN
?>