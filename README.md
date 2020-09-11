# cacheautomated

利用GitHub和jsdelivr自动加速你的网站

本脚本可利用jsdelivr提供的CDN服务自动加速你的网站。

使用条件：必须在你的GitHUb账户申请一个Auth Key，必须有创建库和删库的权限（方便自动管理），建议申请一个新的GitHub账户

PHP环境：必须带有pcntl扩展，并且支持proc_*系列函数

使用指引：上传到你的网站资源文件夹，访问index.php,会生成.cdn/config.json,在里面填入你的GitHUb账户名、Repo库名（你用来作为CDN的库名）、Auth-Key（申请至GitHUb）

然后，切换到www用户，ssh-keygen生成一个SSH公钥，并配置到GitHub

在你的index.php同目录新建一个.htaccess(以Apache2服务器为例），同时确保你的服务器已经打开了rewrite模块支持，

.htaccess的内容为（不包括*号）
```
RewriteEngine on

RewriteCond %{REQUEST_FILENAME} !index.php

RewriteCond %{REQUEST_FILENAME} !test.php

RewriteRule ^ index.php [QSA,L]
```

然后，新建.gitignore文件，内容为你不想被缓存的文件：
```
.*
*.php
*.asp
*.phar
.cdn/
index.php
```
最后，在www用户打开php index.php daemon启动守护进程（用于监视资源文件的更新及完成于GitHub的自动同步）。若发现没有效果/网站出错，请查看.cdn/debug.log查找出错原因。

(建议写到systemd service或用screen，扔到后台运行，注意：请以www或www-data用户运行)

加速效果演示网址：~~[七猫论坛]~~  (此论坛因经营不善已倒闭)

加速前：

![pre.png](https://i.loli.net/2020/07/28/m7PZeJA2TYcrlko.png)

加速后：

![post.png](https://i.loli.net/2020/07/28/XqzFQolmA39jWrP.png)
