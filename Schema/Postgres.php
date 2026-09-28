<?php

namespace Kanboard\Plugin\StatusReport\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE status_reports (
            id SERIAL PRIMARY KEY,
            task_id INTEGER NOT NULL,
            comment_id INTEGER NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            date_creation INTEGER NOT NULL,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec('CREATE INDEX status_reports_task_idx ON status_reports(task_id)');
}
