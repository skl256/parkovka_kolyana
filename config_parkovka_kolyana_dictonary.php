<?php
	//config_parkovka_kolyana_dictonary.php
	define("BOT_DICTONARY", array(//Массив со словарём бота, %s заменяется переданной переменной. (!)Внимание, критично: при передаче кол-ва переменных меньшего, чем кол-во %s бот отвечает молчанием.
		"default" => array( //Ответ бота при неверном значении ключа словаря
			"У меня закончился словарный запас \xF0\x9F\x98\xB6"
		),
		"command_start_text" => array(
			"Привет \xE2\x9C\x8B\n\n\xE2\x9C\x85 Чтобы получить изображение - нужно выбрать камеру из меню бота, из списка ниже, или, просто написать в чат название камеры, как удобнее.\n\n%s%s\n\xF0\x9F\x91\x86 Чтобы снова показать эту справку и актуальный список камер - команда /start.\n\n\xF0\x9F\x93\xA7 Если будут вопросы - пиши %s %s %s",
			"Инструкция \xF0\x9F\x98\xBA\n\n\xE2\x9C\x85 Посмотреть картинку можно разными способами - написать в чат команду или имя камеры, выбрать камеру из меню или из списка камер (который ниже).\n\n%s%s\n\xF0\x9F\x91\x86 Актуальный список камер и эта инструкция всегда доступны по команде /start.\n\n\xF0\x9F\x93\xA7 Если что то не получается - пиши %s %s %s"
		),
		"command_start_all_cameras_add_text" => array(
			"\n\xF0\x9F\x92\xA1 Можно посмотреть картинки сразу со всех камер командой /all_cameras.\n<i>* Обрати внимание, что камеры могут иметь дополнительные функции (показывать видео или альбом с историей), эти функции можно увидеть только при просмотре одной камеры, при просмотре всех камер одной командой дополнительные функции не показываются.</i>\n",
			"\n\xF0\x9F\x92\xA1 Чтобы посмотеть картинку сразу со всех - есть команда /all_cameras.\n<i>* Камеры могут уметь записывать видеоклипы или показывать альбом с историей за последнее время, но это можно посмотреть на каждой камере отдельно, при просмотре всех камер одной командой дополнительные функции не показываются.</i>\n"
		),
		"app_init_text" => array(
			"\xF0\x9F\x86\x99 Приложение запущено на #%s\n\n\xF0\x9F\x93\xB9 Камеры:\n%s\n\xF0\x9F\x94\xA7Опции:\n%s",
			"\xF0\x9F\x86\x99 Задеплоил на #%s\n\n\xF0\x9F\x93\xB9 Камеры:\n%s\n\xF0\x9F\x94\xA7Опции:\n%s"
		),
		"app_start_long_polling_text" => array(
			"\xE2\x8F\xA9 Режим long polling запущен на #%s\n"
		),
		"app_stop_long_polling_text" => array(
			"\xE2\xAC\x9B Режим long polling остановлен на #%s\n"
		),
		"wait_message_text" => array(
			"Сейчас сниму и пришлю... \xF0\x9F\x98\xB8",
			"Подожди, пошёл за камерой... \xF0\x9F\x99\x8C",
			"Пол минутки... \xF0\x9F\x99\x86",
			"Надо чуть подождать... \xF0\x9F\x99\x80",
			"Настраиваю фотоаппарат, момент \xF0\x9F\x99\x8C",
			"Подожди пожалуйста... \xF0\x9F\x99\x86",
			"В процессе \xF0\x9F\x99\x80"
		),
		"photo_sent_message_text" => array(
			"Готово \xF0\x9F\x98\xBC",
			"Снял \xF0\x9F\x98\xBD",
			"Вот \xF0\x9F\x91\x86",
			"\xF0\x9F\x91\x8C"
		),
		"last_photo_sent_message_text" => array(
			"Сейчас не получилось связаться с камерой \xF0\x9F\x98\x94\nНО! Вот последняя фотография которая была сделана %s \xF0\x9F\x92\xBE",
			"Нет сигнала \xF0\x9F\x93\xB4\nПока могу прислать старую картинку, сфоткал %s \xF0\x9F\x92\xBE"
		),
		"button_take_video_text" => array(
			"Записать %d секунд видео \xF0\x9F\x95\x90"
		),
		"button_get_album_text" => array(
			"Показать альбом камеры \xF0\x9F\x92\xBD"
		),
		"button_confirm_mailing_text" => array(
			"Переслать это сообщение всем \xF0\x9F\x93\xA8"
		),
		"button_get_message_copy_text" => array(
			"Получить копию сообщения \xF0\x9F\x93\xA9"
		),
		"message_to_admin_user_command_success_text" => array(
			"\xF0\x9F\x91\x8D %s, %s попросил(-а) отправить %s с %s, и я всё отправил \xF0\x9F\x98\xBB"
		),
		"message_to_admin_user_command_error_text" => array(
			"\xF0\x9F\x9A\xA8 %s, проблемы! %s попросил(-а) отправить %s с %s, но у меня не получилось \xF0\x9F\x98\xA2 Подробнее можно узнать в /log"
		),
		"message_to_admin_user_command_warn_text" => array(
			"\xF0\x9F\x9A\xA8 %s, проблемы! %s попросил(-а) отправить %s с %s, я отправил но не всё, %s не получилось \xF0\x9F\x98\xA2 Подробнее можно узнать в /log"
		),
		"message_to_user_user_command_error_try_again_text" => array(
			"Почему-то не получилось \xF0\x9F\x98\xA2 ... но можно попробовать еще раз",
			"Что-то сломалось похоже, но можно ещё раз попробовать \xF0\x9F\x98\xAD",
			"Не получилось \xF0\x9F\x92\xA9",
			"Какая-то шляпа \xF0\x9F\x8E\xA9"
		),
		"message_to_user_offline_or_access_denied_text" => array(
			"Эта камера сейчас отключена или не недоступна \xF0\x9F\x98\xA3 По вопросам можно написать %s %s \xF0\x9F\x94\xA8",
			"А тут что-то должно быть? \xF0\x9F\x98\xAD %s %s напиши, он посмотрит... \xF0\x9F\x94\xA9"
		),
		"message_to_admin_offline_or_access_denied_text" => array(
			"\xF0\x9F\x9A\xA8 %s, внимание! %s %s попросил(-а) отправить %s с %s, но эта камера либо отключена либо у пользователя нет к ней доступа \xF0\x9F\x91\xAE"
		),
		"message_to_user_unknownword_text" => array(
			"Я не знаю таких команд, напиши /start чтобы получить инструкции \xF0\x9F\x99\x89 \nА если это ошибка или ты хочешь чтобы эта команда тоже работала - напиши %s %s",
			"Инструкцию можно посмотреть по команде /start, я не понимаю этой команды \xF0\x9F\x99\x89 \nА если это ошибка или ты хочешь чтобы эта команда тоже работала - напиши %s %s"
		),
		"message_to_user_offline_mode_text" => array(
			"Всё сломано и ничего не работает \xF0\x9F\x98\xAD %s %s напиши, он скажет что случилось... \xF0\x9F\x94\xA9",
			"Всё выключено, или всё сломалось, я не знаю... \xF0\x9F\x98\xA3 Напиши %s, может что то сделает? Вот куда писать %s \xF0\x9F\x94\xA8"
		),
		"message_to_admin_offline_mode_text" => array(
			"\xF0\x9F\x9A\xA8 %s, нам написал(-а) %s, а мы в OFFLINE_MODE \xF0\x9F\x94\xA7 %s"
		),
		"message_to_user_not_auth_text" => array(
			"Напиши %s %s для того, чтобы получить доступ сюда \xF0\x9F\x98\x89"
		),
		"message_to_admin_not_auth_text" => array(
			"\xF0\x9F\x9A\xA8 %s, нам написал(-а) %s %s, может он(-а) тоже хочет смотреть фото с камеры \xF0\x9F\x98\x8F %s"
		),
		"label_album_shown_images_x_of_y_text" => array(
			"\xF0\x9F\x93\x82 Показано снимков %d из %d"
		),
		"button_album_show_more_text" => array(
			"Показать ещё \xE2\x8F\xA9",
			"Надо ещё \xE2\x8F\xA9",
			"Больше \xE2\x8F\xA9",
			"Дальше \xE2\x8F\xA9",
			"Давай ещё \xE2\x8F\xA9"
		),
		"label_album_no_more_text" => array(
			"Больше нет \xF0\x9F\x99\x88",
			"Закончились \xF0\x9F\x99\x88",
			"Тут всё \xF0\x9F\x99\x88"
		),
		"message_to_user_album_no_images_text" => array(
			"Я не нашёл снимков \xF0\x9F\x99\x88",
			"Пусто \xF0\x9F\x99\x88"
		),
		"message_to_admin_error_get_logs_text" => array(
			"\xF0\x9F\x9A\xA8 %s, на #%s не найдено логов, но такого же не может быть? Наверное стоит посмотреть по старинке \xF0\x9F\x98\xA3"
		),
		"message_to_user_unauth_to_logs_text" => array(
			"Логи нужны системному администратору, там нет ничего интересного \xF0\x9F\x98\x89",
			"А зачем такая информация, это никому нельзя показывать \xF0\x9F\x98\xBC"
		),
		"message_to_admin_unauth_to_logs_text" => array(
			"\xF0\x9F\x9A\xA8 %s, нам написал(-а) %s, с просьбой показать логи, но я ничего никому не покажу \xF0\x9F\x98\x8F"
		),
		"menu_button_command_start_text" => array(
			"Меню и список камер"
		),
		"menu_button_command_all_cameras_text" => array(
			"Картинка сразу со всех камер"
		),
		"menu_button_command_camera_i_text" => array(
			"Камера %s"
		),
		"message_to_admin_task_history_error_text" => array(
			"\xE2\x9A\xA0 %s, нам не удалось получить изображение с /camera%s для альбома (подробнее - в /log)"
		),
		"message_to_admin_task_rec_error_text" => array(
			"\xE2\x9A\xA0 %s, нам не удалось выполнить запланированную запись видео с /camera%s (ошибка на %s сек., подробнее - в /log)"
		),
		"message_to_admin_mailing_done_text" => array(
			"\xF0\x9F\x93\xA8 %s, я переслал это сообщение (отправлено сообщений %d, всего авторизированных пользователей %d)"
		)
	));
?>