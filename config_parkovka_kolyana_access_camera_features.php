<?php
	//config_parkovka_kolyana_access_camera_features.php
	define("ADMIN_CHAT_ID", "101297901"); //Идентификатор ID администратора приложения в Telegram API. При начальной настройке можно найти в логах.
	define("AUTHORIZED_ID", array("101297901", "123456789", "987654321")); //Идентификаторы ID пользователей приложения, включая администратора. При сообщении от пользователя отсутствующего в списке администратор получит сообщение с его ID и сможет добавить в список.
	define("SUPPORT_CONTACT", array("Колян", "Коляну", "@skl256")); //Имя (в именительном, дательном падежах), контакт администратора приложения (для обращений в чате)
	
	define("CALLBACK_REQUESTED_VIDEO_DURATION", 10); //Длительность в секундах видео, которое предлагается записать и получить через контекстное меню камеры (0 - отключить запись видео через контекстное меню камеры)
	define("MAX_LOG_READ_LINES", 30); //Количество последних строк лога, которые будут переданы при запросе лога через Telegram API командой /log
	define("DISPLAY_CONTEXT_KEYBOARD_TIMEOUT", 600); //Время в секундах, через которое становятся недоступны кнопки контекстных действий камеры (просмотр истории, запись видео, ...)
	define("MAX_HISTORY_SHOW_ITEMS", 100); //Максимальное количество элементов, которое будет показано при просмотре истории альбома камеры
	define("JUST_FOR_FUN_SEND_START_STICKER", array( //Если в данном массиве имеются стикеры, один из них будет отправлен после сообщения отправляемого по команде /start
		"CAACAgIAAxkDAAIE7WMNDPEUkuNvB-Rk329lcVCL2AhsAAKKIAACJnxoSJe0GA_DT6AKKQQ",
		"CAACAgIAAxkDAAIE7GMNDPHezrJ3HCkYVeWl3qWLGjynAAKJIAACJnxoSM5XpvzLXOLIKQQ",
		"CAACAgIAAxkDAAIE62MNCb5FDO6EoO2SD134W5X4ZktbAAJmIAACJnxoSHgu24uHI27PKQQ"
	));
	define("ATTEMPTS_TO_GET_PHOTO_VIDEO", 2); //Количество попыток получения фото, видео, фото для истории (кроме видеонаблюдения) с камеры, в случае неудачи
	define("ATTEMPTS_TO_GET_REC_VIDEO_TIMEOUT", 30); //Количество секунд в течение которых, но в пределах ATTEMPTS_TO_GET_PHOTO_VIDEO, будут повторяться попытки получения видеозаписи, в случае неудачи
	define("STATUS_PING_TIMEOUT", 2); //Таймаут в секундах функции проверки связи с камерами, используемой при отправеке приветственного сообщения (меньшее значение позволяет более отзывчиво реагировать на команду /start в условиях проблемного канала связии с камерами, но при этом долго отвечающие камеры могут отображаться жёлтым). Никак не влияет на функции получения изображений, только на функции получения статуса в /start.
	
	
	define("CAMERA", array( //Массив с настройкой работы камер (!) по текущей реализации логики нельзя добавить больше 10 камер (считая в т.ч. не активные)
		array(
			'ENABLED' => true, //Включена ли камера. Выключенные камеры не отображаются в меню бота, не выполняют никаких действий, но считаются в нумерации и отображаются в списке камер но только администратору.
			'ACCESS' => AUTHORIZED_ID, //Кто имеет доступ к камере (видит в списке, может получать фото и видео). В меню бота видны (но только видны) все камеры с параметром ENABLED в не зависимости от данной настройки.
			'HISTORY_ENABLED' => true, //Включена ли запись историй (влияет как на запись так и на возможность просмотра). Для записи историй необходимо настроить планировщик в cron и не включать параметр DISABLE_SCHEDULER.
			'HISTORY_ACCESS' => AUTHORIZED_ID, //Кто имеет доступ к просмотру историй камеры
			'REC_ENABLED' => true, //Включена ли запись видеонаблюдения. Для записи необходимо настроить планировщик в cron и не включать параметр DISABLE_SCHEDULER и DISABLE_RECORDER.
			'REC_ACCESS' => null, //Резерв feature toogle для возможно планирующегося функционала, значение null
			'REC_CONFIG_REWRITE' => array( //Перезапись параметров конфигурации камеры, которые будут использоваться отдельно только для видеонаблюдения. Можно оставить пустым массивом, тогда будут использоваться те же параметры, что и для записи коротких клипов, при отсутствии параметра SHEDULED_REC_DURATION длительность записи видеонаблюдения будет 3600 секунд, что соотвтетсвует запуску планировщика через cron 1 раз в 1 час. 
				'SHEDULED_REC_DURATION' => 60, //Длительность в секундах одного клипа записи видеонаблюдения (итогового, если используется ускорение, а не исходного), при нормальной скорости видео 1X и запуске планировщика через cron 1 раз в 1 час должна быть равна 3600 секунд.
				'ADD_STRING_TO_END_V' => "-ss 5 -filter_complex [0:v]setpts=0.0167*PTS[v] -map [v] -vcodec hevc -s 1280*720", //Пример ускорения видео в 60 раз и начала записи видео с 5 секунды
				'TIMEOUT' => 4600), //Таймаут в секундах для видеозаписи нужно подбирать с учетом времени на кодирование и сохранение видео, реальный таймаут операции составит SHEDULED_REC_DURATION + TIMEOUT (т.е. тут указываем длительность только свыше длительности готового видео)
			'CONFIG' => array( //Массив с индивидуальной конфигурацией устройства камеры, информацию о значениях можно получить в описании lib_nikolay_webcam_api.php. (!)Внимание, для данного приложения параметр 'NAME' у каждой камеры обязателен, использовать значение по умолчанию не допускается.
				'NAME' => "parkovka",
				'FFMPEG_BIN' => "ffmpeg",
				'PROTO' => "rtsp",
				'IP' => "192.168.1.111",
				'PORT' => "554",
				'PATH' => "/onvif1",
				'ADD_STRING_TO_END_P' => "-f mjpeg",
				'ADD_STRING_TO_END_V' => "-ss 5",
				'TIMEOUT' => 15
			)//,
			//'NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF' => true, //Не обязательный параметр. Отключает уведомления админситратора ADMIN_CHAT_ID об ошибках при выполнении задач данной камеры, если они включены глобальным параметром NOTIFY_ADMIN_TASK_ACTIONS_ERROR.
			//'CALLBACK_REQUESTED_VIDEO_DURATION' => 0, //Не обязательный параметр. См. описание параметра CALLBACK_REQUESTED_VIDEO_DURATION. Задаёт указанный параметр для отдельной камеры, если не задан - используется CALLBACK_REQUESTED_VIDEO_DURATION.
			//HIDE_FROM_BOT_MENU => true //Не обязательный параметр. Не добавляет камеру в меню бота, кроме этого не влияет на поведение логики, камера остаётс доступна при вызове через написание команды /camera$i или имени камеры вручную, в соответствии с настройками ACCESS
		),
		array(
			'ENABLED' => true, //Включена ли камера. Выключенные камеры не отображаются в меню бота, не выполняют никаких действий, но считаются в нумерации и отображаются в списке камер но только администратору.
			'ACCESS' => AUTHORIZED_ID, //Кто имеет доступ к камере (видит в списке, может получать фото и видео). В меню бота видны (но только видны) все камеры с параметром ENABLED в не зависимости от данной настройки.
			'HISTORY_ENABLED' => true, //Включена ли запись историй (влияет как на запись так и на возможность просмотра). Для записи историй необходимо настроить планировщик в cron и не включать параметр DISABLE_SCHEDULER.
			'HISTORY_ACCESS' => AUTHORIZED_ID, //Кто имеет доступ к просмотру историй камеры
			'REC_ENABLED' => false, //Включена ли запись видеонаблюдения. Для записи необходимо настроить планировщик в cron и не включать параметр DISABLE_SCHEDULER и DISABLE_RECORDER.
			'REC_ACCESS' => null, //Резерв feature toogle для возможно планирующегося функционала, значение null
			'REC_CONFIG_REWRITE' => array(), 
			'CONFIG' => array( //Массив с индивидуальной конфигурацией устройства камеры, информацию о значениях можно получить в описании lib_nikolay_webcam_api.php. (!)Внимание, для данного приложения параметр 'NAME' у каждой камеры обязателен, использовать значение по умолчанию не допускается.
				'NAME' => "dacha",
				'FFMPEG_BIN' => "ffmpeg",
				'PROTO' => "rtsp",
				'IP' => "192.168.2.111",
				'PORT' => "554",
				'PATH' => "/onvif1",
				'ADD_STRING_TO_END_P' => "-f mjpeg",
				'ADD_STRING_TO_END_V' => "-ss 5",
				'TIMEOUT' => 15
			)
		)
	));
?>
