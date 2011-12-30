<?php
    /**
     * @brief   phpBB3의 경로를 이용하여 DB정보를 가져옵니다.
     * @author  zero (zero@zeroboard.com)
     **/
    function getDBInfo($path) {
        if (substr($path, -1) == '/')
            $path = substr($path, 0, strlen($path) - 1);

        $config_file = sprintf('%s/config/global.php', $path);

        if(!file_exists($config_file))
            return;

        $config = file($config_file);
        foreach ($config as $line)
            if ( ereg('^\$', trim($line)) )
                eval($line);

        $db_info->db_type = 'mysql';
        $db_info->db_hostname = $db[server];
        $db_info->db_port = 3306;
        $db_info->db_userid = $db[user];
        $db_info->db_password = $db[pass];
        $db_info->db_database = $db[name];
        $db_info->db_table_prefix = '';

        if ($db_info->db_hostname == '')
            $db_info->db_hostname = 'localhost';
        if ($db_info->db_port == '')
            $db_info->db_port = '3306';

        return $db_info;
    } 
?>
