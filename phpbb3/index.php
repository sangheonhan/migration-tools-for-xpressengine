<?php
    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // 사용하는 변수 선언
    $path = $_POST['path'];                         // config.php 파일 경로
    $target_type = $_POST['target_type'];           // 데이터 추출 대상의 종류 (회원, 포럼)
    $forum_id = $_POST['forum_id'];                    // 데이터 추출 대상 포럼
    if ($target_type != 'forum')
        $forum_id = null;
    $division = (int)($_POST['division']);          // 추출 데이터 분할 개수
    if (!$division)
        $division = 1;
    $exclude_attach = $_POST['exclude_attach'];     // 첨부 파일 포함 여부

    $step = 1;                                      // 검사 단계
    $errMsg = '';                                   // 오류 문구

    // 1 단계 검사 - 데이터베이스 정보 추출
    if ($path)
    {
        $db_info = getDBInfo($path);
        if(!$db_info)
        {
            $errMsg = "입력하신 경로가 잘못되었거나 데이터베이스 정보를 구할 수 있는 파일(config.php)이 없습니다";
        }
        else
        {
            $oMigration->setDBInfo($db_info);
            $oMigration->setCharset('UTF-8', 'UTF-8');
            $message = $oMigration->dbConnect();
            if ($message)
                $errMsg = $message;
            else
                $step = 2;
        }
    }

    // 2 단계 검사 - 포럼 목록 추출
    if ($step == 2)
    {
        // 포럼 목록을 구합니다.
        $query = "SELECT * FROM {$db_info->db_table_prefix}forums WHERE forum_type = 1";
        $forum_list_result = $oMigration->query($query);
        while ($forum_info = $oMigration->fetch($forum_list_result))
        {
            $forum_list[$forum_info->forum_id] = $forum_info;
        }
        if (!$forum_list || !count($forum_list))
            $forum_list = array();
    }

    // 3 단계 검사 - 포럼 선택
    if ($target_type)
    {
        if ($target_type == 'forum' && !$forum_id)
        {
            $errMsg = "포럼 데이터를 추출하기 위해 원하시는 포럼을 선택 해주세요.";
        }
        else
        {
            switch ($target_type) {
                case 'member' :
                        $query = sprintf("SELECT COUNT(*) AS count FROM %s%s", $db_info->db_table_prefix, 'users');
                    break;
                case 'forum' :
                        $query = sprintf("SELECT COUNT(*) AS count FROM %s%s WHERE forum_id = %s", $db_info->db_table_prefix, 'posts', $forum_id);
                    break;
            }
            $result = $oMigration->query($query);
            $data = $oMigration->fetch($result);
            $total_count = $data->count;        // 추출 데이터 개수

            $step = 3;

            if ($total_count > 0)
                $division_cnt = (int) ( ($total_count - 1) / $division ) + 1;
        }
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="ko" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="generator" content="zeroboard xe (http://www.zeroboard.com)" />
    <meta http-equiv="imagetoolbar" content="no" />

    <title>zbxe data export tool ver 0.2</title>
    <style type="text/css">
        body { font-family:arial; font-size:9pt; }
        input.input_text { width:400px; }
        blockquote.errMsg { color:red; }
        select.forum_list { display:block; width:500px; }
    </style>
    <link rel="stylesheet" href="./default.css" type="text/css" />

    <script type="text/javascript">
        function doCopyToClipboard(value) {
            if(window.event) {
                window.event.returnValue = true;
                window.setTimeout(function() { copyToClipboard(value); },25);
            }
        }
        function copyToClipboard(value) {
            if(window.clipboardData) {
                var result = window.clipboardData.setData('Text', value);
                alert("URL을 복사 하였습니다. Ctrl+v 또는 붙여넣기를 하시면 됩니다");
            }
        }
    </script>
</head>
<body>

    <h1>phpBB3 데이터 추출 도구 v0.1</h1>

    <?php
        if($errMsg) {
    ?>
        <hr />
        <blockquote class="errMsg">
            <?=$errMsg?>
        </blockquote>
    <?php
        }
    ?>

    <hr />

    <form action="./index.php" method="post">
        <h3>1 단계. phpBB3 설치 경로 입력</h3>

        <ul>
            <li>
                phpBB3를 설치한 디렉토리의 경로를 입력 해주세요.

                <blockquote>
                예1) /home/아이디/public_html/phpBB3<br />
                예2) ../phpBB3
                </blockquote>

                <input type="text" name="path" value="<?=$_POST['path']?>" class="input_text" /><input type="submit" class="input_submit" value="설치 경로 입력" />
            </li>
        </ul>
    </form>

    <?php
        if ($step > 1)
        {
    ?>
        <hr />

        <form action="./index.php" method="post">
        <input type="hidden" name="path" value="<?=$path?>" />

            <h3>2 단계. 추출할 데이터를 선택 해주세요. (회원 또는 포럼)</h3>
            <blockquote>phpBB3는 회원 정보와 포럼을 나누어 추출합니다.</blockquote>

            <ul>
                <li>
                    <label for="member">
                        <input type="radio" name="target_type" value="member" id="member" <?php if ($target_type == "member") print "checked=\"checked\""?> />
                        회원정보
                    </label>
                </li>
                <li>
                    <label for="forum">
                        <input type="radio" name="target_type" value="forum" id="forum"  <?php if ($target_type == "forum") print "checked=\"checked\""?> />
                        포럼 : 포럼 ID. 제목  (글 개수)
                    </label>

                    <select name="forum_id" size="10" class="forum_list" onclick="this.form.target_type[1].checked = true;">
                    <?php
                        foreach ($forum_list as $forum_info)
                        {
                            $cur_forum_id = $forum_info->forum_id;
                            $title = sprintf('%s. %s (%s)', $cur_forum_id, $forum_info->forum_name, $forum_info->forum_posts);
                            $selected = ($forum_id == $cur_forum_id) ? 'selected="selected"' : '';
                    ?>
                            <option value="<?=$cur_forum_id?>" <?=$selected?>><?=$title?></option>
                    <?php 
                        } 
                    ?>
                    </select><br />
                    <input type="submit" value="추출 대상 선택" class="input_submit" />
                </li>
            </ul>
        </form>
    <?
        }   // of if
    ?>

    <?php
        if ($step > 2)
        {
    ?>
        <hr />

        <form action="./index.php" method="post">
            <input type="hidden" name="path" value="<?=$path?>" />
            <input type="hidden" name="target_type" value="<?=$target_type?>" />
            <input type="hidden" name="forum_id" value="<?=$forum_id?>" />

            <h3>3 단계. 전체 개수 확인 및 분할 전송</h3>
            <blockquote>
                추출 대상 데이터의 전체 개수를 보시고 나눌 개수를 정하세요.<br />
                전체 개수가 많을 경우 적당한 개수로 나누어 추출하시는 것이 좋습니다.
            </blockquote>

            <ul>
                <li>추출 대상 데이터 개수 : <?=$total_count?></li>
                <li>
                    나눌 개수 : <input type="text" name="division" value="<?=$division?>" />
                    <input type="submit" value="나눌 개수 결정" class="input_submit" />
                </li>
                <?php if ($target_type == "forum")
                    {
                ?>
                    <li>
                        첨부파일을 포함하지 않습니다 : <input type="checkbox" name="exclude_attach" value="Y" <?php if ($exclude_attach == 'Y') print "checked=\"checked\""; ?> />
                        <input type="submit" value="첨부파일 포함 여부 재설정" class="input_submit" />
                    </li>
                <?php
                    }
                ?>
            </ul>

            <blockquote>
                phpBB3 데이터 추출 파일을 다운로드 해주세요.<br />
                차례대로 클릭하시면 다운로드 하실 수 있습니다.<br />
                다운을 받지 않고 URL을 직접 zbXE 데이터이전 모듈에 입력하여 데이터 이전하실 수도 있습니다.
            </blockquote>

            <ol>
            <?php
                $real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);
                for ($i=0; $i < $division; $i++)
                {
                    $start = $i * $division_cnt;
                    $filename = sprintf("%s%s.%06d.xml", $target_type, ( ($forum_id) ? '_' . $forum_id : '' ), $i + 1);
                    $url = sprintf("%s/export.php?filename=%s&amp;path=%s&amp;target_type=%s&amp;forum_id=%s&amp;start=%d&amp;limit_count=%d&amp;exclude_attach=%s", $real_path, urlencode($filename), urlencode($path), urlencode($target_type), urlencode($forum_id), $start, $division_cnt, $exclude_attach);
            ?>
                <li>
                    <a href="<?=$url?>"><?=$filename?></a> ( <?php print $start + 1; ?> ~ <?php print $start + $division_cnt; ?> ) [<a href="#" onclick="doCopyToClipboard('<?=$url?>'); return false;">URL 복사</a>] (IE 전용)
                </li>
            <?php
                }   // of for
            ?>
            </ol>
        </form>
    <?
        }   // of if
    ?>

    <hr />
    <address>
        powered by zero (zeroboard.com)
    </address>
</body>
</html>
