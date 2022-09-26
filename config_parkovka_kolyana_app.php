<?php
	//config_parkovka_kolyana_app.php
	define("OFFLINE_MODE", false); //Оффлайн режим, для экстренного выключения приложения в случае сбоев или планового обслуживания
	define("DEBUG_MODE", false); //Режим отладки, для вывода текста логов в ответах протокола HTTP(S)
	define("DISABLE_SCHEDULER", false); //Отключение планировщика, при отключении не будет работать автоочистка, запись историй и запись системы видеонаблюдения. Не влияет на просмотр уже записанных и не блокирует просмотр записанных другими экземплярами приложения.
	define("DISABLE_RECORDER", false); //Отключение только записи видеонаблюдения отдельно. Будет полезно при работе нескольких экземпляров приложения.
	
	define("NOTIFY_ADMIN_USER_ACTIONS_SUCCESS", true); //Уведомлять админситратора ADMIN_CHAT_ID об успешно выполненных командах пользователя
	define("NOTIFY_ADMIN_USER_ACTIONS_ERROR", true); //Уведомлять админситратора ADMIN_CHAT_ID об ошибках при выполнении команд пользователя (не отключает уведомления о доступе неавторизованных пользователей)
	define("NOTIFY_ADMIN_TASK_ACTIONS_ERROR", true); //Уведомлять админситратора ADMIN_CHAT_ID об ошибках при выполнении задач (получение фото для историй, видео для видеозаписи, ...)
	define("MAILING_METHOD", "forwardMessage"); //Метод рассылки сообщений пользователям (forwardMessage - сообщение от администратора будет отображаться пользователю как пересланное, copyMessage - сообщение будет отображаться как просто отправленное ботом)
	
	define("LOG_PATH", "logs/"); //(! / в конце пути - обязателен) Папка для сохранения логов. Будет автоматически создана при инициализации приложения через app_init.
	define("HISTORY_PATH", "history/"); //(! / в конце пути - обязателен) Папка для сохранения файлов с индексами историй (без самих медиафайлов). Будет автоматически создана при инициализации приложения через app_init.
	
	define("DAYS_TO_DELETE_OLD_MEDIA", 7); //Количество дней, через которое будут удаляться старые элементы их папки PATH_TO_DELETE_OLD_MEDIA, при включенном и добавленном в cron планировщике
	define("PATH_TO_DELETE_OLD_MEDIA", "camera/"); //(! / в конце пути - обязателен) Папка из которой будут удаляться старые элементы через DAYS_TO_DELETE_OLD_MEDIA, при включенном и добавленном в cron планировщике
	
	define("ASYNC_TASK_RUN_INTERVAL", 20); //Интервал в секундах паузы между одновременным запуском ассинхронных задач через планировщик (запись историй и запись системы видеонаблюдения), для снижения пиковой одновременной нагрузки на сеть и ЦП
	define("ENV_CONTAINER_TAG", "ENV_CONTAINER_TAG"); //имя переменной окружения, которая содержит названия сервера (для записи в логи и пр.), для идентификации экземпляра приложения в случае работы с несколькими экземплярами. При отсутствии данной переменной используется /etc/hostname вместо.
	define("LONG_POLLING_MODE_API_REQUEST_TIMEOUT", 60); //Таймаут в секундах запроса типа long polling к Telegram API. Данное значение изменять нет необходимости, т.к. запросы циклично повторяются при завершении их по таймауту в режиме бесконечного цикла.
	
	define("BACKEND_FUNCTIONS_ALLOWED", array( //Список функций, которые разрешено вызывать ассинхронно и/или через CLI интерфейс
		"app_init",
		"app_scheduler",
		"command_start",
		"command_all_cameras",
		"command_log",
		"command_camera",
		"command_video",
		"command_album",
		"command_mailing",
		"command_get_message",
		"task_cleaner",
		"task_history",
		"task_rec",
		"editMessageReplyMarkup",
		"featury_setup_bot_menu"
		)
	);
	
	ini_set("log_errors", "On"); //Настройка логов PHP, по умолчанию ini_set("log_errors", "On");
	ini_set("error_log", LOG_PATH . "log_PHP_" . getTag() . "_" . LOG_PATH_SECRET . ".txt"); //Настройка логов PHP, по умолчанию ini_set("error_log", LOG_PATH . "log_PHP_" . getTag() . "_" . LOG_PATH_SECRET . ".txt");
?>
