<?php
/**
 * CacheAutomated -- A Custom CDN for Web Server Speed Up
 * Copyright <YEAR> <COPYRIGHT HOLDER>

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in all copies
 * or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
 * FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * By 晴天QAQ
 * 功能：利用GitHub账户实现CDN加速
 * 使用条件：必须在你的GitHUb账户申请一个Auth Key，必须有创建库和删库的权限（方便自动管理），你可以申请一个新的GitHub账户
 * PHP环境：必须带有pcntl扩展，并且支持proc_*系列函数
 * 使用指引：上传到你的网站资源文件夹，访问index.php,会生成.cdn/config.json,在里面填入你的GitHUb账户名、Repo库名（你用来作为CDN的库名）、Auth-Key（申请至GitHUb）
 * 然后，切换到www用户，ssh-keygen生成一个SSH公钥，并配置到GitHub
 * 在你的index.php同目录新建一个.htaccess(以Apache2服务器为例），同时确保你的服务器已经打开了rewrite模块支持，
 * .htaccess的内容为（不包括*号）
 * RewriteEngine on
 * RewriteCond %{REQUEST_FILENAME} !index.php
 * RewriteCond %{REQUEST_FILENAME} !test.php
 * RewriteRule ^ index.php [QSA,L]
 * 最后，在www用户打开php index.php daemon启动守护进程（用于监视资源文件的更新及完成于GitHub的自动同步）。若发现没有效果/网站出错，请查看.cdn/debug.log查找出错原因。
 * @author Nathanli
 */

$GLOBALS['debug'] = true;
require_once 'func.php';
//Entry
if(!isset($argc))
{
    // access from web
    $doc_root=substr($_SERVER['PHP_SELF'],0,strlen($_SERVER['PHP_SELF'])-9);
    $require_file_uri=substr($_SERVER['REQUEST_URI'],strlen($doc_root));

    $type="application/octet-stream";

    if($require_file_uri == "/" || $require_file_uri == "index.php" || $require_file_uri == '')
    {
        header("HTTP/1.1 403 Forbidden");
        exit(0);
    }

    $file_ext=explode(".",$require_file_uri);

    if(is_array($file_ext) && sizeof($file_ext)>0)
    {
        $file_ext=$file_ext[sizeof($file_ext)-1];
        if(array_key_exists($file_ext,mime))
            $type = mime[$file_ext];
    }

    header("Content-Type: $type");

    if(!is_dir('.cdn'))
        mkdir('.cdn');

    if(!is_file('.cdn/config.json'))
    {
        if(check_function(array(
            'proc_open',
            'fgets',
            'feof',
            'fclose',
            'proc_close',
            'curl_init',
            'curl_setopt',
            'curl_exec',
            'curl_getinfo',
            'file_exists',
            'mkdir',
            'unlink'
        )))
            logger("All required functions check passed",'INFO','Installer');
        else
            show_itself($require_file_uri);
        $default_config = array(
            'users'=>array(
                array(
                    'user'=>'',
                    'repo'=>'',
                    'token'=>'',
                    'email'=>''
                )
            ),
            //Total space:max_repos_per_user*50M,For example,100*50MB=5GB
            'max_repos_per_user'=>100,
            'debug'=>true
        );
        $ret = @file_put_contents('.cdn/config.json',json_encode($default_config,JSON_PRETTY_PRINT));
        $GLOBALS['debug'] = true;
        if($ret)
            logger("Config file was created,please modify the config file.(.cdn/config.json)",'INFO',"Installer");
        else
            logger("Config file can not be created.(.cdn/config.json)",'FATAL',"Installer");
    }

    $GLOBALS['settings'] = @file_get_contents(".cdn/config.json");

    if(!is_string($GLOBALS['settings']) || !is_array($GLOBALS['settings'] = @json_decode($GLOBALS['settings'],true)))
        show_itself($require_file_uri);

    if(!array_key_exists('user',$GLOBALS['settings']) || !array_key_exists('repo',$GLOBALS['settings']) || !array_key_exists('token',$GLOBALS['settings']))
        show_itself($require_file_uri);

    if($GLOBALS['settings']['user'] == '' || $GLOBALS['settings']['repo'] == '' || $GLOBALS['settings']['token'] == '')
        show_itself($require_file_uri);

    $user = $GLOBALS['settings']['user'];
    $repo = $GLOBALS['settings']['repo'];

    if(array_key_exists('debug',$GLOBALS['settings']) && $GLOBALS['settings']['debug'] == true)
        $GLOBALS['debug'] = true;

    if(!is_daemon_running())
    {
        logger('daemon are not running.Trying to startup daemon','WARN','Web');
        show_itself($require_file_uri);
    }

    if(!file_exists(".cdn/md5sum/".$require_file_uri) || md5_file($require_file_uri) != file_get_contents(".cdn/md5sum/".$require_file_uri))
    {
        if(is_dir(".cdn") && file_exists($require_file_uri))
            file_put_contents(".cdn/need_recommit","NEED",LOCK_EX);
        show_itself($require_file_uri);
    }else
    {
        $lasttag = file_get_contents(".cdn/lasttag");
        header("Location: https://cdn.jsdelivr.net/gh/$user/$repo@$lasttag/$require_file_uri");
    }
}else
{

    //Daemon process
    if($argc <= 1)
    {
        echo "Argument missing";
        exit(-1);
    }
    if($argv[1]=="daemon")
    {
        @unlink(".cdn/cache_ok");
        $GLOBALS['status'] = 2;

        pcntl_signal(SIGHUP,  function($signo) {
            logger("Recv SIGHUP.Try to reloading...");

            $GLOBALS['settings'] = @file_get_contents(".cdn/config.json");

            if(!is_string($GLOBALS['settings']) || !is_array($GLOBALS['settings'] = @json_decode($GLOBALS['settings'],true)))
            {
                logger("Missing config file,killed\n");
                exit(1);
            }
            $GLOBALS['status'] = 2;
        });

        $GLOBALS['settings'] = @file_get_contents(".cdn/config.json");

        if(!is_string($GLOBALS['settings']) || !is_array($GLOBALS['settings'] = @json_decode($GLOBALS['settings'],true)))
        {
            echo("Missing config file,killed");
            exit(1);
        }

        @file_put_contents(".cdn/daemon_running",getmypid(),LOCK_EX);

        $tmp = fopen(".cdn/daemon_running", 'rb');

        logger("Daemon are running");


        $retry = 1;

        while($GLOBALS['status'])
        {
            pcntl_signal_dispatch();
            if($GLOBALS['status'] == 2)
            {

                while(!init_git())
                {
                    pcntl_signal_dispatch();
                    logger("failed to initialize your Repository. Retry in $retry seconds...",'ERROR');
                    sleep($retry);
                    if($retry>=120)
                        $retry *=2;
                }
                $GLOBALS['status'] = 1;
                @file_put_contents(".cdn/need_recommit","NEED",LOCK_EX);
            }

            if(@file_get_contents(".cdn/need_recommit")!=false)
            {
                $retry = 1;
                while(!update_git())
                {
                    logger("failed to update your Repository. Retry in $retry seconds...",'ERROR');
                    sleep($retry);
                    if($retry>=120)
                        $retry *=2;
                }
                unlink(".cdn/need_recommit");
                @file_put_contents(".cdn/cache_ok","OK",LOCK_EX);
            }
            sleep(1);
        }
    }
}
?>