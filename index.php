<?php
/**
 * CacheAutomated -- A Custom CDN for Web Server Speed Up
 * @author Nathanli
 */

$GLOBALS['debug'] = true;

const mime = array (
    'doc'  => 'application/vnd.ms-word',
    'xls'  => 'application/vnd.ms-excel',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pps'  => 'application/vnd.ms-powerpoint',
    'pdf'  => 'application/pdf',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'odt'  => 'application/vnd.oasis.opendocument.text',
    'swf'  => 'application/x-shockwave-flash',
    'gz'  => 'application/x-gzip',
    'tgz'  => 'application/x-gzip',
    'bz'  => 'application/x-bzip2',
    'bz2'  => 'application/x-bzip2',
    'tbz'  => 'application/x-bzip2',
    'zip'  => 'application/zip',
    'rar'  => 'application/x-rar',
    'tar'  => 'application/x-tar',
    '7z'  => 'application/x-7z-compressed',
    'txt'  => 'text/plain',
    'php'  => 'text/x-php',
    'html' => 'text/html',
    'htm'  => 'text/html',
    'js'  => 'text/javascript',
    'css'  => 'text/css',
    'rtf'  => 'text/rtf',
    'rtfd' => 'text/rtfd',
    'py'  => 'text/x-python',
    'java' => 'text/x-java-source',
    'rb'  => 'text/x-ruby',
    'sh'  => 'text/x-shellscript',
    'pl'  => 'text/x-perl',
    'sql'  => 'text/x-sql',
    'bmp'  => 'image/x-ms-bmp',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'png'  => 'image/png',
    'tif'  => 'image/tiff',
    'tiff' => 'image/tiff',
    'tga'  => 'image/x-targa',
    'psd'  => 'image/vnd.adobe.photoshop',
    'mp3'  => 'audio/mpeg',
    'mid'  => 'audio/midi',
    'ogg'  => 'audio/ogg',
    'mp4a' => 'audio/mp4',
    'wav'  => 'audio/wav',
    'wma'  => 'audio/x-ms-wma',
    'avi'  => 'video/x-msvideo',
    'dv'  => 'video/x-dv',
    'mp4'  => 'video/mp4',
    'mpeg' => 'video/mpeg',
    'mpg'  => 'video/mpeg',
    'mov'  => 'video/quicktime',
    'wm'  => 'video/x-ms-wmv',
    'flv'  => 'video/x-flv',
    'mkv'  => 'video/x-matroska'
);
function logger(string $msg,string $level='INFO',string $from='daemon')
{
    if(!@isset($GLOBALS['settings']['debug']) || $GLOBALS['settings']['debug'] == false)
        return;
    $dt = date('Y-m-d H:i:s');
    @file_put_contents(".cdn/debug.log","[$dt][$from][$level] $msg"."\n",FILE_APPEND);
}

/**
 * Start a process and waiting for it finished
 * @param string $cmd
 * @param string $stdout
 * @param string $stderr
 * @param string $stdin
 * @return int
 */
function exec_wait(string $cmd,string &$stdout=null,string &$stderr=null,string $stdin="")
{
    $descriptorspec = array(
        0 => array('pipe','r'), //stdin
        1 => array('pipe','w'), //stdout
        2=>array('pipe','w')    //stderr
    );

    $process = proc_open($cmd,$descriptorspec,$pipes,getcwd(),null);

    if(is_resource($process))
    {

        while (true)
        {
            $output = fgets($pipes[1],1024);
            $errout = fgets($pipes[2],1024);
            if($stdout == '' || !is_null($stdout))
            {
                $stdout .= $output;
            }

            if($stderr == '' || !is_null($stderr))
            {
                $stderr .= $errout;
            }
            //done
            if(($output == false || feof($pipes[1])) && ($errout == false || feof($pipes[2])))
            {
                break;
            }
        }
    }

    if($stdin != "")
    {
        sleep(3);
        fputs($pipes[0],$stdin);
        fflush($pipes[0]);
        sleep(3);
    }

    if(!feof($pipes[0]))
        fclose($pipes[0]);

    $ret = proc_close($process);

    if($ret != 0)
        logger("Can not execute $cmd What:$stderr",'WARN');

    return $ret;
}

/**
 * Getting respond from server after we send a POST request to it.
 * @param $url
 * @param null $post
 * @param null $header
 * @param string $method
 * @param string $result
 * @return bool|string
 */
function curl_https($url,$post=null,$header=null,$method='GET',string &$result=null){

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    if($method == 'POST')
    {
        curl_setopt($curl,CURLOPT_POST,1);
        curl_setopt($curl,CURLOPT_POSTFIELDS,$post);
    }

    if($header != null)
    {
        $header[]='User-Agent: curl/7.68.0';
        curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
    }
    else
        curl_setopt($curl,CURLOPT_HTTPHEADER,array('User-Agent: curl/7.68.0'));

    if($method != 'GET')
    {
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,$method);
    }

    $tmpInfo = curl_exec($curl);

    if($tmpInfo == false && $tmpInfo!= '')
    {
        logger("curl error:".curl_error($curl));
        return false;
    }
    else if($result == '')
        $result = $tmpInfo;

    $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);

    return $httpCode;
}
/**
 * Checks the process is exists specified by $pid
 * @param $pid
 * @return bool
 */
function is_process_exists($pid)
{
    exec_wait("/bin/ps -axo pid",$res);
    preg_match("~$pid~",$res,$res);
    if(is_array($res) && sizeof($res)==1) {
        return true;
    }
    else
        return false;
}

/**
 * Display the specified resources when it was found ,or 404 information when it not found
 * @param string $file
 */

function show_itself(string $file){
    if(file_exists($file) && is_file($file))
        echo file_get_contents($file);
    else
        header("HTTP/1.1 404 Not Found");
    exit(0);
}

/**
 * Checks the daemon is running
 * @return bool
 */

function is_daemon_running()
{
    return file_exists(".cdn/daemon_running") && is_process_exists(file_get_contents(".cdn/daemon_running") && file_exists(".cdn/cache_ok"));
}

/**
 * Check the specified function exists
 * @param array $fun
 * @return bool
 */
function check_function(array $fun)
{
    foreach ($fun as $name)
    {
        if(function_exists($name))
            logger("function $name check passed",'INFO','Installer');
        else
        {
            logger("function $name check failed",'FATAL','Installer');
            return false;
        }
    }
    return true;
}

/**
 * Initializing Git Repository
 * @return bool
 */
function init_git()
{
    logger('Initializing Git Repository...');
    if(
        !array_key_exists('user',$GLOBALS['settings']) ||
        !array_key_exists('repo',$GLOBALS['settings']) ||
        !array_key_exists('token',$GLOBALS['settings']) ||
        !array_key_exists('email',$GLOBALS['settings'])
    )
    {
        logger("wrong config file!",'ERROR');
        return false;
    }
    $user = $GLOBALS['settings']['user'];
    $repo = $GLOBALS['settings']['repo'];
    $token = $GLOBALS['settings']['token'];
    $email = $GLOBALS['settings']['email'];

    $auth = array("Authorization: token $token");
    logger("Auth: ".$auth[0]);
    //delete old repo
    $res = curl_https("https://api.github.com/repos/$user/$repo",null,$auth,'DELETE',$retinfo);
    if($res == false || $res != "204") {
        logger("could not delete old repository:$res:$retinfo",'WARN');
    }

    //create a new repo
    $res = curl_https("https://api.github.com/user/repos",json_encode(array(
        "name"=>$repo,
        "description"=>"Repository for CDN using",
        "private"=>false
    )),$auth,'POST',$retinfo);

    if($res == false || $res != "201") {
        logger("could not create a new repository:$res:$retinfo",'ERROR');
        return false;
    }

    exec_wait("rm -rf .git");

    if(is_dir(".git"))
    {
        logger("Cannot delete a old local Git Repository.");
        return false;
    }

    if(exec_wait("git init")!=0)
        return false;

    if(exec_wait("git config user.name $user")!=0)
        return false;

    if(exec_wait("git config user.email $email") != 0)
        return false;

    if(exec_wait("git remote add origin git@github.com:$user/$repo.git") != 0)
        return false;

    if(is_dir(".git"))
    {
        logger("Git Repository was initialized successfully!");
        return true;
    }else
    {
        logger("Git Repository was failed to initialize!Please check your file permissions",'FATAL');
        return true;
    }
}

/**
 * Updating Git Repository
 * @return bool
 */
function update_git()
{
    logger("Updating your Git Repository...");

    if(exec_wait("git add .") != 0)
        return false;

    if(exec_wait("git status",$stat) != 0)
        return false;

    $tag = time();

    $lasttag=@file_get_contents(".cdn/lasttag");


    if(exec_wait("git commit -m"."TIME-".$tag,$out,$err) != 0)
        return false;

    if(exec_wait("git tag ".$tag) != 0)
        return false;

    if(exec_wait("GIT_SSH_COMMAND=\"ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no\" git push origin master $tag",$out2,$err,"yes\n") != 0)
    {
        logger($out2);
        return false;
    }

    preg_match_all("~(?<=new file:|modified:|deleted:).*~",$stat,$stat);
    if(isset($stat[0]) && is_array($stat[0]))
    {
        foreach ($stat[0] as $item)
        {
            $item=preg_replace("~\s~","",$item);
            if(!file_exists($item))
            {
                unlink(".cdn/md5sum/$item");
                continue;
            }
            $parts = explode('/', ".cdn/md5sum/$item");
            $dir = '.';
            for($i=0;$i<sizeof($parts)-1;$i++) {
                $dir.="/".$parts[$i];
                if(!is_dir($dir))
                    mkdir($dir);
            }
            file_put_contents(".cdn/md5sum/$item",md5_file($item),LOCK_EX);
        }
    }
    if($lasttag!=false)
        exec_wait("git tag -d $lasttag");

    @file_put_contents(".cdn/lasttag",$tag,LOCK_EX);
    logger("Your Git Repository was updated successfully!");
    return true;
}

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
            'unlink',
            'pcntl_signal',
            'pcntl_signal_dispatch'
        )))
            logger("All required functions check passed",'INFO','Installer');
        else
            show_itself($require_file_uri);
        $default_config = array(
            'user'=>'',
            'repo'=>'',
            'token'=>'',
            'email'=>'',
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