FROM ubuntu:18.04
EXPOSE 80
RUN ln -snf /usr/share/zoneinfo/Europe/Moscow /etc/localtime && echo "Europe/Moscow" > /etc/timezone && \
	apt update -y && apt install -y nginx php php-fpm php-curl php-mbstring ffmpeg iputils-ping git cron && \
	ln -sf /dev/stdout /var/log/nginx/access.log && ln -sf /dev/stderr /var/log/nginx/error.log && \
	rm -rf /var/lib/apt/lists/*  && \
	echo "daemon off;" >> /etc/nginx/nginx.conf
ENV PARK_PHP_VER=7.2
ENV PARK_URL_PATH=parkovka_kolyana
ENV PARK_APP_BRANCH=main
ENV PARK_CONFIG_BRANCH=prod
ENV PARK_CRON_SET=1 * * * *
#ENV PARK_HOSTNAME=$(cat /etc/hostname)
#ENV PARK_CONFIG_TOKEN=git_GIT_git
CMD echo "${PARK_HOSTNAME}_$RANDOM_container" > /etc/hostname &&\
	mkdir -p /var/www/html/${PARK_URL_PATH} && cd /var/www/html/${PARK_URL_PATH}  &&\
	git clone https://github.com/skl256/nikolay_webcam_api.git && mv ./nikolay_webcam_api/* ./ && rm -Rf ./nikolay_webcam_api &&\
	git clone https://github.com/skl256/nikolay_telegram_api.git && mv ./nikolay_telegram_api/* ./ && rm -Rf ./nikolay_telegram_api  &&\
	git clone --branch ${PARK_APP_BRANCH} https://github.com/skl256/parkovka_kolyana.git && mv ./parkovka_kolyana/* ./ && rm -Rf ./parkovka_kolyana  &&\
	git clone --branch ${PARK_CONFIG_BRANCH} https://skl256:${PARK_CONFIG_TOKEN}@github.com/skl256/parkovka_kolyana_config.git && mv ./parkovka_kolyana_config/* ./ && rm -Rf ./parkovka_kolyana_config &&\
	echo 'server { listen 80 default_server; server_name _; root /var/www/html; location / { try_files $uri $uri/ =404; index app_parkovka_kolyana.php; } location ~ [^/]\.php(/|$) { fastcgi_split_path_info ^(.+?\.php)(/.*)$; if (!-f $document_root$fastcgi_script_name) { return 404; } include snippets/fastcgi-php.conf; fastcgi_pass unix:/var/run/php/php'${PARK_PHP_VER}'-fpm.sock; } }' > /etc/nginx/sites-available/default &&\
	echo "${PARK_CRON_SET}"' www-data cd /var/www/html/'${PARK_URL_PATH}' && php -f app_parkovka_kolyana.php app_scheduler' > /etc/crontab &&\
	service php${PARK_PHP_VER}-fpm start && php -f app_parkovka_kolyana.php app_init && chown -R www-data /var/www/html/${PARK_URL_PATH} && cron && nginx
