<?php
/**
 *  Open Library
 *
 *  @author Mitchell Tuck aka Matuck matuck@matuck.com
 *  @link https://github.com/matuck/MySQL-Replication-Monitor
 *  @copyright Copyright (c) 2011, matuck
 *  @license Apache v2.0 see license.txt in install folder
 *
 *  See the README on how to configure
 */
/**
 * Copyright 2011 Mitchell Tuck aka matuck
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
 
#Resets warning messages
error_reporting(E_ALL ^ E_NOTICE);

/**
 * Define users who can access this page.
 */
//$user['username'] = 'an md5 password hash';

/**
 * Setup the email address cron messages will come from.
 */
$from_email = 'someone@somewhere.com'; //the email address you want the messages to come from
/**
 * Define your servers in below array to create a new server just add a new $server[servername] with all the below keys
 */
$server['servername']['host'] = "192.168.0.2"; //the hostname or ip address of the server
$server['servername']['port'] = 3306;  //optional the mysql port default to 3306
$server['servername']['username'] = "username";  //username to connect with.
$server['servername']['password'] = "password"; //password to connect with.
$server['servername']['email'] = 'someone@somewhere.com'; //email address to send messages to.  to add another put a , and type next address .

/********************************************************************************
 *      Stop Here Unless You Know What You Are Doing !!!!!!!!!!!!!!!!!!!!!!     *
 ********************************************************************************/
 
$from_email = 'From: '.$from_email.'' . "\r\n";
/**
 * Get the slave status of a server
 * @param $host string ip address or hostname of the mysql server
 * @param $username string the username to connect to the server
 * @param $password string the password to connect to the server
 * @param $port int the port number to connect to.  defaults to 3306 optional
 * @return array with variables.  or failes on failed to connect
 */
function slavestatus($host, $username, $password, $port = 3306)  {
  if(!mysql_connect("$host:$port",$username,$password))  {
    return FALSE;
  }
  $result = mysql_query("show slave status");
  $timeResult = mysql_query("select now()");
  $slave_stat['time'] = mysql_result($timeResult,0,0);
  $slave_stat['host'] = $host;
  $slave_stat['port'] = $port;
  while($status = mysql_fetch_array($result))  {
    $slave_stat['file'] = $status[5];
    $slave_stat['position'] = $status[6];
    $slave_stat['sql_run'] = $status[10];
    $slave_stat['io_run'] = $status[11];
    $slave_stat['errorNum'] = $status[18];
    $slave_stat['errorMeg'] = $status[19];
  }
  return $slave_stat;
}


/**
 * To stop or start the slave status
 * @param $host string ip address or hostname of the mysql server
 * @param $username string the username to connect to the server
 * @param $password string the password to connect to the server
 * @param $task string has to be start or stop everything else will fail.
 * @param $port int the port number to connect to.  defaults to 3306 optional
 * @return string or False on fail connect or no return and refreshes page.
 */
function start_stop($host, $username, $password, $task, $port = 3306)  {
  if($task == 'start' || $task == 'stop' || $task == 'START' || $task == 'STOP' || $task == 'Start' || $task == 'Stop')  {
    if(!mysql_connect("$host:$port",$username,$password))  {
      return FALSE;
    }
    $sql = $task . " slave";
    $result = mysql_query($sql);
    unset($task);
	sleep(2);
  }
  else  {
    return "No task specified";
  }
}

/**
 * Showing detailed status of server
 * @param $host string ip address or hostname of the mysql server
 * @param $username string the username to connect to the server
 * @param $password string the password to connect to the server
 * @param $port int the port number to connect to.  defaults to 3306 optional
 * @return arry with the values or FALSE on fail connect
 */
function get_status($host, $username, $password, $port)  {
  $hostname = $_SERVER['SCRIPT_NAME'];
  if(!mysql_connect("$host:$port",$username,$password))  {
    return FALSE;
  }
  $sql = "show global status";
  $res = mysql_query($sql);
  while($row = mysql_fetch_assoc($res))  {
	$values[$row['Variable_name']] = $row['Value'];
  }
  return $values;
}
// end of functions
if (isset($_POST['start'])) {
  list($task, $servername)=split(" ",$_POST['start']);
  start_stop($server[$servername]['host'], $server[$servername]['username'], $server[$servername]['password'], $task, $server[$servername]['port']);
}
if (isset($_POST['stop'])) {
  list($task, $servername)=split(" ",$_POST['stop']);
  start_stop($server[$servername]['host'], $server[$servername]['username'], $server[$servername]['password'], $task, $server[$servername]['port']);
}
if(isset($_POST['LOGIN']))  {
  if($user[$_POST['txtUsername']] == md5($_POST['txtPassword']))  {
    //setcookie
	setcookie("mysql_rep", $_POST['txtUsername'].':'.md5($_POST['txtUsername'].$user[$_POST['txtUsername']]), time()+3600); /* expire in 1 hour */
  }
  else  {
    $loginerror = 'The username or password entered is incorrect';
  }
}

foreach($server as $servername => $thisserver)  {
  $serverstatus[$servername] = slavestatus($thisserver['host'], $thisserver['username'], $thisserver['password'], $thisserver['port']);
}
if($_GET['cron'] == 1)  {
  //run checks and send email if neccessary?
  foreach($serverstatus as $servername => $thisserver)  {
    $message = '';
	$message .= $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."\n\n";
    if($thisserver == FALSE)  {
	  $message .= "Failed to connect to $servername\n\n";
	  mail ($server[$servername]['email'] , "Replication problems with server $servername", $message, $from_email);
	}
	elseif ($thisserver['io_run'] == 'no' || $thisserver['io_run'] == 'No' || $thisserver['io_run'] == 'NO' || $thisserver['sql_run'] == 'no' || $thisserver['sql_run'] == 'No' || $thisserver['sql_run'] == 'NO')  {
	  $message .= "Replication has failed on $servername. See error below\n";
	  $message .= "$thisserver[errorNum]:    $thisserver[errorMeg]";
	  mail ($server[$servername]['email'] , "Replication problems with server $servername", $message, $from_email);
	}
  }
}
else {
  if(isset($_COOKIE['mysql_rep'])){ 
    $cookievalue = explode(':', $_COOKIE['mysql_rep']);
	if(md5($cookievalue[0].$user[$cookievalue[0]]) == $cookievalue[1])  {
	  echo "<html>\n\t<body>\n\t\t<h1>MySQL Replication Monitor</h1>\n";
      echo "<a href=\"http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\">REFRESH</a>\n";
      echo "\t\t<table border=1>\n\t\t\t<tr>\n
        \t\t\t\t<th> ServerName </th>\n
        \t\t\t\t<th> time </th>\n
        \t\t\t\t<th> host : port </th>\n
        \t\t\t\t<th> file </th>\n
        \t\t\t\t<th> position </th>\n
        \t\t\t\t<th> io run </th>\n
        \t\t\t\t<th> sql run </th>\n
        \t\t\t\t<th> errorNum </th>\n
        \t\t\t\t<th> errorMeg </th>\n
        \t\t\t\t<th> Stop / Start </th>\n
        \t\t\t\t<th> Extra </th>\n
        \t\t\t</tr>\n";
      foreach($serverstatus as $servername => $thisserver)  {
        if($thisserver == FALSE)  {
          echo "\t\t\t<tr";
          if ($thisserver['io_run'] == 'no' || $thisserver['io_run'] == 'No' || $thisserver['io_run'] == 'NO' || $thisserver['sql_run'] == 'no' || $thisserver['sql_run'] == 'No' || $thisserver['sql_run'] == 'NO')  {
            echo " BGCOLOR=\"#ff0000\"";
          }
          echo ">\n
            \t\t\t\t<td>$servername</td>\n
		     \t\t\t\t<td colspan=\"10\">Failed to connect to the server</td>\n";
        }
        else  {
          echo "\t\t\t<tr";
          if ($thisserver['io_run'] == 'no' || $thisserver['io_run'] == 'No' || $thisserver['io_run'] == 'NO' || $thisserver['sql_run'] == 'no' || $thisserver['sql_run'] == 'No' || $thisserver['sql_run'] == 'NO')  {
            echo " BGCOLOR=\"#ff0000\"";
          }
          echo ">\n
            \t\t\t\t<td>$servername </td>\n
            \t\t\t\t<td>$thisserver[time]</td>\n
            \t\t\t\t<td>$thisserver[host] : $thisserver[port]</td>\n
            \t\t\t\t<td>$thisserver[file]</td>\n
            \t\t\t\t<td>$thisserver[position]</td>\n
            \t\t\t\t<td>$thisserver[io_run]</td>\n
            \t\t\t\t<td>$thisserver[sql_run]</td>\n
            \t\t\t\t<td>$thisserver[errorNum]</td>\n
            \t\t\t\t<td>$thisserver[errorMeg]</td>\n
            \t\t\t\t<td><form name=\"form\" action=\"http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\" method=\"POST\">
            <input type=\"submit\" name=\"stop\"  id=\"stop\"  value=\"STOP $servername\">
            <input type=\"submit\" name=\"start\"  id=\"start\"  value=\"START $servername\">
            </form> </td>\n
            \t\t\t\t<td><a href=\"?detail=$servername\">details</a></td>\n
            \t\t\t</tr>\n";
        }
      }
      echo "</table>";
      if (isset($_GET['detail'])) {
        echo "<br /><center><b>Showing global status for: ".
        $_GET['detail'] . "</b></center><br />";
	    echo "<table border=1 align=center><tr><th>Variable</th><th>Value</th></tr>";
        $status = get_status($server[$_GET['detail']]['host'], $server[$_GET['detail']]['username'], $server[$_GET['detail']]['password'], $server[$_GET['detail']]['port']);
	    if($status != FALSE)  {
	      foreach($status as $statkey => $statvalue)  {
            echo "<tr><td>$statkey</td><td>$statvalue</td></tr>";
	      }
	    }
        else  {
	      echo "<tr><td colspan=\"2\">Failed to connect to server</td></tr>";
        }
	    echo "</table>";
      }
      echo '</body></html>';
	}
  }
  else{ 
    echo '<html><body><h1>Login</h1><br />';
	if(isset($loginerror))  {
	  echo '<br /><font color="#ff0000">'.$loginerror.'</font><br />';
	}
	echo '<form name="form" method="post" action="'.$_SERVER['PHP_SELF'].'"> 
      <p><label for="txtUsername">Username:</label> 
      <br /><input type="text" title="Enter your Username" name="txtUsername" /></p> 
      <p><label for="txtpassword">Password:</label> 
      <br /><input type="password" title="Enter your password" name="txtPassword" /></p> 
      <p><input type="submit" name="LOGIN" value="Login" /></p>
	  </form></body></html>';
  }	
}
?>