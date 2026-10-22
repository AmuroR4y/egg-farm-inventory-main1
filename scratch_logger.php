<?php
file_put_contents('scratch_log.txt', date('H:i:s') . ' ' . file_get_contents('php://input') . "\n", FILE_APPEND);
