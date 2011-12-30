<?php 
    /**
     * @brief zeroboard4 export tool
     * @author zero (zero@zeroboard.com)
     **/

    @set_time_limit(0);

    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // 사용되는 변수의 선언
    $path = $_GET['path'];
    if (substr($path, -1) == '/')
        $path = substr($path, 0, strlen($path) - 1);
    $target_type = $_GET['target_type'];
    $forum_id = $_GET['forum_id'];
    $start = $_GET['start'];
    $limit_count = $_GET['limit_count'];
    $exclude_attach = ($_GET['exclude_attach'] == 'Y') ? 'Y' : 'N' ;
    $filename = $_GET['filename'];

    // 입력받은 path를 이용하여 db 정보를 구함
    $db_info = getDBInfo($path);
    if(!$db_info) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // zMigration DB 정보 설정
    $oMigration->setDBInfo($db_info);

    // 대상 정보 설정
    $oMigration->setModuleType($target_type, $forum_id);

    // 언어 설정
    $oMigration->setCharset('UTF-8', 'UTF-8');

    // 다운로드 파일명 설정
    $oMigration->setFilename($filename);

    // 경로 지정
    $oMigration->setPath($path);

    // db 접속
    if ($oMigration->dbConnect()) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // limit 쿼리 생성합니다. (MySQL이 아닌 데이터베이스 지원)
    $limit_query = $oMigration->getLimitQuery($start, $limit_count);

    /**
     * 회원 정보 export일 회원 관련 정보를 모두 가져와서 처리
     **/
    if ($target_type == 'member') {

        // 아바타 이미지 경로를 만듭니다.
        $image_avatars_path = sprintf('%s/images/avatars/upload/', $path);

        // 헤더 정보를 출력합니다.
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옵니다.
        $query = "SELECT * FROM {$db_info->db_table_prefix}users ORDER BY user_id ASC " . $limit_query;
        $member_result = $oMigration->query($query);

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출합니다.
        while ($member_info = mysql_fetch_object($member_result))
        {
            $obj = null;

            // 일반 변수들
            // phpBB3는 한글 ID를 허용하나 XE는 허용하지 않는 관계로 이메일의 앞부분을 ID로 사용합니다.
            // 중복 검사는 하지 않으니 이메일 앞부분이 중복됐는지는 phpBB3 데이터베이스에서 미리 조사를
            // 해주셔야 합니다.
            $obj->user_id = get_user_id($member_info->username, $member_info->user_email, true);      // TODO index.php 선택 사항으로 넣어야 합니다.
            $obj->password = $member_info->user_password;
            $obj->user_name = $member_info->username;
            $obj->nick_name = $member_info->username;
            $obj->email = $member_info->user_email;
            $obj->homepage = $member_info->user_website;
            $obj->blog = $member_info->user_website;
            list($birth_day, $birth_month, $birth_year) = split('-', $member_info->user_birthday);
            $birth_unixtime = mktime(0, 0, 0, $birth_month, $birth_day, $birth_year);
            $obj->birthday = date("YmdHis", $birth_unixtime);
            $obj->allow_mailing = ($member_info->user_allow_massemail != 0) ? 'Y' : 'N';
            $obj->point = $member_info->user_posts;
            $obj->regdate = date("YmdHis", $member_info->user_regdate);
            $member_info->user_sig = strip_bbcode($member_info->user_sig);
            $obj->signature = nl2br($member_info->user_sig);

            // 이미지이름, 이미지마크, 프로필이미지등은 경로를 입력
            $avatar_filename = glob($image_avatars_path . '*_' . $member_info->user_avatar);
            $obj->profile_image = array_shift($avatar_filename);

            // 확장변수 칸에 입력된 변수들은 제로보드XE의 멤버 확장변수를 통해서 사용될 수 있음
            $obj->extra_vars = array(
                'icq' => $member_info->user_icq,
                'aol' => $member_info->user_yim,
                'msn' => $member_info->user_msnm,
                'home_address' => $member_info->user_from,
            );

            $oMigration->printMemberItem($obj);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {

        // 첨부 파일 경로를 만듭니다.
        $attachment_path = sprintf('%s/files', $path);
        $attachment_path = realpath($attachment_path);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // phpBB는 카테고리가 없으므로 빈 배열을 넘깁니다.
        $category_list = array();

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 토픽을 글로 처리하고 토픽에 딸린 글들은 댓글로 처리합니다.
        $query = sprintf(
                    'SELECT a.*, b.* FROM %s%s a LEFT OUTER JOIN %s%s b ON a.topic_poster = b.user_id ' .
                    'WHERE a.forum_id = %s ORDER BY a.topic_id ASC %s',
                    $db_info->db_table_prefix, 'topics', $db_info->db_table_prefix, 'users', $forum_id, $limit_query);
        $document_result = $oMigration->query($query);

        while ($document_info = mysql_fetch_object($document_result))
        {
            $obj = null;

            $obj->title = $document_info->topic_title;                      // 제목
            $obj->readed_count = $document_info->topic_views;               // 조회수
            $obj->voted_count = 0;                                          // 추천수
            $obj->user_id = get_user_id($document_info->username, $document_info->user_email, true);      // TODO index.php 선택 사항으로 넣어야 합니다.
            $obj->nick_name = $document_info->username;                     // 사용자 닉네임
            $obj->email = $document_info->user_email;                       // 사용자 이메일
            $obj->homepage = $document_info->user_website;                  // 사용자 홈페이지
            $obj->password = $document_info->user_password;                 // 사용자 패스워드
            $obj->allow_comment = 'Y';                                      // 댓글 허용 여부
            $obj->lock_comment = 'N';                                       // 댓글 금지 여부
            $obj->allow_trackback = 'Y';                                    // 트랙백 허용 여부
            $obj->is_secret = 'N';                                          // 비밀글 여부
            $obj->regdate = date("YmdHis", $document_info->topic_time);     // 등록일시
            $obj->update = null;
            $obj->tags = '';                                                // 태그

            // $obj->content = $document_info->memo;                        // 내용
            // $obj->ipaddress = $document_info->ip;                        // 사용자 IP

            // 토픽의 다른 글들을 댓글로 처리합니다. 토픽 최초 글에 대한 부족한 정보(내용, IP)도 함께 처리합니다.
            $comments = array();
            $query = sprintf(
                        'SELECT a.*, b.* FROM %s%s a LEFT OUTER JOIN %s%s b ON a.poster_id = b.user_id ' .
                        'WHERE a.forum_id = %s AND a.topic_id = %s ORDER BY a.post_id ASC %s',
                        $db_info->db_table_prefix, 'posts', $db_info->db_table_prefix, 'users', $forum_id, $document_info->topic_id, $limit_query);
            $comment_result = $oMigration->query($query);
            while ($comment_info = mysql_fetch_object($comment_result))
            {
                // 토픽의 첫번째 글이므로 부족한 정보를 채우고 다음 글로 넘어간다
                if ($comment_info->post_id == $document_info->topic_first_post_id)
                {
                    $comment_info->post_text = strip_bbcode($comment_info->post_text);
                    $obj->content = nl2br($comment_info->post_text);                    // 내용
                    $obj->ipaddress = $comment_info->poster_ip;                         // IP
                    continue;
                }

                $comment_obj = null;

                // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                $comment_obj->sequence = $comment_obj->post_id;

                // phpBB3 글에는 depth가 없으므로 parent를 0으로 설정합니다. 다른 프로그램이라면 부모 고유값을 입력해주면 됩니다.
                $comment_obj->parent = 0; 

                $comment_obj->is_secret = 'N';
                $comment_info->post_text = strip_bbcode($comment_info->post_text);
                $comment_obj->content = nl2br($comment_info->post_text);
                $comment_obj->voted_count = 0;
                $comment_obj->notify_message = 'N';
                $comment_obj->password = $comment_info->user_password;
                $comment_obj->user_id = get_user_id($comment_info->username, $comment_info->user_email, true);      // TODO index.php 선택 사항으로 넣어야 합니다.
                $comment_obj->nick_name = $comment_info->username;
                $comment_obj->email = $comment_info->user_email;
                $comment_obj->homepage = $comment_info->user_website;
                $comment_obj->update = null;
                $comment_obj->regdate = date('YmdHis', $comment_info->post_time);
                $comment_obj->ipaddress = $comment_info->poster_ip;

                $comments[] = $comment_obj;
            }
            $obj->comments = $comments;

            // 첨부파일 구합니다.
            $files = array();
            $image_header = '';

            // 게시물을 댓글로 처리하는 관계로 해당 토픽 안의 모든 파일은 토픽 첫글에 다 첨부합니다.
            $query = sprintf('SELECT * FROM %s%s WHERE topic_id = %s ORDER BY attach_id ASC',
                        $db_info->db_table_prefix, 'attachments', $document_info->topic_id);
            $file_result = $oMigration->query($query);
            while ($file_info = mysql_fetch_object($file_result))
            {
                $file_obj = null;
                $file_obj->filename = $file_info->real_filename;
                $file_obj->file = sprintf('%s/%s', $attachment_path, $file_info->physical_filename);
                $file_obj->download_count = $file_info->download_count;
                $files[] = $file_obj;

                // TODO XE에 임포트 한 후 경로를 미리 계산해서 경로로 입력하도록 해야합니다.
                // TODO 마이그레이션 툴 쪽에서는 기존 phpBB3 경로를 입력하는데 이럴 경우 파일을 이중으로
                // TODO 저장하여 디스크 공간 낭비가 발생합니다.

                // 이미지 파일이라면 내용 상단에 이미지 추가
                /*
                if (eregi('\.(jpg|gif|jpeg|png)$', $file_info->real_filename))
                    $image_header .= sprintf('<img src="%s/%s" border="0" alt="" /><br /><br />', $attachment_path, $file_info->real_filename);
                */
            }

            $obj->content = $image_header . $obj->content;

            $obj->attaches = $files;

            $oMigration->printPostItem($document_info->no, $obj, $exclude_attach);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();
    }

    // 사용자이름과 이메일 계정명 중 하나를 선택해 사용자 ID로 반환합니다.
    function get_user_id($username, $email, $use_email = true)
    {
        if ($use_email)
            list($user_id) = split('@', $email);
        else
            $user_id = $username;

        return trim($user_id);
    }

    // bbcode 를 제거합니다.
    function strip_bbcode($text)
    {
        $text = preg_replace('/\[b.*?\](.*?)\[\/b.*?\]/s', '$1', $text);
        $text = preg_replace('/\[i.*?\](.*?)\[\/i.*?\]/s', '$1', $text);
        $text = preg_replace('/\[u.*?\](.*?)\[\/u.*?\]/s', '$1', $text);
        $text = preg_replace('/\[quote.*?\](.*?)\[\/quote.*?\]/s', '$1', $text);
        $text = preg_replace('/\[code.*?\](.*?)\[\/code.*?\]/s', '$1', $text);
        $text = preg_replace('/\[img.*?\](.*?)\[\/img.*?\]/s', '$1', $text);
        $text = preg_replace('/\[url.*?\](.*?)\[\/url.*?\]/s', '$1', $text);
        $text = preg_replace('/\[flash.*?\](.*?)\[\/flash.*?\]/s', '$1', $text);
        $text = preg_replace('/\[size.*?\](.*?)\[\/size.*?\]/s', '$1', $text);
        $text = preg_replace('/\[color.*?\](.*?)\[\/color.*?\]/s', '$1', $text);
        $text = preg_replace('/\[list.*?\](.*?)\[\/list.*?\]/s', '$1', $text);
        // [attachment=1:263ok2io]024돌잔치.jpg[/attachment:263ok2io]
        $text = preg_replace('/\[attachment.*?\](.*?)\[\/attachment.*?\]/s', '', $text);
        $text = preg_replace('/\[\*\]/', ' * $1', $text);
        $text = preg_replace('/\[\*.*?\](.*?)\[\/\*.*?\]/s', ' * $1', $text);

        return $text;
    }
?>
