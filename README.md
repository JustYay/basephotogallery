ErrorDocument 403 /index.php
ErrorDocument 404 /index.php

# Разрешаем доступ к каталогам через наш скрипт
Options -Indexes +FollowSymLinks
DirectoryIndex index.php

# Основные настройки mod_rewrite
RewriteEngine On
RewriteBase /

# Разрешаем доступ всем
<FilesMatch "^(index\.php)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Обработка 403/404 ошибок через index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L] 
