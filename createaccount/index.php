<?php

	$strRel = '../../../';
	include($strRel.'config.php');
	if(!$member->isLoggedIn()) doError('You\'re not logged in.');
	include($DIR_LIBS . 'PLUGINADMIN.php');

//create the admin area page
	$oPluginAdmin = new PluginAdmin('CreateAccount');
	$oPluginAdmin->start();

	global $member, $manager, $CONF;

//表示件数設定
	$count = 30;

	if($member->isLoggedIn() && $member->isAdmin()) {
		$slink = '<a href="'.$_SERVER['PHP_SELF'].'?mode='.$_GET['mode'].'&amp;sort=';
		$clink = '<a href="'.$_SERVER['PHP_SELF'].'?mode='.$_GET['mode'].'&amp;check=';
//アカウント作成
		if(postVar('submit')) {
			$mname = strtolower(postVar('name'));
			$mrealname = postVar('realname');
			$mfolder = postVar('folder');
			$memail = postVar('email');
			$mlogin = postVar('login');
			$mnotes = postVar('notes');
			$bcablog = postVar('cablog');
			$bcateam = postVar('cateam');
//チェック
			if(!$memail) $err = 'メールアドレスを記入してください。';
			if(!$mrealname) $err = 'ユーザー名を記入してください。';
			if(!$mname) $err = 'ユーザーIDを記入してください。';
			if(!$mfolder) $err = '設置フォルダ名を記入してください。サイトを作成しない場合は「*」を記入します。';
			if(!postVar('defskin')) $err = 'スキンの設定（「スキンの説明」の最初に「u_」）をしてください。';
			if(!$mlogin && $bcateam) $err = 'チーム登録には管理者領域へのログインが必要です。';
			$i = strlen($DIR_SKINS) - 6;
			$blogDir = substr($DIR_SKINS, 0, $i);
			$dir = @opendir($blogDir) or die("ディレクトリのオープンに失敗しました");
			while($file = readdir($dir)) {
				if($file == $mname) $err = 'このユーザーIDは既に使われています。';
			}
			closedir($dir);
			$result = mysql_query('SELECT bshortname FROM '.sql_table('blog'));
			while($row = mysql_fetch_assoc($result)) {
				if($row['bshortname'] == $mname) $err = 'このユーザーIDは既に使われています。';
			}
			mysql_free_result($result);
			$result = mysql_query('SELECT mname FROM '.sql_table('member'));
			while($row = mysql_fetch_assoc($result)) {
				if($row['mname'] == $mname) $err = 'このユーザーIDは既に使われています。';
			}
			mysql_free_result($result);
//エラー表示
			if($err) {
				echo '<h4>※新規アカウントを作成できませんでした。　[警告] '.$err.'</h4>';
				$oPluginAdmin->end();
				exit;
			}
//メンバー追加
//			$url = 'http://'.$_SERVER['HTTP_HOST'].'/'.postVar('folder').'/';
			$url = $CONF['IndexURL'].postVar('folder').'/';
			$pass = crypt(crypt("pass"), "ca");
			$mpassword = md5($pass);
			$query = 'INSERT INTO '.sql_table('member')." (mname, mrealname, mpassword, memail, murl, mnotes, mcanlogin) VALUES ('$mname', '$mrealname', '$mpassword', '$memail', '$url', '$mnotes', '$mlogin')";
			sql_query($query);
			$memberid	= mysql_insert_id();
			$newmem = new MEMBER();
			$dataArray = array(
						'member' => &$newmem
					);
			$manager->notify('PostRegister', $dataArray);		
// add New blog
			$bname			= trim(postVar('realname')).' Website';
			$bshortname		= trim(postVar('folder'));
			$btimeoffset	= postVar('timeoffset');
			$bdefskin		= postVar('defskin');
// add slashes for sql queries
			$bname = 		addslashes($bname);
			$bshortname = 	addslashes($bshortname);
			$btimeoffset = 	addslashes($btimeoffset);
			$bdefskin = 	addslashes($bdefskin);
// send Mail
			@mb_language('Ja') ;
			@mb_internal_encoding('UTF-8');
			$subject = '新規アカウント発行 : '.$CONF['SiteName'];
			$message = "'".$CONF['SiteName']. "'へようこそ。\n". $CONF['IndexURL']."\n\n";
			$message .= $mrealname."さん\n\n";
			$message .= "'".$CONF['SiteName']. "'の登録情報は下記の通りです。\n\n";
			$message .= 'ユーザーID : '.$mname."\n";
			$message .= 'パスワード : '.$pass."\n";
			$message .= 'E-mail : '.$memail."\n";
			if($bcablog) $message .= 'URL : '.$url."\n";
			$message .= '備考 : '.$mnotes."\n\n";
			$message .= "下記URLよりログインしてください。\n";
			$message .= $CONF['IndexURL'].'nucleus/'."\n";
			$message .= getMailFooter();
			$mailfrom = 'From:'.mb_encode_mimeheader($CONF['SiteName']) .'<'.$CONF['AdminEmail'].'>';
			@mb_send_mail($memail, $subject, $message, $mailfrom);
// create createcount	
			$query = 'INSERT INTO '.sql_table('plugin_ca')." (caid, cablog, cateam) VALUES ('$memberid', '$bcablog', '$bcateam')";
			sql_query($query);
			if($bcablog){
// create blog
				$query = 'INSERT INTO '.sql_table('blog')." (bname, bshortname, bdesc, btimeoffset, bdefskin) VALUES ('$bname', '$bshortname', '$bdesc', '$btimeoffset', '$bdefskin')";
				sql_query($query);
				$blogid	= mysql_insert_id();
				$blog	=& $manager->getBlog($blogid);
// create new category
				sql_query('INSERT INTO '.sql_table('category')." (cblog, cname, cdesc) VALUES ($blogid, 'General','Items that do not fit in other categories')");
				$catid = mysql_insert_id();
// set as default category
				$blog -> setDefaultCategory($catid);
				$blog -> writeSettings();
// create team member	
				$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($memberid, $blogid, 0)";
				sql_query($query);
// create team member	
				$bmemberid = $member -> getID();
				$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($bmemberid, $blogid, 1)";
				sql_query($query);
//新規アイテム
				$blog->additem($blog->getDefaultCategory(), "プロフィール", "ID：".$bshortname."\n[[ホームページ]]".$url, "", $blogid, $memberid, $blog->getCorrectTime(), 0, 0, 0);
				$blog->additem($blog->getDefaultCategory(), "ブックマーク", "[[Nucleus CMS]]http://japan.nucleuscms.org/\n[[Nucleusフォーラム]]http://japan.nucleuscms.org/bb/", "", $blogid, $memberid, $blog->getCorrectTime(), 0, 0, 0);
//事後処理
				$dataArray = array(
							'blog' => &$blog
						);
				$manager->notify(
					'PostAddBlog',
					$dataArray
				);
				$manager->notify(
					'PostAddCategory',
					array(
						'catid' => $catid
					)
				);
				$blog =& $manager -> getBlog($blogid);		
				$blog -> setURL($url);
				$blog -> writeSettings();
			}
// add team
			if($bcateam) {
				$addteam = explode('/', $bcateam);
				foreach($addteam as $addteams) {
					$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($memberid, $addteams, 0)";
					sql_query($query);
				}
			}
//メンバー追加完了
			echo '<h2>'.$mname.'さんをメンバーに追加しました。</h2>';
			if($bcablog) echo '<p>メンバーURL:<a href="'.$url.'" target="_blank">'.$url.'</a><br />ブログID：'.$blogid.'<br />※注意：セーフモードの場合、上記ページにアクセスしてもエラーが出るので所有者を変更する必要があります。FTPソフトで一旦新規に作成した「'.$mname.'」フォルダ（その中のファイルを含む。以下同じ）をダウンロードしてからサーバー上のそのフォルダを削除してください。その後、同じフォルダ名で再度アップロードし直してください。</p>';
		}
//ページスイッチ
			$uri = sprintf('%s%s%s', 'http://', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
			list($plink, $mpage) = explode('page=', $uri, 2);
			$p0 = ($_GET['page']) ? $_GET['page'] : 1 ;
			$p00 = ($_GET['page']) ? ($_GET['page'] - 1) * $count : 0 ;
			if($p0 > 1) $p1 = $p0 - 1;
			$p2 = $p0 + 1;
			echo '<p>';
			if($p1) echo '<a href="'.$plink.'&amp;page='.$p1.'">P.'.$p1.'</a>';
			echo ' [P.'.$p0.'] ';
			echo '<a href="'.$plink.'&amp;page='.$p2.'">P.'.$p2.'</a>';
			echo '　（※'.$count.'件ずつ表示）</p>';
//ヘッダー
			if($_GET['mode'] == 2) {
				echo '		<h2>新規ユーザー作成　（→<a href="'.$_SERVER['PHP_SELF'].'?mode=0" title="ユーザー管理画面へ戻る">ユーザー管理</a>）</h2>';
			}else {
				echo '		<h2>ユーザー管理　（→<a href="'.$_SERVER['PHP_SELF'].'?mode=2" title="新規ユーザー作成画面へ進む">新規ユーザー作成</a>）</h2>';
			}
//ユーザー管理
		if(!$_GET['mode']) {
			// リクエストパラメータbget,blogid,memidは変数に設定する(2008-04-29変更)
			unset($req_bget, $req_blogid, $req_memid);
			$req_bget = ($_GET['bget']) ? urldecode($_GET['bget']) : postVar('bget');
			$req_blogid = ($_GET['blogid']) ? urldecode($_GET['blogid']) : postVar('blogid');
			$req_memid = ($_GET['memid']) ? urldecode($_GET['memid']) : postVar('memid');
			if ( !$req_blogid && !$req_memid ) $req_bget = '';
//ユーザーオプション変更
			if($_GET['edit']) {
				$caid = $_GET['caid'];
				$cablog = ($_GET['cablog']) ? $_GET['cablog'] : 0;
				$cateam = ($_GET['cateam']) ? $_GET['cateam'] : 0;
				if(!$_GET['mlogin'] && $cateam) {
					echo '<br />※チーム登録には管理者領域へのログインが必要です。';
					$cateam = 0;
				}
				sql_query('DELETE FROM '.sql_table('team').' WHERE tmember = '.$caid);
				$caid1 = quickQuery('SELECT caid as result FROM '.sql_table('plugin_ca').' WHERE caid = '.$caid);
				if(!$caid1) {
					$query = "INSERT INTO ".sql_table('plugin_ca')." (caid, cablog, cateam) VALUES ('$caid', '$cablog', '$cateam')";
					sql_query($query);
				}
				$query = "UPDATE ".sql_table('plugin_ca')." SET cablog = ".$cablog.", cateam = '".$cateam."' WHERE caid = ".$caid;
				sql_query($query);
				if($cateam) {
					$addteam = explode('/', $cateam);
					foreach($addteam as $addteams) {
						$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($caid, $addteams, 0)";
						sql_query($query);
					}
				}
				echo '<br />'.$_GET['mname'].' -> Web権限:'.$cablog.' チーム:'.$cateam.'。';
			}
//メンバー選択
			echo '		<div><form action="" method="POST">
				<input type="hidden" name="bget" value="3">
				<select name="memid" onChange="this.form.submit();">
				<option value="">※メンバー選択</option>';
				$res = mysql_query('SELECT mnumber, mname FROM '.sql_table('member'));
				while($row = mysql_fetch_assoc($res)) {
					$p_flag = ($row['mnumber'] == $req_memid) ? ' selected="selected"' : '';
					echo '<option value="'.$row['mnumber'].'"'.$p_flag.'>'.$row['mnumber'].' '.$row['mname'].'</option>';
				}
//チーム選択
					echo '				</select></form>
				<form action="" method="POST">
				<input type="hidden" name="bget" value="1">
				<select name="blogid" onChange="this.form.submit();">
				<option value="">※チーム選択</option>';
				$res = mysql_query('SELECT bnumber, bname FROM '.sql_table('blog'));
				while($row = mysql_fetch_assoc($res)) {
					$p_flag = ($row['bnumber'] == $req_blogid) ? ' selected="selected"' : '';
					echo '<option value="'.$row['bnumber'].'"'.$p_flag.'>'.$row['bnumber'].' '.$row['bname'].'</option>';
				}
			echo '				</select></form></div>';
//query
			$query = 'SELECT mnumber, mname, mrealname, memail, murl, mnotes, madmin, mcanlogin, tadmin, tblog, bname, bshortname, bnumber, cablog, cateam';
			$query .= ' FROM '.sql_table('member').' left join '.sql_table('team').' on tmember = mnumber left join '.sql_table('blog').' on tblog = bnumber left join '.sql_table('plugin_ca').' on caid = mnumber ';
			$query .= 'WHERE mnumber = mnumber ';
			if($req_bget == 1) $query .= 'and bnumber = '.$req_blogid.' ';
			if($req_bget == 3) $query .= 'and mnumber = '.$req_memid.' ';
			if($req_bget == 2 || !$req_bget) $query .= 'GROUP BY mnumber ';
			$query .='ORDER BY ';
			if($_GET['sort'] == 1) {
				$query .= 'mnumber ASC';
			}elseif($_GET['sort'] == 2) {
				$query .= 'mname ASC';
			}elseif($_GET['sort'] == 3) {
				$query .= 'mname DESC';
			}else {
				$query .= 'mnumber DESC';
			}
			$query .= ' LIMIT '.$p00.','.$count;
			$result = mysql_query($query) or die("Bad query: ".mysql_error());;
//print_r($query);
//body header print
			$loginn = ($_GET['mcanlogin'] == 1) ? '0' : '1';
			echo '		<table>
			<thead>
				<tr><th>';
			$no = $slink;
			$no .= ($_GET['sort'] == 1) ? '20' : '1';
			$no .= '" title="sort">NO.</a>';
			$nos = ($req_bget == 1 || $req_bget == 3) ? '権限' : $no ;
			echo $nos.'</th>
				<th>'.$slink;
			if($_GET['sort'] == 2) echo '3' ; else echo '2';
			echo '" title="メンバー名で並替">メンバー</a></th>
				<th>チーム</th>
				<th>Web権限</th>
				<th>チーム一括変更</th>
				<th>更新</th>
				<th>特記事項</th>
			</tr></thead><tbody>';
//body main print
			while($row = mysql_fetch_assoc($result)) {
				$mnotes = shorten(htmlspecialchars(trim($row['mnotes'])), 40, '...');
				$tadmin = ($req_bget == 1 || $req_bget == 3) ? $row['tadmin'] : $row['mnumber'] ;
				$bname = (!$row['bnumber']) ? '' : '<a href="'.$CONF['AdminURL'].'?action=manageteam&amp;blogid='.$row['bnumber'].'" title="チーム設定ページへ">'.$row['bnumber'].' '.$row['bname'].'</a><br />'.$row['bshortname'] ;
				echo '			<tr>
				<td>'.$tadmin.'</td>
				<td><a href="'.$CONF['AdminURL'].'?action=memberedit&amp;memberid='.$row['mnumber'].'" title="メンバー設定ページへ">'.$row['mrealname'].'</a><br />'.$row['mname'].'</td>
				<td>';
				if($req_bget == 3 || $req_bget == 1) {
					echo $bname;
				}elseif($row['cateam']) {
					echo '
					<form>
					<select onChange="location.href=this.options[this.selectedIndex].value">
					<option value="'.$_SERVER['PHP_SELF'].'">※チーム設定ページへ</option>';
					$cateam = explode('/', $row['cateam']);
					foreach($cateam as $cateam1) {
						$bnames = quickQuery('SELECT bname as result FROM '.sql_table('blog').' WHERE bnumber = '.$cateam1);
						echo '<option value="'.$CONF['AdminURL'].'?action=manageteam&amp;blogid='.$cateam1.'">'.$cateam1.' '.$bnames.'</option>';
					}
					echo '
					</select></form>';
					foreach($cateam as $cateam1) {
						echo '<a href="'.$_SERVER['PHP_SELF'].'?bget=1&amp;blogid='.$cateam1.'" title="チーム絞込">'.$cateam1.'</a> ';
					}
					
				}elseif($row['madmin']) {
					echo '				※Super-Admin';
				}else {
					echo '				※Blogなし';
				}
				echo '				</td>
				<td>';
				if(!$row['madmin']) {
					echo '
				<form method="get" action="'.$_SERVER['PHP_SELF'].'">
				<input name="caid" value="'.$row['mnumber'].'" type="hidden">
				<input name="mname" value="'.$row['mname'].'" type="hidden">
				<input name="mlogin" value="'.$row['mcanlogin'].'" type="hidden">
				<input name="cablog" value="1" type="radio" ';
					if($row['cablog']) echo ' checked="checked"';
					echo '				/>はい<br />
				<input name="cablog" value="0" type="radio" ';
					if(!$row['cablog']) echo ' checked="checked"';
					echo '				/>いいえ</td>
				<td><input name="cateam" value="'.$row['cateam'].'" size="15"></td>
				<td><input name="edit" value="edit" type="submit"></form>';
				}else {
					echo '				※Super-Admin</td><td>
				</td><td>';
				}
				echo '				<td>'.$mnotes.'</td>
				</tr>';
			}
			echo '				</tr></tbody></table>';

//新規ユーザー追加
		}elseif($_GET['mode'] == 2) {
			$name = ($_GET['name']) ? urldecode($_GET['name']) : postVar('name');
			$realname = ($_GET['realname']) ? urldecode($_GET['realname']) : postVar('realname');
			$folder = ($_GET['folder']) ? urldecode($_GET['folder']) : postVar('folder');
			$email = ($_GET['email']) ? urldecode($_GET['email']) : postVar('email');
			echo '			<p>※ユーザーIDと同一名のブログが同時に作成されます。（パスワードは自動発行）</p>
				<form method="post" action="'.$_SERVER['PHP_SELF'].'"><div>
				<table>
				<tr>
					<td>ユーザーID (※半角英数字で6文字以上15字以内)</td>
					<td><input tabindex="10010" name="name" size="15" maxlength="15" value="'.$name.'" /></td>
				</tr><tr>
					<td>ユーザー名 (※日本語可)</td>
					<td><input name="realname" tabindex="10020" size="40" maxlength="60" value="'.$realname.'" /></td>
				</tr><tr>
					<td>設置フォルダ (※半角英数字で6文字以上15字以内)</td>
					<td><input name="folder" tabindex="10020" size="15" maxlength="15" value="'.$folder.'" /></td>
				</tr><tr>
					<td>メールアドレス</td>
					<td><input name="email" tabindex="10050" size="40" maxlength="60" value="'.$email.'" /></td>	
				</tr><tr>
					<td>ブログのスキン (※「スキンの説明」の最初に「u_」が必要)</td>
				<td>';
			$query = 'SELECT sdname as text, sdnumber as value'
					       . ' FROM '.sql_table('skin_desc')
					       . ' WHERE sddesc LIKE "u_%"';
					$template['name'] = 'defskin';
					$template['tabindex'] = 50;
					$template['selected'] = $CONF['BaseSkin'];
					showlist($query, 'select' ,$template);
			echo '</td>
				</tr><tr>
					<td>サーバ時刻との時差<br />現在のサーバ時刻: <strong>';
			echo strftime("%H:%M",time());
			echo '</strong></td>
					<td><input name="timeoffset" tabindex="10060" size="3" value="0" /></td>
				</tr><tr>
					<td>チーム登録(※BlogIDを記入。複数可)　[例1] 2　[例2] 2/5/8</td>
					<td><input name="cateam" tabindex="10065" size="15" value="" /></td>
				</tr><tr>
					<td>管理者領域へのログイン</td>
					<td><input name="login" tabindex="10068" value="1" checked="checkded" type="radio" />はい
				<input name="login" tabindex="10068" value="0" type="radio" />いいえ</td>
				</tr><tr>
					<td>ユーザーサイトの作成</td>
					<td><input name="cablog" tabindex="10075" value="1" checked="checkded" type="radio" />はい
				<input name="cablog" tabindex="10075" value="0" type="radio" />いいえ</td>
				</tr><tr>
					<td>備考 (※任意)</td>
					<td><input name="notes" maxlength="100" size="40" tabindex="10080" value="'.postVar('notes').'" /></td>
				</tr><tr>
					<td>新しいアカウントの追加</td>
					<td><input name="submit" type="submit" value="アカウントの追加" tabindex="10090" onclick="return checkSubmit();" /> <input name="reset" type="reset" tabindex="10100" /></td>
				</tr></table>
			</div></form>';
		}

//チームメンバー
	}else {
		$query = 'SELECT tblog FROM '.sql_table('team').' WHERE tmember = '.$member -> getID();
		$result = sql_query($query);
		while ($row = mysql_fetch_object($result)) {
    	$blogid = $row -> tblog;
		}
		mysql_free_result($result);
		$blog =& $manager->getBlog($blogid);
		$ballowpast = (postVar('allowpastposting')) ? postVar('allowpastposting') : 0; 
		$bcomments = (postVar('comments')) ? postVar('comments') : 0;
		$bpublic = (postVar('public')) ? postVar('public') : 0;
		if(!postVar('name')) $err = 'ブログの名前を記入してください。';
		if(!(postVar('public') == 0 || postVar('public') == 1) || !(postVar('comments') == 0 || postVar('comments') == 1) || !(postVar('allowpastposting') == 0 || postVar('allowpastposting') == 1)) $err = '全ての項目を正確に記入してください。';
		if(postVar('submit') && !$err) {
			$query = 'UPDATE '.sql_table('blog').' SET 
						bname = "'.postVar('name').'", 
						bdefskin = '.postVar('defskin').', 
						ballowpast = '.$ballowpast.', 
						bcomments = '.$bcomments.', 
						bpublic = '.$bpublic.' 
						WHERE bnumber = '.$blogid;
			sql_query($query);
			echo '<h2>ブログの設定を変更しました。</h2>';
			$oPluginAdmin->end();
			exit;
		}
		echo '<h2>あなたのブログの設定</h2>';
		echo '			<form method="post" action="'.$_SERVER['PHP_SELF'].'"><div>
		<table><tr>
			<td>ユーザーID</td>
			<td>';
		echo  htmlspecialchars($blog->getShortName());
		echo  '			</td>
		</tr><tr>
			<td>ブログのURL</td>
			<td>';
		$burl = htmlspecialchars($blog->getURL());
		echo '<a href="'.$burl.'" target=_blank">'.$burl.'</a>';
		echo  '			</td>
		</tr><tr>
			<td>ブログの名前</td>
			<td><input name="name" tabindex="10" size="40" maxlength="60" value="';
		echo htmlspecialchars($blog->getName());
		echo '" /></td>
		</tr><tr>
			<td>ブログのスキン</td>
			<td>';
					$query =  'SELECT sdname as text, sdnumber as value'
					       . ' FROM '.sql_table('skin_desc')
					       . ' WHERE sddesc LIKE "u_%"';
					$template['name'] = 'defskin';
					$template['selected'] = $blog->getDefaultSkin();
					$template['tabindex'] = 50;
					showlist($query,'select',$template);
echo  '			</td>
		</tr><tr>
			<td>過去の日時での投稿を許可しますか?（ 1 … はい、0 … いいえ）</td>
			<td><input name="allowpastposting" tabindex="1" maxlength="1" size="20" value="';
echo  htmlspecialchars($blog->allowPastPosting());
echo  '" /></td>
		</tr><tr>
			<td>コメントを許可しますか?（ 1 … はい、0 … いいえ）</td>
			<td><input name="comments" tabindex="1" maxlength="1" size="20" value="';
echo  htmlspecialchars($blog->commentsEnabled());
echo  '" /></td>
		</tr><tr>
			<td>非メンバーのコメントを許可しますか?（ 1 … はい、0 … いいえ）</td>
			<td><input name="public" tabindex="1" maxlength="1" size="20" value="';
echo  htmlspecialchars($blog->isPublic());
echo  '" /></td>
		</tr><tr>		
			<td>設定の変更</td>
			<td><input name="submit" type="submit" tabindex="130" value="設定の変更" onclick="return checkSubmit();" /></td>
		</tr></table>
		</div></form>';
		if(postVar('submit')) echo '<h4>※[警告] '.$err.'</h4>';

		echo '		<h2>あなたのカテゴリーの設定</h2>';
		if(postVar('sbmt')) {
			if(postVar('cname')) {
				$query = 'UPDATE '.sql_table('category').' SET cname = "'.postVar('cname').'", cdesc = "'.postVar('cdesc').'"	WHERE catid = '.postVar('catid');
				sql_query($query);
				echo '<strong>※カテゴリーの設定を変更しました。</strong>';
			}else {
				echo '<h4>※[警告] カテゴリー名を記入してください。</h4>';
			}
		}elseif($_GET['d_catid']) {
			$query = 'DELETE FROM '.sql_table('category').' WHERE catid='.$_GET['d_catid'];
			sql_query($query);
			echo '※<strong>カテゴリーを削除しました。</strong>';
		}elseif(postVar('sbmt2')) {
			if(postVar('cname')) {
				$cblog = addslashes(postVar('cblog'));
				$cname = addslashes(postVar('cname'));
				$cdesc = addslashes(postVar('cdesc'));
				$query = 'INSERT INTO '.sql_table('category')." (cblog, cname, cdesc) VALUES ('$cblog', '$cname', '$cdesc')";
				sql_query($query);
				echo '※<strong>カテゴリーを追加しました。</strong>';
			}else {
				echo '<h4>※[警告] カテゴリー名を記入してください。</h4>';
			}
		}
		echo '		<table><thead><tr>
			<th>カテゴリーの名前</th><th>カテゴリーの説明</th><th colspan="2">アクション</th></tr></thead>';
		$result = mysql_query('SELECT * FROM '.sql_table('category').' WHERE cblog='.$blog->getID().' ORDER BY cname');
		while($row = mysql_fetch_assoc($result)) {
			echo '			<tbody><tr onmouseover="focusRow(this);" onmouseout="blurRow(this);">
				<form method="post" action="'.$_SERVER['PHP_SELF'].'">
				<input type="hidden" name="catid" value="'.$row['catid'].'" />
				<td><input type="text" name="cname" value="'.$row['cname'].'" size="30" maxlength="40" /></td>
				<td><input type="text" name="cdesc" value="'.$row['cdesc'].'" size="40" maxlength="200" /></td>
				<td><input type="submit" name="sbmt" value="変更" tabindex="200" /></td>
				<td><a href="'.$_SERVER['PHP_SELF'].'?d_catid='.$row['catid'].'" tabindex="200">削除</a></td>
				</form>
			</tr></tbody>';
		}
		mysql_free_result($result);
		echo '		</table>';

		echo '		<form action="'.$_SERVER['PHP_SELF'].'" method="post"><div>
		<input name="cblog" value="'.$blog->getID().'" type="hidden" />
		<table><tr>
			<th colspan="2">新しいカテゴリーを作る</th>
		</tr><tr>
			<td>カテゴリー名</td>
			<td><input name="cname" size="40" maxlength="40" tabindex="300" /></td>
		</tr><tr>
			<td>カテゴリー名の説明</td>
			<td><input name="cdesc" size="40" maxlength="200" tabindex="310" /></td>
		</tr><tr>
			<td>新しいカテゴリーを作る</td>
			<td><input type="submit" name="sbmt2" value="新しいカテゴリーを作る" tabindex="320" /></td>
		</tr></table>
		</div></form>';
	}
	$oPluginAdmin->end();
?>
