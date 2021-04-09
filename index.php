<?php 
session_start();

/* Конфигурация базы данных. */
$dbOptions = array(
	'db_host' => 'localhost',
	'db_user' => 'root',
	'db_pass' => 'root',
	'db_name' => 'guestbook'
);

$sort_list = array(
	'name_asc'   => '`name`',
	'name_desc'  => '`name` DESC',
	'email_asc'  => '`user_email`',
	'email_desc' => '`user_email` DESC',
	'date_asc'   => '`addtime`',
	'date_desc'  => '`addtime` DESC',
);



$dsn = "mysql:host=localhost;port=3307;dbname=guestbook;charset=utf8";

$options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

$pdo = new PDO($dsn, 'root', 'root', $options);
require "DB.class.php"; //Подключаем класс для работы с базой данных
require "helper.php"; //Подключаем вспомогательные функции

// Соединение с базой данных
DB::init($dbOptions);


$per_page = 25; //Максимальное число сообщений на одной странице
$num_page = 2;

$sort = @$_GET['sort'];
if (array_key_exists($sort, $sort_list)) {
	$sort_sql = $sort_list[$sort];
} else {
	$sort_sql = reset($sort_list);
}
//Получаем общее число сообщений
$result = DB::query('SELECT COUNT(*) AS numrows FROM guestbook');
$total = $result->fetch_object()->numrows;
$start_row = (!empty($_GET['p']))? intval($_GET['p']): 0;
if($start_row < 0) $start_row = 0;
if($start_row > $total) $start_row = $total;
$pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
$result = $pdo->prepare('SELECT * FROM guestbook ORDER BY '.$sort_sql.' LIMIT ?, ?');
$result->execute([$start_row,$per_page]);

foreach($result as $row){
	$row['addtime'] = format_date($row['addtime'],'date').'|'.format_date($row['addtime'],'time');
	$items[] = $row;
}


//Если нажата кнопка "Добавить отзыв"
if(!empty($_POST['submit'])){
	$secret = '6Ldd958aAAAAAIx9ExYVz_kuxSgJdjuVXbJEtkOk';
	$now = time();
	$antiflood = 120;//Время в секундах для блокировки повторной отправки сообщения
		
    $errors = array(); 

    $name = (!empty($_POST['name'])) ? trim(strip_tags($_POST['name'])) : false;
	$user_email = (!empty($_POST['user_email']) && filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) ? $_POST['user_email'] : false;        
    $text = (!empty($_POST['text'])) ? trim(strip_tags($_POST['text'])) : false;
    $home_page = (!empty($_POST['home_page'])) ? trim(strip_tags($_POST['home_page'])) : false;
	

	// ANTIFLOOD
	if (!$antiflood || (!isset($_SESSION['time']) || $now - $antiflood >= $_SESSION['time']) )  {
		
		if (empty($name)) $errors[] = '<div class="error">Вы не заполнили поле "Представьтесь"!</div>'; 
		if (empty($user_email)) $errors[] = '<div class="error">Вы не корректно заполнили поле "Ваш e-mail"!</div>';
        if (empty($text)) $errors[] = '<div class="error">Вы не заполнили поле "Текст"!</div>'; 
		if (!empty($_POST['g-recaptcha-response'])) {
			$out = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $_POST['g-recaptcha-response']);
			$out = json_decode($out);
			if ($out->success !== true) {
				$errors[] = '<div class="error">Вы не прошли капчу!</div>';
			} 
		} else {
			$errors[] = '<div class="error">Вы не установили флажок!</div>';
		}		
        
        //Если ошибок нет пишем отзыв в базу
        if(!$errors){
        	
        	//Переводим IP адрес пользователя в безнаковое целое число
        	$user_ip = sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
			$user_agent = $_SERVER["HTTP_USER_AGENT"];
			if (strpos($user_agent, "Firefox") !== false) $user_browser = "Firefox";
			elseif (strpos($user_agent, "Opera") !== false) $user_browser = "Opera";
			elseif (strpos($user_agent, "Chrome") !== false) $user_browser = "Chrome";
			elseif (strpos($user_agent, "MSIE") !== false) $user_browser = "MSIE";
			elseif (strpos($user_agent, "Safari") !== false) $user_browser = "Safari";
			else $user_browser = "Неизвестный";
        	//DB::query("INSERT INTO guestbook (name,user_email,text,home_page,user_browser,addtime,user_ip) VALUES ('".DB::esc($name)."','".DB::esc($user_email)."','".DB::esc($text)."','".DB::esc($home_page)."','".DB::esc($user_browser)."','".$now."','".$user_ip."')");
        	$result = $pdo->prepare('INSERT INTO guestbook (name,user_email,text,home_page,user_browser,addtime,user_ip) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $result->execute([DB::esc($name),DB::esc($user_email),DB::esc($text),DB::esc($home_page),DB::esc($user_browser),$now, $user_ip]);
        	$_SESSION['time'] = $now;  
			
        	if(DB::getMySQLiObject()->affected_rows == 1){
        		$errors[] = '<div class="error">Ваш отзыв успешно добавлен!</div>';
        	}
        	else{
        		$errors[] = '<div class="error">Ваш отзыв не добавлен. Попробуйте позже!</div>';
        	}
        }				
	}
	else{
		$errors[] = '<div class="error">Подождите '.ceil($antiflood/60).' минут(у,ы) перед отправкой следующего сообщения!</div>'; 
	}
    
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" >
<html xmlns="http://www.w3.org/1999/xhtml" id="nojs">
    <head>
        <meta http-equiv="keywords" content="Гостевая книга" />
	    <meta http-equiv="description" content="Гостевая книга" />
	    <title>Гостевая книга</title>
	    <link rel="stylesheet" href="styles/style.css" type="text/css" /> 
	    <script type="text/javascript" src="js/jquery-1.4.2.min.js"></script> 
	    <script type="text/javascript" src="js/scripts.js"></script>
		<script src="https://www.google.com/recaptcha/api.js"></script> 
    </head>
    <body>
    
	<div class="contentToChange">
	<h1>Отзывы</h1>

        <a name="top"></a>
        <div class="noFloat">
    	    <div class="titleText" onclick="show_form()">оставить отзыв
                <a class="add_com_but"><img src="images/show_com.png" alt=""></a>
            </div>    	    
        </div>
	      
        <div class="add_com_block" id="add_com_block" style="display:<?=(!empty($errors))? 'block': 'none'?>;">
        <?=(!empty($errors))? '<div class="errors">'.implode($errors).'</div>': ''?>  
            <form action="index.php" method="post" accept-charset="utf-8" enctype="multipart/form-data">	
		    <label>Представьтесь:</label>
		    <input class="text" name="name" value="<?=set_value('name');?>" type="text" autocomplete="off">
		    <label>Ваш e-mail:</label>
		    <input class="text" name="user_email" value="<?=set_value('user_email');?>" type="text" autocomplete="off">
			<label>Адрес вашего сайта(необязательно):</label>
		    <input class="text" name="home_page" value="<?=set_value('home_page');?>" type="text" autocomplete="off">
		    <label>Сообщение:</label>
		  	<textarea cols="15" rows="5" name="text" id="com_text"><?=set_value('text');?></textarea>
			<div class="g-recaptcha" data-sitekey="6Ldd958aAAAAAEqw8R6bSNWGwhB0lY4WOgb5IiRW"></div>
	        
            
            <div class="plusClear"><input class="but" name="submit" value="Отправить" type="submit"></div>

            <input name="email" value="" type="hidden">
            <input name="form" value="guestbook" type="hidden">
	        <img class="hide_com" src="images/hide_com.gif" alt="" onclick="show_form();">
            </form>

        </div>
        
	
	<div class="sort-bar">
	<div class="sort-bar-title">Сортировать по:</div> 
	<div class="sort-bar-list">
		<?php echo sort_link_bar('Name', 'name_asc', 'name_desc'); ?>
		<?php echo sort_link_bar('Email', 'email_asc', 'email_desc'); ?>
		<?php echo sort_link_bar('Date', 'date_asc', 'date_desc'); ?>
	</div> 
 </div> 
 
<table>
	<thead>
		<tr>
			<th>Name</th>
			<th>Email</th>
			<th>Text</th>
			<th>Date</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($items as $row): ?>
		<tr>
			<td><?php echo $row['name']; ?></td>
			<td><?php echo $row['user_email']; ?></td>
			<td><?php echo $row['text']; ?></td>
			<td><?php echo $row['addtime']; ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?=pagination($total,$per_page,$num_page,$start_row,'http://guestbook/index.php')?>
		
</div>
</body>
</html>

