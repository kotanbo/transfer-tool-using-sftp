<?php
// php-pecl-ssh2 が必要
//   例：yum install --enablerepo=remi,remi-php74 php-pecl-ssh2
// 下記を設定し、対象ファイル絞り込み部分を任意に書換
define('HOST_FOR_SFTP', 'xxx.xxx.xxx.xxx');
define('PORT_FOR_SFTP', '22');
define('USER_FOR_SFTP', 'root');
define('PUBLIC_KEY_FOR_SFTP', './id_rsa.pub');
define('PRIVATE_KEY_FOR_SFTP', './id_rsa');
define('SOURCE_DIRECTORY_PATH_FOR_SFTP', '/var/www/html/xxxxx');
define('DESTINATION_DIRECTORY_PATH_FOR_SFTP', '/var/www/html/xxxxx');

function getFileList($directoryPath) {
    $files = [];
    foreach(glob($directoryPath . DIRECTORY_SEPARATOR . '*') as $file) {
        if (is_file($file)) {
            $fileInfo = pathinfo($file);
            if (preg_match('/^.+(_list|_detail)$/', $fileInfo['filename'])) { // 対象ファイルを絞り込み
                $files[] = $file;
            }
        }
        if (is_dir($file)) {
            $files = array_merge($files, getFileList($file)); // 再帰により下位階層取得
        }
    }
    return $files;
}

$move = function () {
    $host = HOST_FOR_SFTP;
    $port = PORT_FOR_SFTP;
    $user = USER_FOR_SFTP;
    $publicKey = PUBLIC_KEY_FOR_SFTP;
    $privateKey = PRIVATE_KEY_FOR_SFTP;
    $sourceDirectoryPath = SOURCE_DIRECTORY_PATH_FOR_SFTP;
    $destinationDirectoryPath = DESTINATION_DIRECTORY_PATH_FOR_SFTP;

    $conn = ssh2_connect($host, $port);
    try {
        $ret = ssh2_auth_pubkey_file($conn, $user, $publicKey, $privateKey);
        $sftp = ssh2_sftp($conn);

        $files = getFileList($sourceDirectoryPath);
        foreach($files as $file) {
            $destinationFilePath = $destinationDirectoryPath . str_replace($sourceDirectoryPath, '', $file);
            // 移動先にファイルがない場合は元ファイルを移動先に作成
            $stat = @ssh2_sftp_stat($sftp, $destinationFilePath);
            if (empty($stat)) {
                $directoryPath = dirname($destinationFilePath);
                ssh2_sftp_mkdir($sftp, $directoryPath, 0777, true);
                echo 'mkdir ' . $directoryPath . "\n";

                $contents = file_get_contents($file);
                $fp = null;
                try {
                    $fp = fopen('ssh2.sftp://' . (int)$sftp . $destinationFilePath, 'w+');
                    fwrite($fp, $contents);
                    echo 'write ' . $destinationFilePath . "\n";
                } finally {
                    if (!empty($fp)) {
                        fclose($fp);
                    }
                }
            }
            // 移動先にファイルがある場合は元ファイル削除
            $stat = ssh2_sftp_stat($sftp, $destinationFilePath);
            if (!empty($stat)) {
                unlink($file);
                echo 'unlink ' . $destinationFilePath . "\n";
            }
        }
    } finally {
        if (!empty($conn)) {
            ssh2_exec($conn, 'exit');
            unset($conn);
        }
    }
};
$move();
