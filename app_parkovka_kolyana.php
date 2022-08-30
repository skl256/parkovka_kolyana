<?php

	//Parkovka Kolyana v 2022-08-30-11-25 https://t.me/skl256 https://github.com/skl256/parkovka_kolyana.git
	
	//Перед использованием необходимо убедиться в наличии модулей php-curl php-mbstring, при необходимости установить: sudo apt-get install php-curl php-mbstring
	//Перед использованием необходимо убедится в наличии системных утилит linux таких как iputils-ping (часто отсутствует в docker) и установить ffmpeg (пример установки необходимого набора полностью: apt update -y && apt install -y nginx php php-fpm php-curl php-mbstring ffmpeg iputils-ping)
	//Для инициализации (создание, актуализация меню, создание папок для хранения логов, историй): от имени пользователя web-сервера cd /полный/путь/до/папки/скрипта && sudo -u www-data php -f app_parkovka_kolyana.php app_init
	//Для добавления функционала историй необходимо добавить в cron пользователя web-сервера (обычно www-data: crontab -u www-data -e) либо в cron docker: 0 * * * * cd /полный/путь/до/папки/скрипта && php -f app_parkovka_kolyana.php app_scheduler

	
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
		if (DEBUG_MODE) { echo "$line<br />\n"; }
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
	
	function botDictonary($speech, ...$var) { //Подбирает случайный ответ бота из словаря с ключём $speech, подставляя значения ...$vars
		if (!isset(BOT_DICTONARY[$speech])) { writeLog("ERROR", "CRITICAL - DICTONARY KEY $speech NOT FOUND"); }
		return isset(BOT_DICTONARY[$speech]) ? sprintf(BOT_DICTONARY[$speech][rand(0, count(BOT_DICTONARY[$speech]) - 1)], ...$var) : BOT_DICTONARY["default"][0];
	}
	
	function recognizeСommand($update_type, $from_id, $first_name, $chat_id, $line) { //Обрабатывая входящий текст распознаёт команду боту или возвращает false
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
		} else if (in_array(trim($line, "/"), array_column(array_column(CAMERA, 'CONFIG'), 'NAME'))) { //Команда и именем NAME камеры
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
		}
		return false;
	}
	
	function app_init() { //Инициализация приложения в случаях первого запуска, запуска контейнера, либо для обновления меню
		foreach (array(LOG_PATH, HISTORY_PATH) as $path) {
			if (($path != "") && (!is_dir($path))) { mkdir($path, 0777, true); } // Создание папок ( (!)при появлении новых путей необходимо обновлять список в строке выше)
		}
		writeLog("PROCESS", "app_init() AT #" . getTag());
		$cameras_status_string = "";
		$bot_menu_commands = array();
		$bot_menu_commands[] = array('command' => "start", 'description' => botDictonary("menu_button_command_start_text")); //Добавление стандартных пунктов меню бота
		if (check_if_olny_one_camera_available(ADMIN_CHAT_ID) === false) { //Если камера не единственная добавляется пугкт меню /all_cameras. (!) Важно, проверка производится от имени ADMIN_CHAT_ID и если он задан некорректно на момент инициализации или не имеет доступа ко всем камерам - логика отработает неверно.
			$bot_menu_commands[] = array('command' => "all_cameras", 'description' => botDictonary("menu_button_command_all_cameras_text"));
		}
		for ($i = 0; $i < count(CAMERA); $i++) { //Формирование списка всех камер для сообщения-сводки; Формирование списка всех ENABLED камер для меню бота.
			$cameras_status_string = $cameras_status_string . (pingInterface(CAMERA[$i]['CONFIG']['IP']) ? "\xF0\x9F\x9F\xA2" : "\xF0\x9F\x9F\xA0") . " /camera$i " . CAMERA[$i]['CONFIG']['NAME'] . "\n" . (CAMERA[$i]['ENABLED'] ? "ENABLED \xE2\x9E\x95, " : "ENABLED \xE2\x9E\x96, ") . (CAMERA[$i]['HISTORY_ENABLED'] ? "HISTORY \xE2\x9E\x95, " : "HISTORY \xE2\x9E\x96, ") . (CAMERA[$i]['REC_ENABLED'] ? "REC \xE2\x9E\x95" : "REC \xE2\x9E\x96") . "\n";
			if (CAMERA[$i]['ENABLED']) { $bot_menu_commands[] = array('command' => "camera$i", 'description' => botDictonary("menu_button_command_camera_i_text", CAMERA[$i]['CONFIG']['NAME'])); }
		}
		$options_status_string = "\xE2\x9C\x85 OFFLINE_MODE " . ((OFFLINE_MODE) ? "\xE2\x9E\x95" : "\xE2\x9E\x96") . "\n\xE2\x9C\x85 DEBUG_MODE " . ((DEBUG_MODE) ? "\xE2\x9E\x95" : "\xE2\x9E\x96") . "\n\xE2\x9C\x85 DISABLE_SCHEDULER " . ((DISABLE_SCHEDULER) ? "\xE2\x9E\x95" : "\xE2\x9E\x96" . "\n\xE2\x9C\x85 DISABLE_RECORDER ") . ((DISABLE_RECORDER) ? "\xE2\x9E\x95" : "\xE2\x9E\x96");
		setMyCommands($bot_menu_commands); //В строке выше формирование списка основных опицй для сообщения-сводки
		sendMessage(ADMIN_CHAT_ID, botDictonary("app_init_text", getTag(), $cameras_status_string, $options_status_string)); //Отправка сообщения-сводки с именем экзампляра или хоста, списком всех камер и основных опций.
	}
	
	function app_scheduler() { //Планировщик, необходимо запускать через cron
		if ((!DISABLE_SCHEDULER) && (!OFFLINE_MODE)) { //Не будет запущен при активных параметрах OFFLINE_MODE или DISABLE_SCHEDULER или при отсутсвии настройки cron
			async_exec("task_cleaner", 0); //Запуск задачи очистки старых медиа файлов
			writeLog("PROCESS", "TASK task_cleaner() WILL BE LAUNCHED AFTER 0 SECONDS");
			for ($i = 0; $i < count(CAMERA); $i++) {
				if (CAMERA[$i]['ENABLED']) {
					if (CAMERA[$i]['HISTORY_ENABLED']) {
						$sleep = ASYNC_TASK_RUN_INTERVAL + ASYNC_TASK_RUN_INTERVAL * $i;
						async_exec("task_history", $sleep, $i); //Запуск задачи записи историй
						writeLog("PROCESS", "TASK task_history() FOR CAMERA$i WILL BE LAUNCHED AFTER $sleep SECONDS");
					}
					if ((CAMERA[$i]['REC_ENABLED']) && (!DISABLE_RECORDER)) {
						$sleep = ASYNC_TASK_RUN_INTERVAL * count(CAMERA) + ASYNC_TASK_RUN_INTERVAL * $i;
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
			if (NOTIFY_ADMIN_TASK_ACTIONS_ERROR) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_task_history_error_text", SUPPORT_CONTACT[0], $camera_id), null, true); } //В случае ошибки отправляет уведомление администратору
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
			if (NOTIFY_ADMIN_TASK_ACTIONS_ERROR) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_task_rec_error_text", SUPPORT_CONTACT[0], $camera_id, $failed_after), null, true); } //В случае ошибки отправляет уведомление администратору
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
		$all_cameras_string = (check_if_olny_one_camera_available($from_id) === false) ? (botDictonary("command_start_all_cameras_add_text")) : (""); //Если камера не единственная добавляется строка с описанием команды /all_cameras
		if (!empty(JUST_FOR_FUN_SEND_START_STICKER)) { sendSticker($chat_id, JUST_FOR_FUN_SEND_START_STICKER[rand(0, count(JUST_FOR_FUN_SEND_START_STICKER) - 1)]); } //Если в данном массиве JUST_FOR_FUN_SEND_START_STICKER имеются стикеры, один из них будет отправлен
		sendMessage($chat_id, botDictonary("command_start_text", $status_sting, $all_cameras_string, SUPPORT_CONTACT[1], SUPPORT_CONTACT[2], ($from_id == ADMIN_CHAT_ID ? "\n\n#" . getTag() : "")));
	}
	
	function command_all_cameras($from_id, $first_name, $chat_id) { //Отправляет фото со всех доступных ENABLED и доступных пользователю ACCESS камер, пропуская временнно недоступные
		if (check_if_olny_one_camera_available($from_id) === false) { //Если кол-во доступных камер не равно единице, действуем по логике команды /all_cameras
			$wait_message_id = sendMessage($chat_id, botDictonary("wait_message_text")); //Отправляет сообщение с просьбой подождать, после получения любого резульата удаляет его
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
					if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "фотографии", "камер всех")); }
				} else {//Сообщает администратору об частично неуспешном выполнении пользовательской команды (кол-во изображений не равно кол-ву доступных ENABLED и доступных пользователю ACCESS камер)
					if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_warn_text", SUPPORT_CONTACT[0], $first_name, "фотографии", "камер всех", ($available_cameras_cameras_count - count($images_from_all_available_cameras)))); }
				}
			} else { //При получении ответа об ошибке при отправке от Telegram API
				sendMessage($chat_id, botDictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "фотографии", "камер всех")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET IMAGE FOR USER $from_id $first_name FROM ALL CAMERAS");
			}
		} else { //Если доступна всего 1 камера передаём параметры в функцию command_camera и выполняем действия аналогичные получению команды с явным указанием камеры
			command_camera($from_id, $first_name, $chat_id, check_if_olny_one_camera_available($from_id));
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
				sendMessage($chat_id, botDictonary("message_to_admin_error_get_logs_text", SUPPORT_CONTACT[0], getTag()));//Сообщает администратору об ошибке
			}
		} else { //Если сообщение не от администратора
			sendMessage($chat_id, botDictonary("message_to_user_unauth_to_logs_text"));//Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_unauth_to_logs_text", SUPPORT_CONTACT[0], $first_name)); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO /log, HIS ID != ADMIN_CHAT_ID");
		}
	}
	
	function command_camera($from_id, $first_name, $chat_id, $camera_id) { //Отправляет фото с $camera_id
		if ((CAMERA[$camera_id]['ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['ACCESS']))) { //Проверяет что камера ENABLED и у пользователя есть к ней доступ ACCESS
			$wait_message_id = sendMessage($chat_id, botDictonary("wait_message_text")); //Отправляет сообщение с просьбой подождать, после получения любого резульата удаляет его
			$inline_keyboard_keys[] = createInlineKey(botDictonary("button_take_video_text", CALLBACK_REQUESTED_VIDEO_DURATION), "video$camera_id " . CALLBACK_REQUESTED_VIDEO_DURATION);//Добавляет контекстную кнопку записи видеоклипа
			if ((CAMERA[$camera_id]['HISTORY_ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['HISTORY_ACCESS']))) {//Если у камеры включены истории HISTORY_ENABLED и пользователь имеет к ним доступ HISTORY_ACCESS
				$inline_keyboard_keys[] = createInlineKey(botDictonary("button_get_album_text"), "album$camera_id 0");//Добавляет контекстную кнопку получения историй
			}
			$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
			$get_from_ip_webcam_result = false;
			do {
				sendChatAction($chat_id, "upload_photo"); //Отправляет статус в чат
				$get_from_ip_webcam_result = getFromIpWebcam(CAMERA[$camera_id]['CONFIG'], 0); //Получает изображение с камеры
				$retry_count--;
			} while ((!$get_from_ip_webcam_result) && ($retry_count > 0)); //При необходимости повторяет попытку
			deleteMessage($chat_id, $wait_message_id); //Не проверяя успешность пытаетмя отправить фото (т.к. если фото нет, отправка сообщения всё равно будет неудачной); Удаляет сообщение с просьбой подождать.
			$send_message_result = sendPhoto($chat_id, botDictonary("photo_sent_message_text"), $get_from_ip_webcam_result, createInlineKeyboard(...$inline_keyboard_keys));
			if ($send_message_result) { //При получении ответа об успешной отправке от Telegram API //Сообщает администратору об успешном выполнении пользовательской команды
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "фото", "/camera$camera_id")); }
				async_exec("editMessageReplyMarkup", DISPLAY_CONTEXT_KEYBOARD_TIMEOUT, $chat_id, $send_message_result); //Создаёт отложенную на DISPLAY_CONTEXT_KEYBOARD_TIMEOUT задачу удалить контекстные кнопки
			} else { //При получении ответа об ошибке от Telegram API
				sendMessage($chat_id, botDictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "фото", "/camera$camera_id")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET IMAGE FOR USER $from_id $first_name FROM CAMERA$camera_id");
			}
		} else { //Если камера отключена или у пользователя нет доступа к камере ACCESS //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			sendMessage($chat_id, botDictonary("message_to_user_offline_or_access_denied_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_offline_or_access_denied_text", SUPPORT_CONTACT[0], $from_id, $first_name, "фото", "/camera$camera_id")); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO /camera$camera_id WHEN CAMERA IS OFFLINE OR USER IS NOT IN ACCESS LIST");
		}
	}
	
	function command_video($from_id, $first_name, $chat_id, $camera_id, $duration) { //Отправляет видео с $camera_id
		if ((CAMERA[$camera_id]['ENABLED']) && (in_array($from_id, CAMERA[$camera_id]['ACCESS']))) { //Проверяет что камера ENABLED и у пользователя есть к ней доступ ACCESS
			$wait_message_id = sendMessage($chat_id, botDictonary("wait_message_text")); //Отправляет сообщение с просьбой подождать, после получения любого резульата удаляет его
			$retry_count = ATTEMPTS_TO_GET_PHOTO_VIDEO;
			$get_from_ip_webcam_result = false;
			do {
				sendChatAction($chat_id, "record_video"); //Отправляет статус в чат
				$get_from_ip_webcam_result = getFromIpWebcam(CAMERA[$camera_id]['CONFIG'], $duration); //Получает изображение с камеры
				$retry_count--;
			} while ((!$get_from_ip_webcam_result) && ($retry_count > 0)); //При необходимости повторяет попытку
			deleteMessage($chat_id, $wait_message_id); //Не проверяя успешность пытаетмя отправить фото (т.к. если фото нет, отправка сообщения всё равно будет неудачной); Удаляет сообщение с просьбой подождать.
			$send_message_result = sendVideo($chat_id, botDictonary("photo_sent_message_text"), $get_from_ip_webcam_result);
			if ($send_message_result) { //При получении ответа об успешной отправке от Telegram API //Сообщает администратору об успешном выполнении пользовательской команды
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "видео", "/camera$camera_id")); }
			} else { //При получении ответа об ошибке от Telegram API
				sendMessage($chat_id, botDictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "видео", "/camera$camera_id")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET VIDEO FOR USER $from_id $first_name FROM CAMERA$camera_id");
			}
		} else { //Если камера отключена или у пользователя нет доступа к камере ACCESS //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			sendMessage($chat_id, botDictonary("message_to_user_offline_or_access_denied_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_offline_or_access_denied_text", SUPPORT_CONTACT[0], $from_id, $first_name, "видео", "/camera$camera_id")); }
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
						$message_with_keyboard = sendMessage($chat_id, botDictonary("label_album_shown_images_x_of_y_text", (count($files_to_send) + $offset), $history_files_count), createInlineKeyboard(createInlineKey(botDictonary("button_album_show_more_text"), "album$camera_id " . ($offset + 10))));
						async_exec("editMessageReplyMarkup", DISPLAY_CONTEXT_KEYBOARD_TIMEOUT, $chat_id, $message_with_keyboard); //Создаёт отложенную на DISPLAY_CONTEXT_KEYBOARD_TIMEOUT задачу удалить контекстные кнопки
					} else { //Если изображений не осталось отправляет сообщение пользователю с данной информацией
						sendMessage($chat_id, botDictonary("label_album_no_more_text")); 
					} //Сообщает администратору об успешном выполнении пользовательской команды но только для перой странциы альбома, чтобы не дублировать сообщения при пролистывании в глубину
					if (($from_id != ADMIN_CHAT_ID) && ($offset == 0)  && (NOTIFY_ADMIN_USER_ACTIONS_SUCCESS)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_success_text", SUPPORT_CONTACT[0], $first_name, "альбом", "/camera$camera_id")); }
				} else { //При получении ответа об ошибке от Telegram API
					sendMessage($chat_id, botDictonary("message_to_user_user_command_error_try_again_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
					if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "альбом", "/camera$camera_id")); }
					writeLog("ERROR", "USER CRITICAL - FAILED TO GET ALBUM FOR USER $from_id $first_name FROM CAMERA$camera_id - !SEND_MESSAGE_RESULT");
				}
			} else { //Если файлов изначально меньше 0 или меньше сдвига (это значит что либо истории не писались либо некорректно рассчиталось кол-во)
				$send_message_result = sendMessage($chat_id, botDictonary("message_to_user_album_no_images_text")); //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
				if (($from_id != ADMIN_CHAT_ID) && (NOTIFY_ADMIN_USER_ACTIONS_ERROR)) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_user_command_error_text", SUPPORT_CONTACT[0], $first_name, "альбом", "/camera$camera_id")); }
				writeLog("ERROR", "USER CRITICAL - FAILED TO GET ALBUM FOR USER $from_id $first_name FROM CAMERA$camera_id - NO IMAGES");
			}
		} else {//Если камера отключена или у пользователя нет доступа к камере ACCESS //Сообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
			sendMessage($chat_id, botDictonary("message_to_user_offline_or_access_denied_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
			if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_offline_or_access_denied_text", SUPPORT_CONTACT[0], $from_id, $first_name, "альбом", "/camera$camera_id")); }
			writeLog("ACCESS", "$from_id $first_name TRY TO ACCESS TO ALBUM /camera$camera_id WHEN CAMERA IS OFFLINE|HISTORY NOT ENABLED OR USER IS NOT IN ACCESS LIST");
		}
	}
	
	function check_if_olny_one_camera_available($from_id) { //Проверка на ситауцию, когда пользователю доступна всего одна камера // (!) Важно при использовании функции в логике использовать сравнение === вместо == чтобы не путать значение false со значением $camera_id = 0
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
	
	if ((!empty($argv)) && ($argc > 1)) { //Проверяет, запущено ли приложение через CLI и имеет ли более 1 агрумента (т.к. 1 это имя скрипта, следовательно, запуск без аргументов будет иметь $argc = 1)
		writeLog("PROCESS", "BACKEND CALL: $argv[1](" . implode(", ", array_slice($argv, 2)) . ")");
		if (in_array($argv[1], BACKEND_FUNCTIONS_ALLOWED)) { //Аргумент[1] при запуске через CLI должен содержать имя функции //Проверяем, разрешен ли её запуск
			$argv[1](...array_slice($argv, 2)); //Запускает функцию имя которой передано через CLI, остальные аргументы передаёт в функцию
		} else {
			writeLog("ERROR", "FUNCTION $argv[1]() IS NOT IN BACKEND_FUNCTIONS_ALLOWED");
		}
	} else { //Если приложение запущено не через CLI, предполагаем что приложение запущено через HTTP(S)-запрос
		$update = getUpdate(HTTP_HEADER_TOKEN); //Рассматривает данные, полученные по HTTP(S)-запросу как Update-запрос Telegram API. Если параметр HTTP_HEADER_TOKEN задан, то он будет проверен (функция вернёт false при несовпадении и не позволит обработать запрос)
		if((!empty($update['message']['text'])) || (!empty($update['callback_query']['data']))) { //Если Update-запрос Telegram API содержит message или callback_query
			$update_type = !empty($update['message']['text']) ? "message" : "callback_query"; //Получает параметры из Update-запроса //тип запроса - сообщение или callback нажатия кнопки
			$from_id = $update[$update_type]['from']['id'];//ID отправителя сообщения или callback'а
			$first_name = $update[$update_type]['from']['first_name'];//имя отправителя сообщения или callback'а
			$chat_id = ($update_type == "message") ? $update[$update_type]['chat']['id'] : $update[$update_type]['message']['chat']['id'];//ID чата, в котором отправлено сообщение или callback (= $from_id если сообщение отправлено в ЛС боту)
			$line = !empty($update['message']['text']) ? trim(mb_strtolower($update[$update_type]['text'])) : trim($update[$update_type]['data']);//строка сообщения (приведена к строчным буквам и убраны лишние пробелы) или callback'а (только убраны лишние пробелы)
			writeLog("INBOX", "RECEIVED $update_type FROM $from_id $first_name IN CHAT $chat_id TEXT $line");
			if ((!OFFLINE_MODE) && (in_array($from_id, AUTHORIZED_ID))) { //Если приложение не отключено OFFLINE_MODE и пользователь имеет доступ к приложению  AUTHORIZED_ID
				if (!recognizeСommand($update_type, $from_id, $first_name, $chat_id, $line)) {//Пытается распознать команду используя функцию распознавания и сразу эе выполняет её в рамках функции
					sendMessage($chat_id, botDictonary("message_to_user_unknownword_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));//Если команда неизвестна сообщает пользователю об ошибке, без сообщения администратору
					writeLog("UNKNOWNWORD", "LINE $line NOT RECONIZED AS COMMAND, DETAILS: UPDATE_TYPE $update_type FROM $from_id $first_name IN CHAT $chat_id");
				}
			} else {//Если приложение отключено OFFLINE_MODE или пользователь не имеет доступ к приложению  AUTHORIZED_ID
				if (OFFLINE_MODE) { //Если OFFLINE_MODE //Cообщает пользователю об ошибке, если пользователь не администратор дополнительно сообщает администратору
					sendMessage($chat_id, botDictonary("message_to_user_offline_mode_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
					if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_offline_mode_text", SUPPORT_CONTACT[0], $first_name, "#" . getTag())); }
					writeLog("ACCESS", "OFFLINE_MODE: $from_id $first_name TRY TO ACCESS TO BOT WHEN APP IN OFFLINE_MODE");
				} else if (!in_array($from_id, AUTHORIZED_ID)) { //Аналогичный сценарий, если пользователь не AUTHORIZED_ID
					sendMessage($chat_id, botDictonary("message_to_user_not_auth_text", SUPPORT_CONTACT[1], SUPPORT_CONTACT[2]));
					if ($from_id != ADMIN_CHAT_ID) { sendMessage(ADMIN_CHAT_ID, botDictonary("message_to_admin_not_auth_text", SUPPORT_CONTACT[0], $from_id, $first_name, "#" . getTag())); }
					writeLog("ACCESS", "UNAUTHORIZED: $from_id $first_name TRY TO ACCESS TO BOT, USER IS NOT IN AUTHORIZED_ID");
				}
			}
		} else {//Если запрос пустой или содержит неподдерживаемый (не message или callback_query) тип Update-запроса
			writeLog("ERROR", "UNSUPPORTED UPDATE TYPE OR EMPTY REQUEST");//Если не пройдена проверка HTTP_HEADER_TOKEN то в данном контесте запрос также будет считаться пустым, т.к. getUpdate() вернёт false вместо тела непроверенного запроса.
		}
	}
?>