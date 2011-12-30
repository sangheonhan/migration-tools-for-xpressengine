<?php
    /**
     * @brief   phpBB3의 경로를 이용하여 DB정보를 가져옵니다.
     * @author  zero (zero@zeroboard.com)
     **/
    function getDBInfo($path) {
        if (substr($path, -1) == '/')
            $path = substr($path, 0, strlen($path) - 1);

        $config_file = sprintf('%s/config.php', $path);

        if(!file_exists($config_file))
            return;

        $config = file($config_file);
        foreach ($config as $line)
            if ( ereg('^\$', trim($line)) )
                eval($line);

        $db_info->db_type = $dbms;
        $db_info->db_hostname = $dbhost;
        $db_info->db_port = $dbport;
        $db_info->db_userid = $dbuser;
        $db_info->db_password = $dbpasswd;
        $db_info->db_database = $dbname;
        $db_info->db_table_prefix = $table_prefix;

        if ($db_info->db_hostname == '')
            $db_info->db_hostname = 'localhost';
        if ($db_info->db_port == '')
            $db_info->db_port = '3306';

        return $db_info;
    } 
?>
