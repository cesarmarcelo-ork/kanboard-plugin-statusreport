<?php

namespace Kanboard\Plugin\StatusReport\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE status_reports (
            id INT NOT NULL AUTO_INCREMENT,
            task_id INT NOT NULL,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            date_creation INT NOT NULL,
            PRIMARY KEY(id),
            UNIQUE KEY status_reports_comment_uniq (comment_id),
            KEY status_reports_task_idx (task_id),
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8
    ");
}
