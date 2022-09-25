<?php

	//Parkovka Kolyana v 2022-09-25-22-15 https://t.me/skl256 https://github.com/skl256/parkovka_kolyana.git
	
	//Использование:
	//0. Установить необходимые компоненты, если ещё не установлены: apt install -y nginx php php-fpm php-curl php-mbstring ffmpeg iputils-ping git cron
	//	* nginx, php-fpm необходимы для работы в режиме webhook, иначе не обязательно; вместо nginx можно использовать apache2 ** iputils-ping, cron обычно уже установлены в системе *** git требуется только для клонирования репозитариев, не обязательно
	//1. Загрузить необходимые библиотеки lib_nikolay_telegram_api.php, lib_nikolay_webcam_api.php и поместить в папку с app_parkovka_kolyana.php 
	//2. Внести изменения в конфигурационные файлы, список конфигурационных файлов с описанием находится ниже
	//3. В случае, если бот будет работать в режиме webhook [РЕКОММЕНДУЕТСЯ] (т.е. запросы от Telegram API будут поступать к app_parkovka_kolyana.php через ваш веб-сервер) * подробнее на https://core.telegram.org/bots/api
	//	а) Инициализировать приложение с помощью команды cd /путь/до/папки/с/приложением && sudo -u www-data php -f app_parkovka_kolyana.php app_init (необходимо сделать один раз для инициализации, создания папок; далее можно использовать команду при обновлении конфигурации, например, для актуализации списка камер в меню бота)
	//	б) Установить webhook Telegram API в соответствии с документацией Telegram API
	//	в) Для добавления функционала автоматической очистки папок, историй, записи видеонаблюдения, необходимо добавить в cron пользователя web-сервера строку 0 * * * * cd /путь/до/папки/с/приложением && php -f app_parkovka_kolyana.php app_scheduler
	//	*	открыть редактор cron можно командой crontab -u www-data -e ** параметр "0 * * * *" соответствует запуску планировщика каждый час, этот параметр можно изменить
	//3.1. В случае, если бот будет работать в режиме long polling (т.е. будет непрерывно проверять наличие запросов самостоятельно отправляя запросы к Telegram API)
	//	* [+] Для данного режима не нужен web-сервер/выделенный IP
	//	* [-] При использовании данного режима необходимо контролировать возможные остановки приложения (по причине ошибок, сетевых сбоев, перезагрузки сервера) и огранизовывать его перезапуск в случае остановки (предлагаемый вариант - запуск процесса в контейнере с настройкой автоматического рестарта контейнера)
	//	* [-] Данный режим позволяет запустить только один экземаляр приложения, запросы будут выполнятся последовательно без возможности балансировки нагрузки
	//	а) Инициализировать приложение с помощью команды cd /путь/до/папки/с/приложением && php -f app_parkovka_kolyana.php app_init (необходимо сделать один раз для инициализации, создания папок; далее можно использовать команду при обновлении конфигурации, например, для актуализации списка камер в меню бота)
	//	б) Для добавления функционала автоматической очистки папок, историй, записи видеонаблюдения, необходимо добавить в cron строку 0 * * * * cd /путь/до/папки/с/приложением && php -f app_parkovka_kolyana.php app_scheduler (* в cron пользователя, от имени которого инициализировано и будет работать в дальнейшем приложение)
	//	в) Запустить приложение php -f app_parkovka_kolyana.php * далее контролировать автозапуск, перезапуск приложения любыми удобными способами
	
	require_once("lib_nikolay_telegram_api.php"); //Библиотека для работы с Telegram API https://github.com/skl256/nikolay_telegram_api.git
	require_once("lib_nikolay_webcam_api.php"); //Библиотека для работы с IP-камерами https://github.com/skl256/nikolay_webcam_api.git
	
	require_once("config_parkovka_kolyana_secret.php"); //Конфигурация содержащая токены Telegram API и ключи доступа к хранимым данным (БД или файлам)
	//В данной конфигурации понадобится указать данные, полученные от @BotFather, также там можно найти ссылку с описанием, как установить Webhook. Этот файл необходимо хранить в тайне, он содержит данные для доступа к боту и управления им.
	require_once("config_parkovka_kolyana_app.php"); //Конфигурация приложения, определяет основные параметры (запись логов, режим работы, планироващик, глобальные настройки записи, пути)
	//В большенстве случаев, в данной конфигурации не понадобится делать изменения, если не пларируется использование нескольких экземпляров приложения (например при балансировке нагрузки, резервировании или контейнеризации)
	require_once("config_parkovka_kolyana_access_camera_features.php"); //Конфигурация настройки доступа пользователей, администраторов, параметров камер, а также других настраваемых параметров приложения.
	//В данной конфигурации понадобится указать ID администратора и пользователей, а также данные о камерах, остальные, токие настройки фич обычно подходят всем, можно оставить как есть
	//Важно(!) если пларируется использование нескольких экземпляров приложения, основные настройки используемые в данной конфигурации, должны совпадать с другими экземплярами (например, кол-во и порядок камер, числовые параметры фич) иначе возможно нарушение логики некоторых функций
	require_once("config_parkovka_kolyana_dictonary.php"); //Конфигурация используемых ботом фраз, названий, надписей
	//Выражения и смайлики можно изменить на любые удобные, важно помнить, что кол-во переменных в фразах, где они (переменные) используются менять нельзя, чтобы бот не потерял дар речи (т.е. можно, но в таком случае необходимо вносить измененич не только в словарь но и в основной код)
	
	function getTag() { //Функция получает либо значение заданной в конфигурации переменной окружения либо имя хоста
		return exec("printenv " . ENV_CONTAINER_TAG) ? exec("printenv " . ENV_CONTAINER_TAG) : exec("cat /etc/hostname");
	}
	
	function async_exec($function, $sleep, ...$vars) { //Выполняет ассинхронно функцию $function с задержкой $sleep секунд и с параметрами ...$vars
		foreach ($vars as &$var) {
			$var = escapeshellarg($var);
		}
		exec("((sleep $sleep) && (php -f " . __FILE__ . " -- $function " . implode(" ", $vars) . ")) >/dev/null 2>/dev/null &");
	}
	
	function writeLog($level, $string) { //Записывает лог в текстовый файл
		$line = date("Y-m-d H:i:s", time()) . " " . $level . ": " . htmlspecialchars(str_replace("\n", " ", $string), ENT_NOQUOTES) . "\n";
		if (DEBUG_MODE) { echo "$line<br />\n"; } //Выводит логи в стандартный вывод в случае, если приложение запущено в режиме отладки, не работает для кода, запускаемого ассинхронно
		file_put_contents(LOG_PATH . "log_MAIN_" . getTag() . "_" . LOG_PATH_SECRET . ".txt", $line, FILE_APPEND);
		file_put_contents(LOG_PATH . "log_" . $level . "_" . getTag() . "_" . LOG_PATH_SECRET . ".txt", $line, FILE_APPEND);
	}
	
	function pingInterface($ip, $timeout = 5, $is_error_if_not_pinging = true) { //Возвращает true если ping прошёл успешно или false в случае ошибки
		if (mb_strpos(exec("timeout $timeout ping -c 1 $ip | grep \"received\""), "1 received")) {
			return true;
		} else {
			if ($is_error_if_not_pinging) {
				writeLog("ERROR", "UNREACHABLE INTERFACE $ip");
			}
			return false;
		}
	}
	
	function bot_dictonary($speech, ...$var) { //Подбирает случайный ответ бота из словаря с ключём $speech, подставляя значения ...$vars
		if (!isset(BOT_DICTONARY[$speech])) { writeLog("ERROR", "CRITICAL - DICTONARY KEY $speech NOT FOUND"); }
		return isset(BOT_DICTONARY[$speech]) ? sprintf(BOT_DICTONARY[$speech][rand(0, count(BOT_DICTONARY[$speech]) - 1)], ...$var) : BOT_DICTONARY["default"][0];
	}
	
	function bot_update($update) {
		if((!empty($update['message']['text'])) || (!empty($update['callback_query']['data']))) { //Если Update-запрос Telegram API содержит message или callback_query
			$update_type = !empty($update['message']['text']) ? "message" : "callback_query"; //Получает параметры из Update-запроса //тип запроса - сообщение или callback нажатия кнопки
			$from_id = $update[$update_type]['from']['id'];//ID отправителя сообщения или callback'а
			$first_name = $update[$update_type]['from']['first_name'];//имя отправителя сообщения или callback'а
			$chat_id = ($update_type == "message") ? $update[$update_type]['chat']['id'] : $update[$update_type]['message']['chat']['id'];//ID чата, в котором отправлено сообщение или callback (= $from_id если сообщение отправлено в ЛС боту)
			$line = !empty($update['message']['text']) ? trim(mb_strtolower($update[$update_type]['text'])) : trim($update[$update_type]['data']);//строка сообщения (приведена к строчным буквам и убраны лишние пробелы) или callback'а (только убраны лишние пробелы)
			writeLog("INBOX", "RECEIVED $update_type FROM $from_id $first_name IN CHAT $chat_id TEXT $line");
			if ((!OFFLINE_MODE) && (in_array($from_id, AUTHORIZED_ID))) { //Если приложение не отключено OFFLINE_MODE и пользователь имеет доступ к приложению  AUTHORIZED_ID
				if (!bot_command($update_type, $from_id, $first_name, $chat_id, $line, $update)) {//Пытается распознать команду используя функцию распознавания и сразу же выполняет её в рамках функции
					sendMessage($chat_id, bot_dictonary("message_to_user_unknownword_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));//Если команда неизвестна сообщает пользователю об ошибке, без сообщения администратору
					writeLog("UNKNOWNWORD", "LINE $line NOT RECONIZED AS COMMAND, DETAILS: UPDATE_TYPE $update_type FROM $from_id $first_name IN CHAT $chat_id");
				}
			} else {//Если приложение отключено OFFLINE_MODE или пользователь не имеет доступ к приложению  AUTHORIZED_ID
				if (OFFLINE_MODE) { //Если OFFLINE_MODE //Cообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
					sendMessage($chat_id, bot_dictonary("message_to_user_offline_mode_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
					if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_offline_mode_text", SUPPORT_CONTACT[0], $first_name, "#" . getTag())); }
					writeLog("ACCESS", "OFFLINE_MODE: $from_id $first_name TRY TO ACCESS TO BOT WHEN APP IN OFFLINE_MODE");
				} else if (!in_array($from_id, AUTHORIZED_ID)) { //Аналогичный сценарий, если пользователь не AUTHORIZED_ID
					sendMessage($chat_id, bot_dictonary("message_to_user_not_auth_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
					if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_not_auth_text", SUPPORT_CONTACT[0], $from_id, $first_name, "#" . getTag())); }
					writeLog("ACCESS", "UNAUTHORIZED: $from_id $first_name TRY TO ACCESS TO BOT, USER IS NOT IN AUTHORIZED_ID");
				}
			}
		} else {//Если запрос некорректный или содержит неподдерживаемый (не message или callback_query) тип Update-запроса
			writeLog("ERROR", "UNSUPPORTED UPDATE TYPE");
		}
	}
	
	function bot_command($update_type, $from_id, $first_name, $chat_id, $line, $raw_update) { //Обрабатывая входящий текст распознаёт команду боту или возвращает false
		if ($line == "/start") { //Команда /start
			async_exec("command_start", 0, $from_id, $chat_id);
			return true;
		} else if ($line == "/all_cameras") { //Команда /all_cameras
			async_exec("command_all_cameras", 0, $from_id, $first_name, $chat_id);
			return true;
		} else if ($line == "/log") { //Команда /log
			async_exec("command_log", 0, $from_id, $first_name, $chat_id);
			return true;
		} else if ((mb_substr_count($line, "/camera")) && (is_numeric(mb_strcut($line, 7))) && (mb_strcut($line, 7) < count(CAMERA))) { //Команда /camera
			$camera_id = mb_strcut($line, 7);
			async_exec("command_camera", 0, $from_id, $first_name, $chat_id, $camera_id);
			return true;
		} else if (in_array(trim($line, "/"), array_column(array_column(CAMERA, 'CONFIG'), 'NAME'))) { //Команда с именем NAME камеры
			$camera_id = array_search(trim($line, "/"), array_column(array_column(CAMERA, 'CONFIG'), 'NAME'));
			async_exec("command_camera", 0, $from_id, $first_name, $chat_id, $camera_id);
			return true;
		} else if (($update_type == "callback_query") && (mb_substr_count($line, "video")) && (is_numeric(mb_substr($line, 5, 1))) && (mb_substr($line, 5, 1) < count(CAMERA))) { //Нажатие контекстной конпки записи клипа
			$vars = explode(" ", $line);
			$camera_id = mb_substr($line, 5, 1);
			$duration = (isset($vars[1]) && is_numeric($vars[1])) ? $vars[1] : 0;
			async_exec("command_video", 0, $from_id, $first_name, $chat_id, $camera_id, $duration);
			return true;
		} else if (($update_type == "callback_query") && (mb_substr_count($line, "album")) && (is_numeric(mb_substr($line, 5, 1))) && (mb_substr($line, 5, 1) < count(CAMERA))) { //Нажатие контекстной конпки получения истории, листание историй
			$vars = explode(" ", $line);
			$camera_id = mb_substr($line, 5, 1);
			$offset = (isset($vars[1]) && is_numeric($vars[1])) ? $vars[1] : 0;
			async_exec("command_album", 0, $from_id, $first_name, $chat_id, $camera_id, $offset);
			return true;
		} else if (($update_type == "callback_query") && (mb_substr_count($line, "mailing")) && (is_numeric(mb_substr($line, 7)))) { //Нажатие контекстной конпки подтверждения рассылки
			$message_id = mb_substr($line, 7);
			async_exec("command_mailing", 0, $from_id, $chat_id, $message_id, true);
			return true;
		} else if (($update_type == "callback_query") && (mb_substr_count($line, "get_message")) && (is_numeric(mb_substr(explode(" ", $line)[0], 11)))) { //Нажатие контекстной конпки запроса копии сообщения
			$vars = explode(" ", $line);
			$message_id = mb_substr($vars[0], 11);
			$from_chat_id = (isset($vars[1]) && is_numeric($vars[1])) ? $vars[1] : 0;
			$media_group_messages_count = (isset($vars[2]) && is_numeric($vars[2])) ? $vars[2] : 0;
			async_exec("command_get_message", 0, $from_id, $chat_id, $message_id, $from_chat_id, $media_group_messages_count);
			return true;
		} else if ($from_id == ADMIN_CHAT_ID) { //Команда mailing (запускается только если не подошли другие варианты, тогда считаем что администратор хочет запустить рассылку)
			async_exec("command_mailing", 0, $from_id, $chat_id, $raw_update['message']['message_id'], false);
			return true;
		}
		return false;
	}
	
	function app_init() { //Инициализация приложения в случаях первого запуска, запуска контейнера, либо для обновления меню
		foreach (array(LOG_PATH, HISTORY_PATH) as $path) {
			if (($path != "") && (!is_dir($path))) { mkdir($path, 0777, true); } // Создание папок ( (!)при появлении новых путей необходимо обновлять список в строке выше)
		}
		writeLog("PROCESS", "app_init() AT #" . getTag());
		$cameras_status_string = "";
		for ($i = 0; $i < count(CAMERA); $i++) { //Формирование списка всех камер для сообщения-сводки
			$cameras_status_string = $cameras_status_string . (pingInterface(CAMERA[$i]['CONFIG']['IP']) ? "\xF0\x9F\x9F\xA2" : "\xF0\x9F\x9F\xA0") . " /camera$i " . CAMERA[$i]['CONFIG']['NAME'] . "\n" . (CAMERA[$i]['ENABLED'] ? "ENABLED \xE2\x9E\x95, " : "ENABLED \xE2\x9E\x96, ") . (CAMERA[$i]['HISTORY_ENABLED'] ? "HISTORY \xE2\x9E\x95, " : "HISTORY \xE2\x9E\x96, ") . (CAMERA[$i]['REC_ENABLED'] ? "REC \xE2\x9E\x95" : "REC \xE2\x9E\x96") . "\n";
		}
		$options_status_string = "\xE2\x9C\x85 OFFLINE_MODE " . ((OFFLINE_MODE) ? "\xE2\x9E\x95" : "\xE2\x9E\x96") . "\n\xE2\x9C\x85 DEBUG_MODE " . ((DEBUG_MODE) ? "\xE2\x9E\x95" : "\xE2\x9E\x96") . "\n\xE2\x9C\x85 DISABLE_SCHEDULER " . ((DISABLE_SCHEDULER) ? "\xE2\x9E\x95" : "\xE2\x9E\x96") . "\n\xE2\x9C\x85 DISABLE_RECORDER " . ((DISABLE_RECORDER) ? "\xE2\x9E\x95" : "\xE2\x9E\x96");
		featury_setup_bot_menu();//Создаёт и устанавливает меню бота.  //В строке выше формирование списка основных опицй для сообщения-сводки
		sendMessage(ADMIN_CHAT_ID, bot_dictonary("app_init_text", getTag(), $cameras_status_string, $options_status_string)); //Отправка сообщения-сводки с именем экзампляра или хоста, списком всех камер и основных опций.
	}
	
	function app_webhook_mode() { //Данная функция вызывается всякий раз при запуске приложения через WEB-сервер и пытается получить команду через Webhook
		$update = getUpdate(HTTP_HEADER_TOKEN); //Рассматривает данные, полученные по HTTP(S)-запросу как Update-запрос Telegram API. Если параметр HTTP_HEADER_TOKEN задан, то он будет проверен (функция вернёт false при несовпадении и не позволит обработать запрос)
		if ($update) {
			bot_update($update); //При успешном получении запроса передаёт обработку полученных данных
		} //Далее следует выход из приложения, после выполнения обработки запроса или без его обработки, если запрос пустой
	}
	
	function app_long_polling_mode() { //Данная функция вызывается всякий раз при запуске приложения через CLI без параметров и пытается получить команду через getUpdates
		writeLog("PROCESS", "START IN LONG POLLING MODE");
		sendMessage(ADMIN_CHAT_ID, bot_dictonary("app_start_long_polling_text", getTag())); //Отправляет администратору сообщение о запуске режима long polling
		echo bot_dictonary("app_start_long_polling_text", getTag());
		register_shutdown_function(function() { //Данный функционал срабатывает только в случае завершения работы приложения изнутри (например, при внутренней ошибке или прекращения работы long polling
			writeLog("PROCESS", "STOP LONG POLLING MODE"); //со стороны сервера в случаях, когда установлен webhook и аналогичные варианты)
			sendMessage(ADMIN_CHAT_ID, bot_dictonary("app_stop_long_polling_text", getTag())); //Отправляет администратору сообщение об остановке запуске режима long polling
			echo bot_dictonary("app_stop_long_polling_text", getTag());
		});
		$offset = 0;
		$result_ok = false;
		do {
			$update = getUpdateLongPolling(LONG_POLLING_MODE_API_REQUEST_TIMEOUT, $offset, $result_ok); //Пытается получить Update-запрос Telegram API метод getUpdates
			if ($update) {
				bot_update($update); //При успешном получении запроса передаёт обработку полученных данных
				$offset++; //и установаливает счётчик для подтверждения обработки запроса Telegram API при следующем запросе getUpdates
			}
		} while ($result_ok); //Функция бесконечно ожидает получение команды от Telegram API пока не будет завершена вручную или не будет получена ошибка от Telegram API
	}
	
	function app_scheduler() { //Планировщик, необходимо запускать через cron
		if ((!DISABLE_SCHEDULER) && (!OFFLINE_MODE)) { //Не будет запущен при активных параметрах OFFLINE_MODE или DISABLE_SCHEDULER или при отсутсвии настройки cron
			async_exec("task_cleaner", 0); //Запуск задачи очистки старых медиа файлов
			writeLog("PROCESS", "TASK task_cleaner() WILL BE LAUNCHED AFTER 0 SECONDS");
			for ($i = 0; $i < count(CAMERA); $i++) {
				if (CAMERA[$i]['ENABLED']) {
					if (CAMERA[$i]['HISTORY_ENABLED']) {
						$sleep = ASYNC_TASK_RUN_INTERVAL * ($i + 1); //Задаёт отсрочку запуска задачи на интервал * порядковый номер камеры
						async_exec("task_history", $sleep, $i); //Запуск задачи записи историй
						writeLog("PROCESS", "TASK task_history() FOR CAMERA$i WILL BE LAUNCHED AFTER $sleep SECONDS");
					}
					if ((CAMERA[$i]['REC_ENABLED']) && (!DISABLE_RECORDER)) {
						$sleep = ASYNC_TASK_RUN_INTERVAL * (count(CAMERA) + $i + 1); //Задаёт отсрочку запуска задачи на интервал * (кол-во камер + порядковый номер камеры) для того чтобы исключить одновременный запуск задач, с целью снижения пиковой нагрузки на ЦП/сеть/память/диск/...
						async_exec("task_rec", $sleep, $i); //Запуск задачи записи видеонаблюдения
						writeLog("PROCESS", "TASK task_rec() FOR CAMERA$i WILL BE LAUNCHED AFTER $sleep SECONDS");
					}
				}
			}
		} else {
			writeLog("PROCESS", "SCHEDULER IS DISABLED IN CONFIGURATION OR OFFLINE_MODE IS ENABLED, DO NOTHING");
		}
	}
	
	function task_cleaner() { //Запуск задачи очистки старых медиа файлов
		if (DAYS_TO_DELETE_OLD_MEDIA != 0) { //Не будет запущена при значении DAYS_TO_DELETE_OLD_MEDIA = 0
			$media_to_delete = array();
			exec("find " . PATH_TO_DELETE_OLD_MEDIA . "* -mtime +" . DAYS_TO_DELETE_OLD_MEDIA . "", $media_to_delete);
			$media_to_delete_result = exec("find " . PATH_TO_DELETE_OLD_MEDIA . "* -mtime +" . DAYS_TO_DELETE_OLD_MEDIA . " -delete");
			writeLog("PROCESS", "SCHEDULER FOUND AND TRY TO DELETE " . count($media_to_delete) . " OLD MEDIA FILES, ADDITIONAL SHELL MESSAGE IF EXISTS: $media_to_delete_result");
		}
	}
	
	function task_history($camera_id) { //Запуск задачи записи историй
		$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
		$get_from_ip_webcam_result = false;
		do {
			$get_from_ip_webcam_result = getFromIpWebcam(CAMERA[$camera_id]['CONFIG'], 0); //Получает изображение с камеры
			$retry_count--;
		} while ((!$get_from_ip_webcam_result) && ($retry_count > 0)); //При необходимости повторяет попытку
		if ($get_from_ip_webcam_result != false) { //Записывает путь к изображению в индексный файл только если изображение создано и имеет не нулевой размер
			$history_filename = HISTORY_PATH . "history_CAMERA$camera_id" . "_" . CAMERA[$camera_id]['CONFIG']['NAME'] . "_" . LOG_PATH_SECRET . ".txt";
			file_put_contents($history_filename, "$get_from_ip_webcam_result\n", FILE_APPEND);
		} else {
			if ((NOTIFY_ADMIN_TASK_ACTIONS_ERROR) && (!((isset(CAMERA[$camera_id]['NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF'])) && (CAMERA[$camera_id]['NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF'])))) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_task_history_error_text", SUPPORT_CONTACT[0], $camera_id), null, true); } //В случае ошибки отправляет уведомление администратору (если включен параметр NOTIFY_ADMIN_TASK_ACTIONS_ERROR и не включен параметр NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF для данной камеры)
			writeLog("ERROR", "CRITICAL - FAILED TO GET IMAGE FOR TASK_HISTORY FROM CAMERA$camera_id");
		}
	}
	
	function task_rec($camera_id) { //Запуск задачи записи видеонаблюдения
		$camera_config_rewrited = array_replace(CAMERA[$camera_id]['CONFIG'], CAMERA[$camera_id]['REC_CONFIG_REWRITE']); //Перезаписывает параметры камеры для задачи записи видео, см. описание REC_CONFIG_REWRITE в конфигурации
		$start_at = time();
		$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
		$get_from_ip_webcam_result = false;
		do {
			$get_from_ip_webcam_result = getFromIpWebcam($camera_config_rewrited, (isset($camera_config_rewrited['SHEDULED_REC_DURATION']) ? $camera_config_rewrited['SHEDULED_REC_DURATION'] : 3600)); //Получает видео с камеры длительностью SHEDULED_REC_DURATION или 3600 секунд если SHEDULED_REC_DURATION не установлен в массиве REC_CONFIG_REWRITE
			$retry_count--;
		} while ((!$get_from_ip_webcam_result) && ($retry_count > 0) && ((time() - $start_at) < ATTEMPTS_TO_GET_REC_VIDEO_TIMEOUT)); //При необходимости повторяет попытку в пределах кол-ва попыток и таймаута попыток
		if ($get_from_ip_webcam_result != false) { //Записывает путь к видео в индексный файл только если изображение создано и имеет не нулевой размер
			$rec_filename = HISTORY_PATH . "rec_CAMERA$camera_id" . "_" . $camera_config_rewrited['NAME'] . "_" . LOG_PATH_SECRET . ".txt";
			file_put_contents($rec_filename, "$get_from_ip_webcam_result\n", FILE_APPEND);
		} else {
			$failed_after = time() - $start_at;
			if ((NOTIFY_ADMIN_TASK_ACTIONS_ERROR) && (!((isset(CAMERA[$camera_id]['NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF'])) && (CAMERA[$camera_id]['NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF'])))) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_task_rec_error_text", SUPPORT_CONTACT[0], $camera_id, $failed_after), null, true); } //В случае ошибки отправляет уведомление администратору (если включен параметр NOTIFY_ADMIN_TASK_ACTIONS_ERROR и не включен параметр NOTIFY_ADMIN_TASK_ACTIONS_ERROR_OFF для данной камеры)
			writeLog("ERROR", "CRITICAL - FAILED TO GET VIDEO FOR TASK_REC FROM CAMERA$camera_id AFTER $failed_after SECONDS");
		}
	}
	
	function command_start($from_id, $chat_id) { //Отправляет сообщение с приветсвием, инструкциями и списком камер
		$status_sting = "";
		sendChatAction($chat_id); //Отправляет в чат статус печатает
		for ($i = 0; $i < count(CAMERA); $i++) { //Для администратора формируется список из всех камер, для остальных пользователей только доступных и разрешенных для данного пользователя
			if (((CAMERA[$i]['ENABLED']) && (in_array($from_id, CAMERA[$i]['ACCESS']))) || ($from_id == ADMIN_CHAT_ID)) {
				$status_sting = $status_sting . (pingInterface(CAMERA[$i]['CONFIG']['IP'], STATUS_PING_TIMEOUT) ? "\xF0\x9F\x9F\xA2" : "\xF0\x9F\x9F\xA1") . " /camera$i " . CAMERA[$i]['CONFIG']['NAME'] . (CAMERA[$i]['ENABLED'] ? "" : " \xF0\x9F\x9A\xA7") . "\n";
			} //Те камеры, которые не ответили на проверку связи в течении установленного STATUS_PING_TIMEOUT кол-ва секунд помечаются жёлтым цветом
		}
		$all_cameras_string = (featury_check_if_olny_one_camera_available($from_id) === false) ? (bot_dictonary("command_start_all_cameras_add_text")) : (""); //Если камера не единственная добавляется строка с описанием команды /all_cameras
		if (!empty(JUST_FOR_FUN_SEND_START_STICKER)) { sendSticker($chat_id, JUST_FOR_FUN_SEND_START_STICKER[rand(0, count(JUST_FOR_FUN_SEND_START_STICKER) - 1)]); } //Если в данном массиве JUST_FOR_FUN_SEND_START_STICKER имеются стикеры, один из них будет отправлен
		sendMessage($chat_id, bot_dictonary("command_start_text", $status_sting, $all_cameras_string, SUPPORT_CONTACT[1], SUPPORT_CONTACT[2], ($from_id == ADMIN_CHAT_ID ? "\n\n#" . getTag() : "")));
	}
	
	function command_all_cameras($from_id, $first_name, $chat_id) { //Отправляет фото со всех доступных ENABLED и доступных пользователю ACCESS камер, пропуская временнно недоступные
		if (featury_check_if_olny_one_camera_available($from_id) === false) { //Если кол-во доступных камер не равно единице, действуем по логике команды /all_cameras
			$wait_message_id = sendMessage($chat_id, bot_dictonary("wait_message_text")); //Отправляет сообщение с просьбой подождать, после получения любого резульата удаляет его
			$images_from_all_available_cameras = array();
			$available_cameras_cameras_count = 0;
			for ($i = 0; $i < count(CAMERA); $i++) { //Перебирает все камеры
				if ((CAMERA[$i]['ENABLED']) && (in_array($from_id, CAMERA[$i]['ACCESS']))) { //Проверят что камера ENABLED и у пользователя есть к ней доступ ACCESS
					$available_cameras_cameras_count++;
					$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
					$get_from_ip_webcam_result = false;
					do {
						sendChatAction($chat_id, "upload_photo"); // Отправляет в чат каждый раз статус
						$get_from_ip_webcam_result = getFromIpWebcam(CAMERA[$i]['CONFIG'], 0); //Получает изображение с каждой камеры
						$retry_count--;
					} while ((!$get_from_ip_webcam_result) && ($retry_count > 0)); //При необходимости повторяет попытку
					if ($get_from_ip_webcam_result) { //Если изображение получено и имеет ненулевой размер добавляет в список для отправки и подписывает командой открытия камеры и её именем
						$images_from_all_available_cameras[] = array('image' => $get_from_ip_webcam_result, 'caption' => "	\xF0\x9F\x93\xB7 /camera$i " . CAMERA[$i]['CONFIG']['NAME']);
					}
				}
			}
			deleteMessage($chat_id, $wait_message_id);//Удаляет сообщение с просьбой подождать
			$send_message_result = false;
			if (count($images_from_all_available_cameras) > 0) {//Если добавлено хотябы одно изображение - отправляет
				$send_message_result = sendMediaGroup($chat_id, array_column($images_from_all_available_cameras, 'image'), array_column($images_from_all_available_cameras, 'caption'));
			}
			if  ($send_message_result) { //При получении ответа об успешной отправке от Telegram API
				if (count($images_from_all_available_cameras) == $available_cameras_cameras_count) {//Сообщает администратору об успешном выполнении пользовательской команды
					if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "фотографии", "камер всех"), createInlineKeyboard(createInlineKey(bot_dictonary("button_get_message_copy_text"), "get_message$send_message_result $chat_id " . (count($images_from_all_available_cameras) - 1)))); }
				} else {//Сообщает администратору об частично неуспешном выполнении пользовательской команды (кол-во изображений не равно кол-ву доступных ENABLED и доступных пользователю ACCESS камер)
					if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_warn_text", SUPPORT_CONTACT[0], $first_name, "фотографии", "камер всех", ($available_cameras_cameras_count - count($images_from_all_available_cameras))), createInlineKeyboard(createInlineKey(bot_dictonary("button_get_message_copy_text"), "get_message$send_message_result $chat_id " . (count($images_from_all_available_cameras) - 1)))); }
				}
			} else { //При получении ответа об ошибке при отправке от Telegram API
				sendMessage($chat_id, bot_dictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "фотографии", "камер всех")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET IMAGE FOR USER $from_id $first_name FROM ALL CAMERAS");
			}
		} else { //Если доступна всего 1 камера передаём параметры в функцию command_camera и выполняем действия аналогичные получению команды с явным указанием камеры
			command_camera($from_id, $first_name, $chat_id, featury_check_if_olny_one_camera_available($from_id));
		}
	}
	
	function command_log($from_id, $first_name, $chat_id) { //Отправляет дампы всех логов в виде файла администратору
		if ($from_id == ADMIN_CHAT_ID) { //Проверяет что сообщение от администратора
			$log_files = array();
			$files_to_send = array();
			exec("ls -1 -c " . LOG_PATH . "*log_*" . LOG_PATH_SECRET . ".txt", $log_files); //Получает список файлов соответствующих шаблону имени лога, с сортировкой от последнего изменения
			foreach ($log_files as $log_file) { //Перебирает список файлов
				if ((file_exists($log_file)) && (filesize($log_file) > 0)) {
					$dump_file_name = str_ireplace(LOG_PATH_SECRET, date("YmdHis", filemtime($log_file)), $log_file); //Формирует имена для файлов дампов
					exec("tail -n " . MAX_LOG_READ_LINES . " $log_file > $dump_file_name"); //Создаёт файлы дампов (временные) для каждого файла лога, извлекая в дамп последние MAX_LOG_READ_LINES строк
					if ((file_exists($dump_file_name)) && (filesize($dump_file_name) > 0)) { //Если файл создался, имеет ненулевой размер добавляет в список на отправку
						$files_to_send[] = array('filename' => $dump_file_name, 'caption' => "\xF0\x9F\x93\x9D " . date("Y.m.d H:i:s", filemtime($log_file)));//подписывая датой последнего изменения
					}
				}
			}
			if (!empty($files_to_send)) {//Если список для отправки не пуст - отправляет файлы группами по 10 штук - ограничение метода sendMediaGroup Telegram API
				for ($i = 0; $i < count($files_to_send); $i = $i + 10) {
					sendMediaGroup($chat_id, array_slice(array_column($files_to_send, 'filename'), $i, 10), array_slice(array_column($files_to_send, 'caption'), $i, 10));
				}
				foreach (array_column($files_to_send, 'filename') as $dump_file_name) {
					unlink($dump_file_name); //Удаляет файлы дампов (временные)
				}
			} else {//Если список пуст (такого не должно быть, это в любом случае ошибка, т.к. хотя бы действия по запросу логов должны залогироваться)
				sendMessage($chat_id, bot_dictonary("message_to_admin_error_get_logs_text", SUPPORT_CONTACT[0], getTag()));//Сообщает администратору об ошибке
			}
		} else { //Если сообщение не от администратора
			sendMessage($chat_id, bot_dictonary("message_to_user_unauth_to_logs_text"));//Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_unauth_to_logs_text", SUPPORT_CONTACT[0], $first_name)); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO /log, HIS ID != ADMIN_CHAT_ID");
		}
	}
	
	function command_camera($from_id, $first_name, $chat_id, $camera_id) { //Отправляет фото с $camera_id
		if ((CAMERA[$camera_id]['ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['ACCESS']))) { //Проверяет что камера ENABLED и у пользователя есть к ней доступ ACCESS
			$wait_message_id = sendMessage($chat_id, bot_dictonary("wait_message_text")); //Отправляет сообщение с просьбой подождать, после получения любого резульата удаляет его
			$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
			$get_from_ip_webcam_result = false;
			$filemtime = 0;
			do {
				sendChatAction($chat_id, "upload_photo"); //Отправляет статус в чат
				$get_from_ip_webcam_result = getFromIpWebcam(CAMERA[$camera_id]['CONFIG'], 0); //Получает изображение с камеры
				$retry_count--;
			} while ((!$get_from_ip_webcam_result) && ($retry_count > 0)); //При необходимости повторяет попытку
			deleteMessage($chat_id, $wait_message_id); //Удаляет сообщение с просьбой подождать.
			$filename = ($get_from_ip_webcam_result) ? ($get_from_ip_webcam_result) : (featury_get_last_available_photo($camera_id, $from_id, $filemtime)); //Если результат получения фото с камеры успешный, файл для отправки - полученное фото, иначе, пробудем получить последнее фото из историй (если соблюдены условия просмотра альбома)
			$send_message_result = sendPhoto($chat_id, ($get_from_ip_webcam_result) ? (bot_dictonary("photo_sent_message_text")) : (bot_dictonary("last_photo_sent_message_text", date("Y.m.d H:i", $filemtime))), $filename, featury_make_inline_keyboard_for_photo($from_id, $camera_id, $get_from_ip_webcam_result)); //Не проверяя успешность пытаетмя отправить фото (т.к. если фото нет, отправка сообщения всё равно будет неудачной);
			if ($send_message_result) { //При получении ответа об успешной отправке от Telegram API //Сообщает администратору об успешном выполнении пользовательской команды //Либо о не совсем успешном - когда получилось отправить только фото из альбома.
				if (($get_from_ip_webcam_result) && (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS))) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "фото", "/camera$camera_id"), createInlineKeyboard(createInlineKey(bot_dictonary("button_get_message_copy_text"), "get_message$send_message_result $chat_id"))); }
				if ((!$get_from_ip_webcam_result) && (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR))) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_warn_text", SUPPORT_CONTACT[0], $first_name, "наверное свежую фотку", "/camera$camera_id", "только из альбома, свежей"), createInlineKeyboard(createInlineKey(bot_dictonary("button_get_message_copy_text"), "get_message$send_message_result $chat_id"))); }
				async_exec("editMessageReplyMarkup", DISPLAY_CONTEXT_KEYBOARD_TIMEOUT, $chat_id, $send_message_result); //Создаёт отложенную на DISPLAY_CONTEXT_KEYBOARD_TIMEOUT задачу удалить контекстные кнопки
			} else { //При получении ответа об ошибке от Telegram API
				sendMessage($chat_id, bot_dictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "фото", "/camera$camera_id")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET IMAGE FOR USER $from_id $first_name FROM CAMERA$camera_id");
			}
		} else { //Если камера отключена или у пользователя нет доступа к камере ACCESS //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			sendMessage($chat_id, bot_dictonary("message_to_user_offline_or_access_denied_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_offline_or_access_denied_text", SUPPORT_CONTACT[0], $from_id, $first_name, "фото", "/camera$camera_id")); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO /camera$camera_id WHEN CAMERA IS OFFLINE OR USER IS NOT IN ACCESS LIST");
		}
	}
	
	function command_video($from_id, $first_name, $chat_id, $camera_id, $duration) { //Отправляет видео с $camera_id
		if ((CAMERA[$camera_id]['ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['ACCESS']))) { //Проверяет что камера ENABLED и у пользователя есть к ней доступ ACCESS
			$wait_message_id = sendMessage($chat_id, bot_dictonary("wait_message_text")); //Отправляет сообщение с просьбой подождать, после получения любого резульата удаляет его
			$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
			$get_from_ip_webcam_result = false;
			do {
				sendChatAction($chat_id, "record_video"); //Отправляет статус в чат
				$get_from_ip_webcam_result = getFromIpWebcam(CAMERA[$camera_id]['CONFIG'], $duration); //Получает изображение с камеры
				$retry_count--;
			} while ((!$get_from_ip_webcam_result) && ($retry_count > 0)); //При необходимости повторяет попытку
			deleteMessage($chat_id, $wait_message_id); //Не проверяя успешность пытаетмя отправить фото (т.к. если фото нет, отправка сообщения всё равно будет неудачной); Удаляет сообщение с просьбой подождать.
			$send_message_result = sendVideo($chat_id, bot_dictonary("photo_sent_message_text"), $get_from_ip_webcam_result);
			if ($send_message_result) { //При получении ответа об успешной отправке от Telegram API //Сообщает администратору об успешном выполнении пользовательской команды
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "видео", "/camera$camera_id"), createInlineKeyboard(createInlineKey(bot_dictonary("button_get_message_copy_text"), "get_message$send_message_result $chat_id"))); }
			} else { //При получении ответа об ошибке от Telegram API
				sendMessage($chat_id, bot_dictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "видео", "/camera$camera_id")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET VIDEO FOR USER $from_id $first_name FROM CAMERA$camera_id");
			}
		} else { //Если камера отключена или у пользователя нет доступа к камере ACCESS //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			sendMessage($chat_id, bot_dictonary("message_to_user_offline_or_access_denied_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_offline_or_access_denied_text", SUPPORT_CONTACT[0], $from_id, $first_name, "видео", "/camera$camera_id")); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO /camera$camera_id WHEN CAMERA IS OFFLINE OR USER IS NOT IN ACCESS LIST");
		}
	}
	
	function command_album($from_id, $first_name, $chat_id, $camera_id, $offset) { //Отправляет альбом с $camera_id
		if ((CAMERA[$camera_id]['ENABLED']) && (CAMERA[$camera_id]['HISTORY_ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['ACCESS'])) && (in_array($from_id, CAMERA[$camera_id]['HISTORY_ACCESS']))) { //Проверяет что камера ENABLED и у пользователя есть к ней доступ ACCESS, дополнительно проверяет что истории включены HISTORY_ENABLED и у пользователя есть доступ к историям HISTORY_ACCESS
			$history_filename = HISTORY_PATH . "history_CAMERA$camera_id" . "_" . CAMERA[$camera_id]['CONFIG']['NAME'] . "_" . LOG_PATH_SECRET . ".txt"; //Формирует имя файла где будет произведён поиск индекса историй
			$history_file_lines = array();
			if ((file_exists($history_filename)) && (filesize($history_filename) > 0)) { //Если файл с индексом существует и имеет ненулевой размер
				sendChatAction($chat_id, "upload_photo"); //Отправляет статус в чат
				$history_file_lines = file($history_filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); //Считывает все строчки без знаков переноса и без пустых строк
			}
			$history_files = array();
			$min_i = (count($history_file_lines) > MAX_HISTORY_SHOW_ITEMS) ? (count($history_file_lines) - MAX_HISTORY_SHOW_ITEMS) : (0); //Определяет крайний индекс, чтобы не показывать альбом глубже, чем задано ограничением параметра MAX_HISTORY_SHOW_ITEMS 
			for ($i = $min_i; $i < count($history_file_lines); $i++) {
				if ((file_exists($history_file_lines[$i])) && (filesize($history_file_lines[$i]) > 0)) {
					$history_files[] = $history_file_lines[$i]; //Каждый файл из списка в индексном файле проверяет на существование, добавляет в список
				}
			}
			$history_files_count = count($history_files); //Считает только уже проверенные и существующие файлы
			$send_message_result = false;
			if ($history_files_count > $offset) { //Если файлов больше 0 или больше сдвига, если смотрим не первую страницу
				$start_index = $history_files_count - $offset;
				$stop_index = ($start_index - 10 > 0) ? ($start_index - 10) : (0);
				$files_to_send = array();
				for ($i = $start_index - 1; $i >= $stop_index; $i--) {//Добавляем файлы в список на отправку, подписывая датой изменения
					$files_to_send[] = array('image' => $history_files[$i], 'caption' => "\xF0\x9F\x93\xB7 " . date("Y.m.d H:i", filemtime($history_files[$i])));
				}
				$send_message_result = sendMediaGroup($chat_id, array_column($files_to_send, 'image'), array_column($files_to_send, 'caption'));//Отправляет альбом
				if ($send_message_result) { //При получении ответа об успешной отправке от Telegram API 
					if ($stop_index > 0) { //Если ещё остались изображения в альбоме отправляет сообщение с информацией о страницах и кнопкой дальнейшего просмотра
						$message_with_keyboard = sendMessage($chat_id, bot_dictonary("label_album_shown_images_x_of_y_text", (count($files_to_send) + $offset), $history_files_count), createInlineKeyboard(createInlineKey(bot_dictonary("button_album_show_more_text"), "album$camera_id " . ($offset + 10))));
						async_exec("editMessageReplyMarkup", DISPLAY_CONTEXT_KEYBOARD_TIMEOUT, $chat_id, $message_with_keyboard); //Создаёт отложенную на DISPLAY_CONTEXT_KEYBOARD_TIMEOUT задачу удалить контекстные кнопки
					} else { //Если изображений не осталось отправляет сообщение пользователю с данной информацией
						sendMessage($chat_id, bot_dictonary("label_album_no_more_text")); 
					} //Сообщает администратору об успешном выполнении пользовательской команды но только для перой странциы альбома, чтобы не дублировать сообщения при пролистывании в глубину
					if (($from_id != ADMIN_CHAT_ID) && ($offset == 0)  && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "альбом", "/camera$camera_id"), createInlineKeyboard(createInlineKey(bot_dictonary("button_get_message_copy_text"), "get_message$send_message_result $chat_id " . (count($files_to_send) - 1)))); }
				} else { //При получении ответа об ошибке от Telegram API
					sendMessage($chat_id, bot_dictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
					if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "альбом", "/camera$camera_id")); }
					writeLog("ERROR", "USER CRITICAL - FAILED TO GET ALBUM FOR USER $from_id $first_name FROM CAMERA$camera_id - !SEND_MESSAGE_RESULT");
				}
			} else { //Если файлов изначально меньше 0 или меньше сдвига (это значит что либо истории не писались либо некорректно рассчиталось кол-во)
				$send_message_result = sendMessage($chat_id, bot_dictonary("message_to_user_album_no_images_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "альбом", "/camera$camera_id")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET ALBUM FOR USER $from_id $first_name FROM CAMERA$camera_id - NO IMAGES");
			}
		} else {//Если камера отключена или у пользователя нет доступа к камере ACCESS //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			sendMessage($chat_id, bot_dictonary("message_to_user_offline_or_access_denied_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, bot_dictonary("message_to_admin_offline_or_access_denied_text", SUPPORT_CONTACT[0], $from_id, $first_name, "альбом", "/camera$camera_id")); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO ALBUM /camera$camera_id WHEN CAMERA IS OFFLINE|HISTORY NOT ENABLED OR USER IS NOT IN ACCESS LIST");
		}
	}
	
	function command_mailing($from_id, $chat_id, $message_id, $confirmed = false) { //Выполняет рассылку сообщения с message_id всем AUTHORIZED_ID
		if ($from_id == ADMIN_CHAT_ID) { //Проверяет что сообщение от администратора (дублирующая проверка для целей обратной совместимости)
			if (!$confirmed) { //Если сообщение только получено
				$message_for_confirm = copyMessage($chat_id, $from_id, $message_id, createInlineKeyboard(createInlineKey(bot_dictonary("button_confirm_mailing_text"), "mailing$message_id"))); //Отправляет сообщение отправителю на проверку
				async_exec("editMessageReplyMarkup", DISPLAY_CONTEXT_KEYBOARD_TIMEOUT, $chat_id, $message_for_confirm); //Создаёт отложенную на DISPLAY_CONTEXT_KEYBOARD_TIMEOUT задачу удалить контекстные кнопки
			} else {
				$sent_messages_count = 0;
				$mailing_method = MAILING_METHOD; //Метод рассылки сообщений пользователям (forwardMessage - сообщение от администратора будет отображаться пользователю как пересланное, copyMessage - сообщение будет отображаться как просто отправленное ботом)
				writeLog("PROCESS", "BEGIN MAILING MESSAGE_ID $message_id METHOD $mailing_method");
				foreach (AUTHORIZED_ID as $recipient_chat_id) {
					if ($mailing_method($recipient_chat_id, $from_id, $message_id)) { //Копирует или пересылает сообщение каждому AUTHORIZED_ID, и если сообщение отправлено
						$sent_messages_count++; //считает как успешно отправлено
					}
				}
				writeLog("PROCESS", "END MAILING, SENT $sent_messages_count OF " . count(AUTHORIZED_ID) . " MESSAGES");
				sendMessage($chat_id, bot_dictonary("message_to_admin_mailing_done_text", SUPPORT_CONTACT[0], $sent_messages_count, count(AUTHORIZED_ID))); //Сообщает администратору о выполнении команды
			}
		} //Так как данная проверка только для целей обратной совместимости, и сценарий её Не прохождения маловероятен, т.к. bot_command не должен осуществлять вызов command_mailing не от ADMIN_CHAT_ID, блок else опущен
	}
	
	function command_get_message($from_id, $chat_id, $message_id, $from_chat_id, $media_group_messages_count = 0) { //Пересылает сообщение message_id из чата from_chat_id в чат из которого запросили пересылку chat_id
		if ($from_id == ADMIN_CHAT_ID) { //Проверяет что сообщение от администратора //; media_group_messages_count должен быть на 1 меньше чем кол-во фактически сообщения в группе
			for ($i = 0; $i <= $media_group_messages_count; $i++) { // Если объектом пересылки является MediaGroup - каждое сообщение из группы пересылается отдельно.
				writeLog("PROCESS", "BEGIN FORWARD MESSAGE $message_id + $i (FROM CHAT $from_chat_id) TO $chat_id");
				forwardMessage($chat_id, $from_chat_id, $message_id + $i);//Пересылает сообщение message_id из чата from_chat_id в чат из которого запросили пересылку chat_id 
			}
		} //Так как, кнопка вызова получения копии сообщения появляется только у администратора, данная проверка осуществяется только для целей дополнительной безопасности, и сценарий её Не прохождения маловероятен (поэтому действия else отсутствуют)
	}
	
	function featury_get_last_available_photo($camera_id, $from_id, &$filemtime = 0) { //Получает последнее фото из альбома камеры (если камера ENABLED, истории HISTORY_ENABLED, и пользователь имеет доступ ACCESS и HISTORY_ACCESS
		if ((CAMERA[$camera_id]['ENABLED']) && (CAMERA[$camera_id]['HISTORY_ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['ACCESS'])) && (in_array($from_id, CAMERA[$camera_id]['HISTORY_ACCESS']))) { //Проверяет что камера ENABLED и у пользователя есть к ней доступ ACCESS, дополнительно проверяет что истории включены HISTORY_ENABLED и у пользователя есть доступ к историям HISTORY_ACCESS
			$history_filename = HISTORY_PATH . "history_CAMERA$camera_id" . "_" . CAMERA[$camera_id]['CONFIG']['NAME'] . "_" . LOG_PATH_SECRET . ".txt"; //Формирует имя файла где будет произведён поиск индекса историй
			$history_file_lines = array();
			if ((file_exists($history_filename)) && (filesize($history_filename) > 0)) { //Если файл с индексом существует и имеет ненулевой размер
				$history_file_lines = file($history_filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); //Считывает все строчки без знаков переноса и без пустых строк
				if ((file_exists($history_file_lines[count($history_file_lines) - 1])) && (filesize($history_file_lines[count($history_file_lines) - 1]) > 0)) {
					$last_available_photo = $history_file_lines[count($history_file_lines) - 1]; //Получает последнюю строку из history_file
					$filemtime = filemtime($last_available_photo); //Получает дату изменения файла
					writeLog("PROCESS", "featury_get_last_available_photo($camera_id, $from_id, filemtime) FOUND FILE: $last_available_photo FILEMTIME: $filemtime");
					return $last_available_photo;
				}
			}
		}
		writeLog("PROCESS", "featury_get_last_available_photo($camera_id, $from_id, filemtime) DOES NOT HAVE CONDITIONS FOR FILE SEARCH"); // Поиск произведён не будет, т.к. нет логических условий
		return false;
	}
	
	function featury_check_if_olny_one_camera_available($from_id) { //Проверка на ситауцию, когда пользователю доступна всего одна камера // (!) Важно при использовании функции в логике использовать сравнение === вместо == чтобы не путать значение false со значением $camera_id = 0
		$available_cameras_cameras_count = 0;
		$available_camera_id = null;
		for ($i = 0; $i < count(CAMERA); $i++) { //Перебирает все камеры
			if ((CAMERA[$i]['ENABLED']) && (in_array($from_id, CAMERA[$i]['ACCESS']))) { //Проверят что камера ENABLED и у пользователя есть к ней доступ ACCESS
				$available_cameras_cameras_count++;
				$available_camera_id = $i; // Присваивает camera_id на случай, если доступная камера окажется всего одна
			}
		}
		if ($available_cameras_cameras_count == 1) {
			return $available_camera_id;
		} else {
			return false;
		}
	}
	
	function featury_make_inline_keyboard_for_photo($from_id, $camera_id, $get_from_ip_webcam_result = false) { //Создаёт контекстную клавиатуру для отправки вместе с фото
		$inline_keyboard_keys = array();
		$callback_requested_video_duration = (isset(CAMERA[$camera_id]['CALLBACK_REQUESTED_VIDEO_DURATION'])) ? CAMERA[$camera_id]['CALLBACK_REQUESTED_VIDEO_DURATION'] :  CALLBACK_REQUESTED_VIDEO_DURATION; //Дляительность видео, которое может запросить пользователь - если не определено для конкретной камеры то используем глобальный параметр
		if (($callback_requested_video_duration != 0) && ($get_from_ip_webcam_result)) { //Если запрос видео не отключен (параметр длительности 0) и если фото было умпешно получено с камеры (get_from_ip_webcam_result != false)
			$inline_keyboard_keys[] = createInlineKey(bot_dictonary("button_take_video_text", $callback_requested_video_duration), "video$camera_id " . $callback_requested_video_duration);//Добавляет контекстную кнопку записи видеоклипа
		}
		if ((CAMERA[$camera_id]['HISTORY_ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['HISTORY_ACCESS']))) {//Если у камеры включены истории HISTORY_ENABLED и пользователь имеет к ним доступ HISTORY_ACCESS
			$inline_keyboard_keys[] = createInlineKey(bot_dictonary("button_get_album_text"), "album$camera_id 0");//Добавляет контекстную кнопку получения историй
		}
		return (!empty($inline_keyboard_keys)) ? createInlineKeyboard(...$inline_keyboard_keys) : null; //Если создана хоть одна кнопка возвращаем клавиатуру, иначе null
	}

	function featury_setup_bot_menu() {//Создаёт и устанавливает меню бота
		$bot_menu_commands = array();
		$bot_menu_commands[] = array('command' => "start", 'description' => bot_dictonary("menu_button_command_start_text")); //Добавление стандартных пунктов меню бота
		for ($i = 0; $i < count(CAMERA); $i++) { //Формирование списка камер для меню бота
			if ((CAMERA[$i]['ENABLED']) && !((isset(CAMERA[$i]['HIDE_FROM_BOT_MENU'])) && (CAMERA[$i]['HIDE_FROM_BOT_MENU']))) { $bot_menu_commands[] = array('command' => "camera$i", 'description' => bot_dictonary("menu_button_command_camera_i_text", CAMERA[$i]['CONFIG']['NAME'])); }
		}//В меню добавляются все ENABLED камеры при отсутствии парамента HIDE_FROM_BOT_MENU=true
		if (featury_check_if_olny_one_camera_available(ADMIN_CHAT_ID) === false) { //Если камера не единственная добавляется пункт меню /all_cameras. (!) Важно, проверка производится от имени ADMIN_CHAT_ID и если он задан некорректно на момент инициализации или не имеет доступа ко всем камерам - логика отработает неверно.
			$bot_menu_commands[] = array('command' => "all_cameras", 'description' => bot_dictonary("menu_button_command_all_cameras_text"));
		}
		setMyCommands($bot_menu_commands);//Устанавливат меня запросом к Telegram API
	}
	
	if ((!empty($argv)) && ($argc > 1)) { //Проверяет, запущено ли приложение через CLI и имеет ли более 1 агрумента (т.к. 1 это имя скрипта, следовательно, запуск без аргументов будет иметь $argc = 1)
		if ($argv[1] != "app_init") { writeLog("PROCESS", "BACKEND CALL: $argv[1](" . implode(", ", array_slice($argv, 2)) . ")"); } //При начальной инициализации BACKEND CALL не пишется в лог, т.к. ещё могут быть не созданы папки пути
		if (in_array($argv[1], BACKEND_FUNCTIONS_ALLOWED)) { //Аргумент[1] при запуске через CLI должен содержать имя функции //Проверяем, разрешен ли её запуск
			$argv[1](...array_slice($argv, 2)); //Запускает функцию имя которой передано через CLI, остальные аргументы передаёт в функцию
		} else {
			writeLog("ERROR", "FUNCTION $argv[1]() IS NOT IN BACKEND_FUNCTIONS_ALLOWED");
		}
	} else if ((!empty($argv)) && ($argc == 1)) { //Если приложение запущено через CLI, предполагаем что оно будет работать в режиме long polling (самостоятельно запрашивать обновления от Telegram API а не принимать обновления через Webhook)
		app_long_polling_mode();
	} else { //Если приложение запущено не через CLI, предполагаем что приложение запущено через HTTP(S)-запрос
		app_webhook_mode();
	}
?>
