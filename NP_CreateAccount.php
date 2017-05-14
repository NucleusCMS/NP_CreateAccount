<?php
class NP_CreateAccount extends NucleusPlugin {
	function getName(){return "CreateAccount";}
	function getAuthor(){return "hard + jun + nyanko";}
	function getURL(){return "http://nucleus.mz-style.com/";}
	function getVersion(){return "0.311n";}
	function getDescription(){return "ユーザーアカウント管理プラグイン。アカウントの作成、削除、チームへの一括登録が行えます。オリジナルからの変更点：FTP使用のOn/Off、希望のフォルダ名で作成可能、Blogを削除してもユーザーは消えません。";}
	function supportsFeature($what) {return in_array($what,array('SqlTablePrefix','SqlApi'));}
	function getEventList() {return array('PostRegister', 'PostAddBlog', 'PreDeleteBlog', 'QuickMenu');}
	function install() {
		sql_query('CREATE TABLE IF NOT EXISTS '.sql_table('plugin_ca').' (caid int(11) NOT NULL, cablog char(1), cateam text, PRIMARY KEY (caid))');
		$this -> createOption("ftpon", 'FTPの使用', 'yesno', 'no');
		$this -> createOption("ftpServer", "FTP ホスト名(アドレス)　[例1] 222.222.22.222　[例2] ftp.xxx.com", "text", "");
		$this -> createOption("ftpUser", "FTP ユーザー名", "text", "");
		$this -> createOption("ftpPass", "FTP パスワード", "password", "");
		$this -> createOption("ftpFolder", "FTP 初期フォルダ　[例] public_html/blog", "text", "");
		$this -> createOption('del_uninstall_ca', 'プラグイン削除時にデータベースを削除しますか？', 'yesno', 'no');
//		$this -> createOption("addTeam", "Blogへのチーム登録(※BlogIDを記入。複数可)　[例1] 2　[例2] 1/4/6", "text", "");
		$this -> createOption("quickmenu", '「quick menu」に登録しますか？', 'yesno', 'yes');
	}
	function uninstall() {
		if ($this -> getOption('del_uninstall_ca') == 'yes') {
			sql_query ("DROP table ".sql_table('plugin_ca'));
		}
		$this -> deleteOption("ftpOn");
		$this -> deleteOption("ftpServer");
		$this -> deleteOption("ftpUser");
		$this -> deleteOption("ftpPass");
		$this -> deleteOption("ftpFolder");
		$this -> deleteOption("addTeam");
		$this -> deleteOption("quickmenu");
	}
	function event_QuickMenu($data) {
		global $member;
		if($this->getOption('quickmenu') != 'yes') return;
		if($member->isLoggedIn() && $member->isAdmin()) {
			array_push(
				$data['options'],
				array(
					'title' => 'ユーザー管理',
					'url' => $this->getAdminURL(),
					'tooltip' => 'ユーザーの管理を行ないます'
				)
			);
		}else {
			array_push(
				$data['options'],
				array(
					'title' => 'あなたのブログ',
					'url' => $this->getAdminURL(),
					'tooltip' => 'あなたのブログやカテゴリーの設定を行ないます'
				)
			);
		}
	}
	function getTableList() {	return array(sql_table('plugin_ca'));}
	function hasAdminArea() {	return 1;	}
	function doSkinVar($skinType) {
		global $CONF;
		if(postVar('submit')){
			@mb_language('Ja') ;
			@mb_internal_encoding('EUC-JP');
			$subject = '新規アカウント申込 : '.$CONF['SiteName'];
			$message = "'".$CONF['SiteName']."'への新規アカウント申込がありました。\n".$CONF['IndexURL']."\n\n";
			$message .= 'お名前 : '.postVar('realname')."\n";
			$message .= '希望ID : '.postVar('name')."\n";
			$message .= '希望フォルダ名 : '.postVar('folder')."\n";
			$message .= 'E-mail : '.postVar('email')."\n\n";
			$message .= "下記URLより新規アカウントの発行手続きを行なってください。\n";
			$message .= $CONF['IndexURL'].'nucleus/plugins/createaccount/index.php?mode=2&name='.urlencode(postVar('name')).'&realname='.urlencode(postVar('realname')).'&folder='.urlencode(postVar('folder')).'&email='.urlencode(postVar('email'))."\n";
			$message .= getMailFooter();
			$mailfrom = 'From:'.mb_encode_mimeheader('CreateAccount') .'<'.$CONF['AdminEmail'].'>';
			@mb_send_mail($CONF['AdminEmail'], $subject, $message, $mailfrom);
			echo '	※アカウントの発行を受付けました。場合によっては希望するIDを発行できないかもしれません。ご了承のほどよろしくお願いします。';
		}else {
			echo '				<div class="createaccount">
				<form method="post" action="'.$_SERVER['PHP_SELF'].'">
				希望ID: <input name="name" size="15" maxlength="15" /><br />
				お名前: <input name="realname" size="15" maxlength="60" /><br />
				希望フォルダ名: <input name="folder" size="15" maxlength="15" /><br />
				E-mail: <input name="email" size="15" maxlength="60" /><br />
				<input type="submit" name="submit" value="申込" />
				</form></div>';
		}
	}
	function event_PostRegister($data) {
		$memberid	= sql_insert_id();
		$addteam = quickQuery('SELECT cateam as result FROM '.sql_table('plugin_ca').' WHERE caid = '.$memberid);
		if(!$addteam) return;
		$addteam = explode('/', $addteam);
		foreach($addteam as $addteams) {
			$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($memberid, $addteams, 0)";
			sql_query($query);
		}
	}
	function event_PostAddBlog($data) {
		if ($this -> getOption('ftpOn') == 'yes') {
		global $CONF ,$DIR_SKINS;
		$blog = $data['blog'];
		$shortname = $blog -> getShortName();
		$ftp_server = $this -> getOption("ftpServer");
		$ftp_user = $this -> getOption("ftpUser");
		$ftp_pass = $this -> getOption("ftpPass");
		$ftp_folder = $this -> getOption("ftpFolder");
		$i = strlen($DIR_SKINS) - 6;
		$blogDir = substr($DIR_SKINS, 0, $i);
// setup connection & login
		$conn_id = ftp_connect("$ftp_server");
		$login_result = ftp_login($conn_id, "$ftp_user", "$ftp_pass");
// check connection
		if(!$conn_id) {
			exit ("Ftp connection has failed! Attempted to connect to $ftp_server for user $ftp_user");
		}elseif (!$login_result){
			exit ("Ftp login has failed! Attempted to connect to $ftp_server for user $ftp_user");
 	 	}
// create directory & change permission
		ftp_chdir($conn_id, $ftp_folder);
		ftp_mkdir($conn_id, $shortname);
		ftp_site($conn_id, "chmod 707 $shortname");
// create index.php
		$fp = @fopen($blogDir.$shortname."/index.php", "w") or die("\n index.php file cannot create!!");
		fputs($fp, "<?php\n");
		if($CONF['URLMode'] != 'pathinfo') {
			fputs($fp, "\$CONF['Self'] = 'index.php';\n");
		}else {
			fputs($fp, "\$CONF['Self'] = '';\n");
		}
		fputs($fp, "include('../config.php');\n");
		fputs($fp, "selectBlog('$shortname');\n");
		fputs($fp, "selector();\n");
		fputs($fp, "?>");
		fclose($fp);
// create fancyurls.config.php
		if($CONF['URLMode'] == 'pathinfo') {
			$j = strlen($CONF['IndexURL']) - 1;
			$indexurl = substr($CONF['IndexURL'], 0, $j);
			$fp = @fopen($blogDir.$shortname."/fancyurls.config.php", "w") or die("\n fancyurls.config.php file cannot create!!");
			fputs($fp, "<?php\n");
			fputs($fp, "\$CONF['Self'] = '$indexurl';\n");
			fputs($fp, "?>");
			fclose($fp);
		}
//change permission & close ftp
		ftp_site($conn_id, "chmod 755 $shortname");
		ftp_close($conn_id);
//setting
		$blog -> setURL($CONF['IndexURL'] . $shortname . "/index.php");
		$blog -> writeSettings();
		}
	}
	function event_PreDeleteBlog($data){
		if ($this -> getOption('ftpOn') == 'yes') {
		global $manager ,$DIR_SKINS;
		$ftp_server = $this -> getOption("ftpServer");
		$ftp_user = $this -> getOption("ftpUser");
		$ftp_pass = $this -> getOption("ftpPass");
		$ftp_folder = $this -> getOption("ftpFolder");
		$blogid = $data['blogid'];
		$blog =& $manager -> getBlog($blogid);
		$shortname = $blog -> getShortName();
		$i = strlen($DIR_SKINS) - 6;
		$blogDir = substr($DIR_SKINS, 0, $i);
// setup connection & login
		$conn_id = ftp_connect("$ftp_server");
		$login_result = ftp_login($conn_id, "$ftp_user", "$ftp_pass");
// check connection
		if(!$conn_id || !$login_result) {
			echo "Ftp connection has failed! Attempted to connect to $ftp_server for user $ftp_user";
			exit;
 	 	}
// directory permission change
		ftp_chdir($conn_id, $ftp_folder);
		@ftp_site($conn_id, "chmod 707 $shortname");
//delete all file
		foreach(glob($blogDir.$shortname."/*") as $fn) {
			@unlink($fn);
		}
//remove directory
		@ftp_rmdir($conn_id, $shortname);
//close
		ftp_close($conn_id);
		return;
		}
		if ($this -> getOption('ftpOn') == 'no') {
//delete member noFTP
		return;
		}
	}
}
?>
